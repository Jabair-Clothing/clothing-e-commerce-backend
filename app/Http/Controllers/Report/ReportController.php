<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\User;
use App\Models\Order_list;
use App\Models\ProductSku;
use App\Models\Transition;
use App\Models\Wishlist;
use App\Models\Coupon;
use Carbon\Carbon;


class ReportController extends Controller
{
    private function dateRange(Request $request): array
    {
        $start = $request->filled('start_date')
            ? Carbon::parse($request->start_date)->startOfDay()
            : now()->startOfMonth();

        $end = $request->filled('end_date')
            ? Carbon::parse($request->end_date)->endOfDay()
            : now()->endOfDay();

        return [$start, $end];
    }

    // =========================
    // A) Overview
    // =========================
    public function overview(Request $request)
    {
        [$start, $end] = $this->dateRange($request);

        $totalOrderCount = Order::count();
        $newOrderCount   = Order::where('status', 0)->count();
        $todayOrderCount = Order::whereDate('created_at', now()->toDateString())->count();

        $totalRevenue = Transition::sum('amount');
        $todayRevenue = Transition::whereDate('created_at', now()->toDateString())->sum('amount');

        // Clients count (adjust if you use 'member' instead of 'user')
        $totalClientCount = User::where('type', 'user')->count();

        // Receivables (due)
        $dueAgg = Order::leftJoin('payments', 'payments.order_id', '=', 'orders.id')
            ->selectRaw("
                COUNT(*) as due_orders_count,
                SUM(
                    (COALESCE(payments.amount, orders.total_amount) - COALESCE(payments.paid_amount, 0))
                ) as total_due_amount
            ")
            ->whereBetween('orders.created_at', [$start, $end])
            ->whereRaw("(COALESCE(payments.amount, orders.total_amount) - COALESCE(payments.paid_amount, 0)) > 0")
            ->first();

        // Recent orders
        $recentOrders = Order::with('user')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn($o) => [
                'order_id' => $o->id,
                'invoice_code' => $o->invoice_code,
                'status' => $o->status,
                'total_amount' => $o->total_amount,
                'customer_name' => $o->user?->name ?? $o->user_name,
                'time_ago' => $o->created_at?->diffForHumans(),
                'placed_at' => $o->created_at?->format('Y-m-d H:i:s'),
            ]);

        // Charts (last 7 days)
        $days = collect(range(0, 6))->map(fn($i) => now()->subDays($i)->toDateString())->reverse()->values();

        $ordersByDay = Order::selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->whereDate('created_at', '>=', now()->subDays(6)->toDateString())
            ->groupBy('d')
            ->pluck('c', 'd');

        $revenueByDay = Transition::selectRaw('DATE(created_at) as d, SUM(amount) as s')
            ->whereDate('created_at', '>=', now()->subDays(6)->toDateString())
            ->groupBy('d')
            ->pluck('s', 'd');

        $ordersChart = $days->map(fn($d) => ['date' => $d, 'orders' => (int)($ordersByDay[$d] ?? 0)]);
        $revenueChart = $days->map(fn($d) => ['date' => $d, 'revenue' => (float)($revenueByDay[$d] ?? 0)]);

        $statusDistribution = Order::selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(fn($r) => ['status' => (string)$r->status, 'count' => (int)$r->total]);

        // Top selling products (by qty)
        $topSelling = Order_list::join('products', 'products.id', '=', 'order_lists.product_id')
            ->join('orders', 'orders.id', '=', 'order_lists.order_id')
            ->whereBetween('orders.created_at', [$start, $end])
            ->selectRaw('products.id, products.name, SUM(order_lists.quantity) as sold_qty, SUM(order_lists.quantity * order_lists.price) as sold_amount')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('sold_qty')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'status' => 200,
            'data' => [
                'cards' => [
                    'total_order_count' => $totalOrderCount,
                    'new_order_count' => $newOrderCount,
                    'today_order_count' => $todayOrderCount,
                    'total_revenue' => (float)$totalRevenue,
                    'today_revenue' => (float)$todayRevenue,
                    'total_client_count' => $totalClientCount,
                    'due_orders_count' => (int)($dueAgg->due_orders_count ?? 0),
                    'total_due_amount' => (float)($dueAgg->total_due_amount ?? 0),
                ],
                'recent_orders' => $recentOrders,
                'top_selling_products' => $topSelling,
                'charts' => [
                    'orders_last_7_days' => $ordersChart,
                    'revenue_last_7_days' => $revenueChart,
                    'status_distribution' => $statusDistribution,
                ],
                'range' => [
                    'start' => $start->toDateTimeString(),
                    'end' => $end->toDateTimeString(),
                ],
            ],
        ]);
    }

    // =========================
    // B) Sales report
    // =========================
    public function sales(Request $request)
    {
        [$start, $end] = $this->dateRange($request);

        $groupBy = $request->input('group_by', 'day'); // day|month
        $format = $groupBy === 'month' ? '%Y-%m' : '%Y-%m-%d';

        $rows = Transition::selectRaw("DATE_FORMAT(created_at, '{$format}') as period, SUM(amount) as revenue")
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        $orders = Order::selectRaw("DATE_FORMAT(created_at, '{$format}') as period, COUNT(*) as orders")
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->keyBy('period');

        $data = $rows->map(function ($r) use ($orders) {
            $o = $orders[$r->period] ?? null;
            $orderCount = (int)($o->orders ?? 0);
            $rev = (float)$r->revenue;

            return [
                'period' => $r->period,
                'orders' => $orderCount,
                'revenue' => $rev,
                'aov' => $orderCount > 0 ? $rev / $orderCount : 0,
            ];
        });

        return response()->json(['success' => true, 'status' => 200, 'data' => $data]);
    }

    // =========================
    // C) Receivables (Due)
    // =========================
    public function receivables(Request $request)
    {
        [$start, $end] = $this->dateRange($request);
        $limit = (int)($request->input('limit', 50));

        $query = Order::with(['user', 'payment'])
            ->whereBetween('created_at', [$start, $end])
            ->latest();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_code', 'like', "%{$search}%")
                    ->orWhereHas('user', fn($uq) => $uq->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"));
            });
        }

        $list = $query->take($limit)->get()->map(function ($order) {
            $payment = $order->payment;
            $paid = $payment?->paid_amount ?? 0;
            $total = $payment?->amount ?? $order->total_amount ?? 0;
            $due = $total - $paid;

            if ($due <= 0) return null;

            return [
                'order_id' => $order->id,
                'invoice_code' => $order->invoice_code,
                'customer' => $order->user?->name ?? $order->user_name,
                'phone' => $order->user?->phone ?? $order->phone,
                'total_amount' => $total,
                'paid_amount' => $paid,
                'due_amount' => $due,
                'time_ago' => $order->created_at?->diffForHumans(),
            ];
        })->filter()->values();

        return response()->json([
            'success' => true,
            'status' => 200,
            'data' => [
                'count' => $list->count(),
                'total_due_amount' => (float)$list->sum('due_amount'),
                'list' => $list,
            ],
        ]);
    }

    // =========================
    // D) Inventory report
    // =========================
    public function inventory(Request $request)
    {
        try {
            $type = $request->input('type', 'all'); // all|low|out
            $lowStock = (int) $request->input('low_stock', 5);
            $limit = (int) $request->input('limit', 50);

            $query = ProductSku::query()
                ->where('is_deleted', 0)
                ->with([
                    'product:id,name,category_id,parent_category_id',
                    // load variant values + attribute names
                    'attributeValues:id,attribute_id,name,code',
                    'attributeValues.attribute:id,name,slug',
                ]);

            // filter by category
            if ($request->filled('category_id')) {
                $categoryId = (int) $request->category_id;
                $query->whereHas('product', function ($pq) use ($categoryId) {
                    $pq->where('category_id', $categoryId);
                });
            }

            // filter by stock type
            if ($type === 'out') {
                $query->where('quantity', '<=', 0);
            } elseif ($type === 'low') {
                $query->whereBetween('quantity', [1, $lowStock]);
            }

            // Do NOT use latest() if product_skus has no created_at
            $skusPage = $query->orderBy('id', 'desc')->paginate($limit);

            $list = collect($skusPage->items())->map(function ($sku) {
                // Build variant list like: Color: Red, Size: XL
                $variants = $sku->attributeValues->map(function ($val) {
                    return [
                        'attribute_id' => $val->attribute_id,
                        'attribute_name' => $val->attribute ? $val->attribute->name : null,
                        'value_id' => $val->id,
                        'value_name' => $val->name,
                        'value_code' => $val->code,
                    ];
                })->values();

                // Also make a readable string for UI
                $variantText = $variants->map(function ($v) {
                    return ($v['attribute_name'] ?? 'Attribute') . ': ' . ($v['value_name'] ?? '');
                })->implode(', ');

                return [
                    'sku_id' => $sku->id,
                    'sku' => $sku->sku,
                    'product_id' => $sku->product_id,
                    'product_name' => $sku->product ? $sku->product->name : null,

                    'quantity' => (int) $sku->quantity,
                    'price' => (float) $sku->price,
                    'discount_price' => $sku->discount_price !== null ? (float) $sku->discount_price : null,

                    // Variant-wise output
                    'variants' => $variants,
                    'variant_text' => $variantText,
                ];
            });

            // Summary (safe bindings)
            $summary = ProductSku::where('is_deleted', 0)
                ->selectRaw('COUNT(*) as total_skus')
                ->selectRaw('SUM(CASE WHEN quantity <= 0 THEN 1 ELSE 0 END) as out_of_stock')
                ->selectRaw('SUM(CASE WHEN quantity BETWEEN 1 AND ? THEN 1 ELSE 0 END) as low_stock', [$lowStock])
                ->first();

            return response()->json([
                'success' => true,
                'status' => 200,
                'data' => [
                    'summary' => [
                        'total_skus' => (int) ($summary->total_skus ?? 0),
                        'out_of_stock' => (int) ($summary->out_of_stock ?? 0),
                        'low_stock' => (int) ($summary->low_stock ?? 0),
                        'low_stock_threshold' => $lowStock,
                    ],
                    'pagination' => [
                        'current_page' => $skusPage->currentPage(),
                        'per_page' => $skusPage->perPage(),
                        'total' => $skusPage->total(),
                        'last_page' => $skusPage->lastPage(),
                    ],
                    'list' => $list,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Inventory report failed.',
                'errors' => $e->getMessage(),
            ], 500);
        }
    }


    // =========================
    // E) Best sellers
    // =========================
    public function bestSellers(Request $request)
    {
        [$start, $end] = $this->dateRange($request);
        $limit = (int)($request->input('limit', 10));

        $data = Order_list::join('orders', 'orders.id', '=', 'order_lists.order_id')
            ->join('products', 'products.id', '=', 'order_lists.product_id')
            ->whereBetween('orders.created_at', [$start, $end])
            ->selectRaw('products.id, products.name, SUM(order_lists.quantity) as sold_qty, SUM(order_lists.quantity * order_lists.price) as sold_amount')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('sold_qty')
            ->limit($limit)
            ->get();

        return response()->json(['success' => true, 'status' => 200, 'data' => $data]);
    }

    // =========================
    // F) Coupon report
    // =========================
    public function coupons(Request $request)
    {
        [$start, $end] = $this->dateRange($request);

        $data = Coupon::leftJoin('orders', 'orders.coupons_id', '=', 'coupons.id')
            ->whereBetween('orders.created_at', [$start, $end])
            ->selectRaw('coupons.id, coupons.code, COUNT(orders.id) as orders_count, SUM(COALESCE(orders.discount,0)) as discount_sum, SUM(COALESCE(orders.total_amount,0)) as sales_sum')
            ->groupBy('coupons.id', 'coupons.code')
            ->orderByDesc('orders_count')
            ->get();

        return response()->json(['success' => true, 'status' => 200, 'data' => $data]);
    }

    // =========================
    // G) Wishlists report
    // =========================
    public function wishlists(Request $request)
    {
        $limit = (int)($request->input('limit', 20));

        $data = Wishlist::join('products', 'products.id', '=', 'wishlists.product_id')
            ->selectRaw('products.id, products.name, COUNT(wishlists.id) as wishlist_count')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('wishlist_count')
            ->limit($limit)
            ->get();

        return response()->json(['success' => true, 'status' => 200, 'data' => $data]);
    }
}

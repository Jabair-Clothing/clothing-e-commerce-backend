<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Transition;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AdminDashboardController extends Controller
{
    //Admin dashboard overview
    public function adminDashboard(Request $request)
    {
        try {
            $totalOrderCount = Order::count();
            $newOrderCount   = Order::where('status', 0)->count();
            $todayOrderCount = Order::whereDate('created_at', now()->toDateString())->count();

            // Revenue from transitions
            $totalRevenue = Transition::sum('amount');
            $todayRevenue = Transition::whereDate('created_at', now()->toDateString())->sum('amount');

            $totalClientCount = User::where('type', 'user')->count();

            $dueOrdersQuery = Order::with(['user', 'payment'])
                ->orderBy('created_at', 'desc');

            // optional filters if you want dashboard to support them too
            if ($request->filled('search')) {
                $search = $request->input('search');
                $dueOrdersQuery->where(function ($q) use ($search) {
                    $q->where('invoice_code', 'like', "%$search%")
                        ->orWhereHas('user', function ($userQ) use ($search) {
                            $userQ->where('name', 'like', "%$search%")
                                ->orWhere('phone', 'like', "%$search%");
                        });
                });
            }

            if ($request->filled('start_date') && $request->filled('end_date')) {
                $dueOrdersQuery->whereBetween('created_at', [
                    Carbon::parse($request->start_date)->startOfDay(),
                    Carbon::parse($request->end_date)->endOfDay(),
                ]);
            }

            $dueOrders = $dueOrdersQuery->get()->map(function ($order) {
                $payment = $order->payment;

                // IMPORTANT: paid_amount (not padi_amount)
                $paidAmount  = $payment?->paid_amount ?? 0;
                $totalAmount = $payment?->amount ?? $order->total_amount ?? 0;
                $dueAmount   = $totalAmount - $paidAmount;

                if ($dueAmount <= 0) return null;

                return [
                    'user_name' => $order->user?->name ?? $order->user_name,
                    'user_phone' => $order->user?->phone ?? $order->phone,
                    'user_email' => $order->user?->email ?? null,
                    'order_id' => $order->id,
                    'invoice_code' => $order->invoice_code,
                    'status' => $order->status,
                    'total_amount' => $totalAmount,
                    'paid_amount' => $paidAmount,
                    'due_amount' => $dueAmount,
                    'order_placed_date_time' => $order->created_at->format('Y-m-d H:i:s'),
                    'placed_human' => $order->created_at->diffForHumans(),
                ];
            })->filter()->values();

            $dueOrdersCount = $dueOrders->count();
            $totalDueAmount = $dueOrders->sum('due_amount');
 
            // Recent orders (top 5)
            $recentOrders = Order::with(['user'])
                ->latest()
                ->take(5)
                ->get()
                ->map(function ($order) {
                    return [
                        'order_id' => $order->id,
                        'invoice_code' => $order->invoice_code,
                        'status' => $order->status,
                        'total_amount' => $order->total_amount,
                        'customer_name' => $order->user?->name ?? $order->user_name,
                        'placed_at' => $order->created_at->format('Y-m-d H:i:s'),
                        'time_ago' => $order->created_at->diffForHumans(), // "2 minutes ago"
                    ];
                });

            // Chart/Graph datasets
            // (Frontend will use these arrays to draw charts)

            //  Orders per day (last 7 days)
            $last7Days = collect(range(0, 6))->map(function ($i) {
                return now()->subDays($i)->toDateString();
            })->reverse()->values();

            $ordersLast7Days = Order::selectRaw('DATE(created_at) as date, COUNT(*) as total')
                ->whereDate('created_at', '>=', now()->subDays(6)->toDateString())
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('total', 'date');

            $ordersChart = $last7Days->map(function ($date) use ($ordersLast7Days) {
                return [
                    'date' => $date,
                    'orders' => (int) ($ordersLast7Days[$date] ?? 0),
                ];
            });

            //  Revenue per day (last 7 days) from transitions
            $revenueLast7Days = Transition::selectRaw('DATE(created_at) as date, SUM(amount) as total')
                ->whereDate('created_at', '>=', now()->subDays(6)->toDateString())
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('total', 'date');

            $revenueChart = $last7Days->map(function ($date) use ($revenueLast7Days) {
                return [
                    'date' => $date,
                    'revenue' => (float) ($revenueLast7Days[$date] ?? 0),
                ];
            });

            // Order status distribution (pie/donut chart)
            $statusDistribution = Order::selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->orderBy('status')
                ->get()
                ->map(fn($row) => [
                    'status' => (string) $row->status,
                    'count'  => (int) $row->total,
                ]);

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Dashboard data retrieved successfully.',
                'data' => [
                    // existing
                    'total_order_count' => $totalOrderCount,
                    'new_order_count' => $newOrderCount,
                    'today_order_count' => $todayOrderCount,
                    'total_revenue' => $totalRevenue,
                    'today_revenue' => $todayRevenue,

                    // added
                    'total_client_count' => $totalClientCount,

                    // due order summary
                    'due_orders' => [
                        'count' => $dueOrdersCount,
                        'total_due_amount' => $totalDueAmount,
                        'list' => $dueOrders, // if too heavy, paginate or limit
                    ],

                    // recent orders
                    'recent_orders' => $recentOrders,

                    // chart datasets
                    'charts' => [
                        'orders_last_7_days' => $ordersChart,
                        'revenue_last_7_days' => $revenueChart,
                        'status_distribution' => $statusDistribution,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error in adminDashboard: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while retrieving dashboard data.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }
}

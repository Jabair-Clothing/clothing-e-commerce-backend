<?php

namespace App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Order_list;
use App\Models\Product;
use App\Models\ProductSku;
use App\Models\Payment;
use App\Models\Coupon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Jobs\SendOrderEmailsJob;
use App\Helpers\ActivityHelper;


class OrderController extends Controller
{
    //plase order by user
    /**
     * Place an order
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function placeOrder(Request $request)
    {
        DB::beginTransaction();

        try {
            // Validate the basic request structure
            $validator = $this->validateOrderRequest($request);
            if ($validator->fails()) {
                return $this->validationErrorResponse($validator);
            }

            $discount = 0;
            $couponId = $request->input('coupon_id');

            // If a coupon is provided, validate it and calculate the discount
            if ($couponId) {
                $discount = $this->applyAndValidateCoupon($request);
            }

            //  **Crucial Security Step:** Recalculate total on the server
            $calculatedTotal = ($request->product_subtotal + $request->shipping_charge) - $discount;

            // Optional but recommended: Compare server-calculated total with client-sent total
            // if (abs($calculatedTotal - $request->total) > 0.01) { // Using a small tolerance for float comparison
            //     throw new \Exception('Total amount mismatch. Please recalculate and try again.');
            // }


            // Check product availability and update quantities
            $this->updateProductQuantities($request->products);

            // Generate invoice code
            $invoiceCode = $this->generateInvoiceCode();

            // Generate order description
            $orderDescription = $this->generateOrderDescription($request->products);

            //  Create the order with server-calculated values
            $order = $this->createOrder($request, $invoiceCode, $discount, $calculatedTotal, $orderDescription);

            // Save order items
            $this->saveOrderItems($order, $request->products);

            // Create payment record
            $this->createPayment($order, $request, $calculatedTotal);

            DB::commit();

            // Dispatch the job to send emails asynchronously
            // Only send email if order has a valid user_id
            // if ($order->user_id) {
            //     dispatch(new SendOrderEmailsJob($order));
            // }

            // Return success response
            return $this->successResponse($order, 'Order placed successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            // Use a more specific status code for validation-like errors
            $statusCode = ($e->getCode() >= 400 && $e->getCode() < 500) ? $e->getCode() : 500;
            return $this->errorResponse($e->getMessage(), $statusCode);
        }
    }

    /**
     * Validate and apply the coupon.
     * Throws exceptions for invalid conditions.
     *
     * @param Request $request
     * @return float The calculated discount amount.
     * @throws \Exception
     */
    private function applyAndValidateCoupon(Request $request): float
    {
        $coupon = Coupon::with('products')->find($request->coupon_id);

        // Basic Checks
        if (!$coupon) {
            throw new \Exception('Invalid coupon provided.', 404);
        }
        if ($coupon->status != 0) {
            throw new \Exception('This coupon is not active.', 400);
        }

        $now = Carbon::now();
        if ($coupon->start_date && $now->isBefore(Carbon::parse($coupon->start_date))) {
            throw new \Exception('This coupon is not yet valid.', 400);
        }
        if ($coupon->end_date && $now->isAfter(Carbon::parse($coupon->end_date))) {
            throw new \Exception('This coupon has expired.', 400);
        }

        // Usage Limit Checks
        $totalUsage = Order::where('coupons_id', $coupon->id)->count();
        if ($totalUsage >= $coupon->max_usage) {
            throw new \Exception('Coupon has reached its maximum usage limit.', 400);
        }

        if ($request->user_id) {
            $userUsage = Order::where('coupons_id', $coupon->id)
                ->where('user_id', $request->user_id)
                ->count();
            if ($userUsage >= $coupon->max_usage_per_user) {
                throw new \Exception('You have already used this coupon the maximum number of times.', 400);
            }
        }

        // Calculate eligible amount based on coupon type (global or product-specific)
        $eligibleAmount = 0;
        if ($coupon->is_global) {
            $eligibleAmount = $request->product_subtotal;
        } else {
            $couponProductIds = $coupon->products->pluck('id')->toArray();
            $cartProducts = collect($request->products);
            $productIdsInCart = $cartProducts->pluck('product_id');

            // Eager load item prices to avoid multiple queries in loop
            $itemsInCart = Product::whereIn('id', $productIdsInCart)->get()->keyBy('id');

            foreach ($cartProducts as $cartProduct) {
                if (in_array($cartProduct['product_id'], $couponProductIds)) {
                    $item = $itemsInCart->get($cartProduct['product_id']);
                    if ($item) {
                        $eligibleAmount += $item->base_price * $cartProduct['quantity'];
                    }
                }
            }
        }

        // Minimum Purchase Check
        if ($eligibleAmount < $coupon->min_pur) {
            throw new \Exception("A minimum purchase of {$coupon->min_pur} on eligible items is required to use this coupon.", 400);
        }

        // Calculate Discount
        $discount = 0;
        if ($coupon->type === 'percent') {
            $discount = ($eligibleAmount * $coupon->amount) / 100;
        } elseif ($coupon->type === 'flat') {
            $discount = $coupon->amount;
        }

        // Ensure discount is not more than the eligible amount
        return min($discount, $eligibleAmount);
    }

    /**
     * Create the order
     *
     * @param Request $request
     * @param string $invoiceCode
     * @param float $discount
     * @param float $totalAmount
     * @param string $orderDescription
     * @return Order
     */
    private function createOrder(Request $request, $invoiceCode, $discount, $totalAmount, $orderDescription): Order
    {
        return Order::create([
            'invoice_code' => $invoiceCode,
            'user_id' => $request->user_id,
            'shipping_id' => $request->shipping_id,
            'status' => '0',
            'item_subtotal' => $request->product_subtotal,
            'shipping_charge' => $request->shipping_charge,
            'total_amount' => $totalAmount,
            'coupons_id' => $request->coupon_id, // Store the coupon id
            'discount' => $discount, // Store the calculated discount
            'user_name' => $request->user_name,
            'phone' => $request->userphone,
            'address' => $request->address,
            'order_description' => $orderDescription,
        ]);
    }

    /**
     * Create payment record
     *
     * @param Order $order
     * @param Request $request
     * @param float $totalAmount
     */
    private function createPayment(Order $order, Request $request, float $totalAmount)
    {
        $paymentStatus = $request->payment_type == 2 ? 4 : 0; // Assuming 2=Bkash(Paid), 1=COD(Unpaid)
        Payment::create([
            'order_id' => $order->id,
            'status' => $paymentStatus,
            'amount' => $totalAmount,
            'paid_amount' => 0,
            'payment_type' => $request->payment_type,
            'trxed' => $request->trxed,
            'phone' => $request->paymentphone
        ]);
    }

    // successResponse, errorResponse
    // Validate the order request
    private function validateOrderRequest(Request $request)
    {
        return Validator::make($request->all(), [
            'coupon_id' => 'nullable|exists:coupons,id',
            'user_id' => 'nullable|exists:users,id',
            'shipping_id' => 'nullable|exists:shipping_addresses,id',
            'shipping_charge' => 'nullable|numeric|min:0',
            'product_subtotal' => 'required|numeric|min:0',
            'total' => 'required|numeric|min:0',
            'paymentphone' => 'nullable|string|max:20',
            'products' => 'required|array',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.product_sku_id' => 'required|exists:product_skus,id',
            'payment_type' => 'required|integer|in:1,2,3',
            'trxed' => 'nullable|string|max:255',

            // Guest must provide these
            'user_name' => 'nullable|string|max:255',
            'address'   => 'nullable|string|max:255',
            'userphone' => 'nullable|string|max:20',
        ]);
    }

    // Update product quantities
    private function updateProductQuantities($products)
    {
        $skuIds = collect($products)->pluck('product_sku_id')->unique()->values();

        $skus = ProductSku::whereIn('id', $skuIds)
            ->lockForUpdate() // prevents race condition for stock
            ->get()
            ->keyBy('id');

        foreach ($products as $product) {
            $sku = $skus->get($product['product_sku_id']);

            if (!$sku) {
                throw new \Exception('SKU not found: ' . $product['product_sku_id'], 404);
            }

            if ($sku->quantity < $product['quantity']) {
                throw new \Exception('Insufficient quantity for SKU: ' . $sku->sku, 409);
            }

            $sku->quantity -= $product['quantity'];
            $sku->save();
        }
    }


    // Generate Order Description
    private function generateOrderDescription($products)
    {
        $descriptionParts = [];

        $skuIds = collect($products)->pluck('product_sku_id')->unique()->values();

        // Load SKU -> Product -> Category + ParentCategory + SKU Attributes
        $skus = ProductSku::with([
            'product.parentCategory',
            'product.category',
            'skuAttributes.attribute',
            'skuAttributes.attributeValue',
        ])->whereIn('id', $skuIds)
            ->get()
            ->keyBy('id');

        foreach ($products as $productData) {
            $sku = $skus->get($productData['product_sku_id']);
            if (!$sku || !$sku->product) continue;

            $product = $sku->product;

            $parentCategoryName = $product->parentCategory?->name;
            $categoryName       = $product->category?->name;

            // attributes: "Size: XL, Color: Red"
            $attrs = $sku->skuAttributes
                ->map(function ($skuAttr) {
                    $attrName = $skuAttr->attribute?->name;
                    $attrVal  = $skuAttr->attributeValue?->name;

                    if (!$attrName || !$attrVal) return null;
                    return "{$attrName}: {$attrVal}";
                })
                ->filter()
                ->values()
                ->implode(', ');

            $attrsString = $attrs ? " ({$attrs})" : "";

            // category breadcrumb: "Men > Shirt"
            $categoryPath = collect([$parentCategoryName, $categoryName])
                ->filter()
                ->implode(' > ');

            $categoryPrefix = $categoryPath ? "{$categoryPath} | " : "";

            $skuCode = $sku->sku ? " [SKU: {$sku->sku}]" : "";

            $descriptionParts[] =
                "{$categoryPrefix}{$product->name}{$skuCode}{$attrsString} x {$productData['quantity']}";
        }

        // Separate items with "; "
        return implode('; ', $descriptionParts);
    }


    // Generate invoice code
    private function generateInvoiceCode()
    {
        $lastOrder = Order::latest()->first();
        return $lastOrder ? 'JG' . (intval(substr($lastOrder->invoice_code, 2)) + 1) : 'JG1000';
    }

    // Save order items
    private function saveOrderItems($order, $products)
    {
        $skuIds = collect($products)->pluck('product_sku_id')->unique()->values();

        $skus = ProductSku::with('product')
            ->whereIn('id', $skuIds)
            ->get()
            ->keyBy('id');

        foreach ($products as $product) {
            $sku = $skus->get($product['product_sku_id']);

            if (!$sku || !$sku->product) {
                throw new \Exception('Product/SKU not found for sku_id: ' . $product['product_sku_id'], 404);
            }

            $price = $sku->discount_price ?? $sku->price ?? $sku->product->base_price;

            Order_list::create([
                'order_id' => $order->id,
                'product_id' => $sku->product->id,
                'product_sku_id' => $sku->id,
                'quantity' => $product['quantity'],
                'price' => $price,
            ]);
        }
    }



    // Return validation error response
    private function validationErrorResponse($validator)
    {
        return response()->json([
            'success' => false,
            'status' => 422, // 422 is more appropriate for validation errors
            'message' => 'Validation failed.',
            'data' => null,
            'errors' => $validator->errors(),
        ], 422);
    }

    // Return success response
    private function successResponse($data, $message)
    {
        return response()->json([
            'success' => true,
            'status' => 201,
            'message' => $message,
            'data' => $data,
            'errors' => null,
        ], 201);
    }

    // Return error response
    private function errorResponse($errorMessage, $statusCode = 500)
    {
        return response()->json([
            'success' => false,
            'status' => $statusCode,
            'message' => 'Failed to place order.',
            'data' => null,
            'errors' => $errorMessage,
        ], $statusCode);
    }

    // Resend order emails
    public function sendOrderEmails(Request $request, $order_Id)
    {
        // Fetch the order by ID
        $order = Order::find($order_Id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Order not found',
                'data' => null,
                'errors' => 'Order not found',
            ], 404);
        }

        // Get additional email addresses from the request
        $additionalEmails = $request->input('emails', []); // Expecting an array of emails

        dispatch(new SendOrderEmailsJob($order, $additionalEmails));

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Emails are being sent in the background',
            'data' => null,
        ], 200);
    }

    // shwo all orders for admin page
    public function adminindex(Request $request)
    {
        try {
            $perPage = $request->input('limit');
            $currentPage = $request->input('page');
            $search = $request->input('search');
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            // Eager load 'user' and 'payment' relationships
            $query = Order::with(['user', 'payment'])->orderBy('created_at', 'desc');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('invoice_code', 'like', '%' . $search . '%')
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', '%' . $search . '%')
                                ->orWhere('phone', 'like', '%' . $search . '%')
                                ->orWhere('email', 'like', '%' . $search . '%');
                        });
                });
            }

            if ($startDate && $endDate) {
                $query->whereBetween('created_at', [
                    Carbon::parse($startDate)->startOfDay(),
                    Carbon::parse($endDate)->endOfDay()
                ]);
            }

            $orders = ($perPage && $currentPage)
                ? $query->paginate($perPage, ['*'], 'page', $currentPage)
                : $query->get();

            $formattedOrders = $orders->map(function ($order) {
                $payment = $order->payment; // Assume one-to-one relationship (adjust if multiple payments)

                $paidAmount = $payment?->paid_amount ?? 0;
                $totalAmount = $payment?->amount ?? $order->total_amount ?? 0;
                $dueAmount = $totalAmount - $paidAmount;

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
                ];
            });

            $statusCounts = Order::select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->get()
                ->pluck('total', 'status');

            $response = [
                'success' => true,
                'status' => 200,
                'message' => 'Orders fetched successfully.',
                'data' => $formattedOrders,
                'status_summary' => [
                    'processing' => $statusCounts[0] ?? 0,
                    'completed' => $statusCounts[1] ?? 0,
                    'on_hold' => $statusCounts[2] ?? 0,
                    'cancelled' => $statusCounts[3] ?? 0,
                    'refunded' => $statusCounts[4] ?? 0,
                ],
                'errors' => null,
            ];

            if ($perPage && $currentPage) {
                $response['pagination'] = [
                    'total' => $orders->total(),
                    'per_page' => $orders->perPage(),
                    'current_page' => $orders->currentPage(),
                    'last_page' => $orders->lastPage(),
                    'from' => $orders->firstItem(),
                    'to' => $orders->lastItem(),
                ];
            }

            return response()->json($response, 200);
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();

            if (str_contains($errorMessage, 'Integrity constraint violation')) {
                preg_match("/Duplicate entry '(.+?)' for key '(.+?)'/", $errorMessage, $matches);
                if (!empty($matches)) {
                    $errorMessage = "Duplicate entry '{$matches[1]}' for key '{$matches[2]}'";
                }
            }

            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to fetch orders.',
                'data' => null,
                'errors' => $errorMessage,
            ], 500);
        }
    }


    // shwo all orders for user
    public function userindex(Request $request)
    {
        try {
            $perPage     = $request->input('limit');
            $currentPage = $request->input('page');
            $search      = $request->input('search');

            // Get logged-in user ID from token
            $userId = Auth::id();

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'status'  => 401,
                    'message' => 'Unauthorized. Please login.',
                    'data'    => null,
                    'errors'  => 'No valid user token found.',
                ], 401);
            }

            // Load orders for logged in user + orderItems + product.images
            $query = Order::with(['user', 'orderItems.item.images'])
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc');


            if ($search) {
                $query->where('invoice_code', 'like', '%' . $search . '%');
            }

            if ($perPage && $currentPage) {
                $orders = $query->paginate($perPage, ['*'], 'page', $currentPage);
            } else {
                $orders = $query->get();
            }

            // Format data
            $formattedOrders = $orders->map(function ($order) {
                return [
                    'user_name'        => $order->user->name,
                    'user_phone'       => $order->user->phone,
                    'user_email'       => $order->user->email,
                    'order_id'         => $order->id,
                    'invoice_code'     => $order->invoice_code,
                    'status'           => $order->status,
                    'total_amount'     => $order->total_amount,
                    'order_placed_date' => $order->created_at->format('Y-m-d H:i:s'),
                    'totalProduct'      => $order->orderItems->sum('quantity'),

                ];
            });

            $response = [
                'success' => true,
                'status'  => 200,
                'message' => 'Orders fetched successfully.',
                'data'    => $formattedOrders,
                'errors'  => null,
            ];

            if ($perPage && $currentPage) {
                $response['pagination'] = [
                    'total'        => $orders->total(),
                    'per_page'     => $orders->perPage(),
                    'current_page' => $orders->currentPage(),
                    'last_page'    => $orders->lastPage(),
                    'from'         => $orders->firstItem(),
                    'to'           => $orders->lastItem(),
                ];
            }

            return response()->json($response, 200);
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();

            if (str_contains($errorMessage, 'Integrity constraint violation')) {
                preg_match("/Duplicate entry '(.+?)' for key '(.+?)'/", $errorMessage, $matches);
                if (!empty($matches)) {
                    $errorMessage = "Duplicate entry '{$matches[1]}' for key '{$matches[2]}'";
                }
            }

            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'Failed to fetch orders.',
                'data'    => null,
                'errors'  => $errorMessage,
            ], 500);
        }
    }


    // shwo single order
    public function show($orderId)
    {
        try {
            $order = Order::with([
                'user',
                'shippingAddress',
                'coupon',
                'payments',

                // order items
                'orderItems.item.images',

                // sku + sku attributes + attribute + attributeValue
                'orderItems.productSku.skuAttributes.attribute',
                'orderItems.productSku.skuAttributes.attributeValue',
            ])->find($orderId);

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Order not found',
                    'data' => null,
                    'errors' => 'The requested order does not exist.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Order fetched successfully.',
                'data' => $this->formatOrderResponse($order),
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to fetch order.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    private function formatOrderResponse($order)
    {
        return [
            'order' => [
                'order_id' => $order->id,
                'user_id' => $order->user_id ?? null,
                'shipping_id' => $order->shipping_id ?? null,
                'user_name' => $order->user_name ?? null,
                'user_phone' => $order->phone ?? null,
                'address' => $order->address ?? null,
                'invoice_code' => $order->invoice_code,
                'status' => $order->status,
                'status_change_desc' => $order->status_change_desc,
                'item_subtotal' => $order->item_subtotal,
                'shipping_charge' => $order->shipping_charge,
                'total_amount' => $order->total_amount,
                'discount' => $order->discount,
                'order_description' => $order->order_description,
                'created_at' => optional($order->created_at)->format('Y-m-d H:i:s'),
            ],

            'user' => $order->user ? [
                'user_id' => $order->user->id,
                'name' => $order->user->name,
                'email' => $order->user->email,
                'phone' => $order->user->phone,
                'address' => $order->user->address,
            ] : null,

            'shipping_address' => $order->shippingAddress ? [
                'shipping_id' => $order->shippingAddress->id,
                'f_name' => $order->shippingAddress->f_name,
                'l_name' => $order->shippingAddress->l_name,
                'phone' => $order->shippingAddress->phone,
                'address' => $order->shippingAddress->address,
                'city' => $order->shippingAddress->city,
                'zip' => $order->shippingAddress->zip,
            ] : null,

            'coupon' => $order->coupon ? [
                'code' => $order->coupon->code,
                'amount' => $order->coupon->amount,
            ] : null,

            'order_items' => $order->orderItems->map(function ($orderItem) {
                $product = $orderItem->item;
                $sku     = $orderItem->productSku;

                // Build attributes like: Size: XL, Color: Red
                $attributes = collect($sku?->skuAttributes ?? [])
                    ->map(function ($skuAttr) {
                        return [
                            'attribute_id' => $skuAttr->attribute_id,
                            'attribute_name' => $skuAttr->attribute?->name,
                            'attribute_value_id' => $skuAttr->attribute_value_id,
                            'attribute_value_name' => $skuAttr->attributeValue?->name,
                            'attribute_value_code' => $skuAttr->attributeValue?->code, // optional
                        ];
                    })
                    ->filter(fn($row) => !empty($row['attribute_name']) && !empty($row['attribute_value_name']))
                    ->values();

                // Optional grouped format: { "Size": ["XL"], "Color": ["Red"] }
                $attributes_grouped = $attributes
                    ->groupBy('attribute_name')
                    ->map(fn($rows) => $rows->pluck('attribute_value_name')->unique()->values())
                    ->toArray();

                // Optional text format: "Size: XL, Color: Red"
                $attributes_text = collect($attributes_grouped)
                    ->map(fn($values, $attrName) => $attrName . ': ' . collect($values)->join(', '))
                    ->values()
                    ->join(', ');

                return [
                    'product_id' => $product?->id,
                    'name' => $product?->name,
                    'slug' => $product?->slug,
                    'product_sku_id' => $orderItem->product_sku_id,
                    'quantity' => $orderItem->quantity,
                    'price' => $orderItem->price,

                    // product first image (your existing)
                    'image' => $product?->images?->first(),

                    //  attributes included
                    'attributes' => $attributes,
                    'attributes_grouped' => $attributes_grouped,
                    'attributes_text' => $attributes_text,
                ];
            })->values(),

            'payments' => $order->payments->map(function ($payment) {
                return [
                    'payment_id' => $payment->id,
                    'status' => $payment->status,
                    'amount' => $payment->amount,
                    'paid_amount' => $payment->paid_amount,
                    'payment_type' => $payment->payment_type,
                    'transaction_id' => $payment->trxed,
                    'phone' => $payment->phone,
                    'due_amount' => (float)$payment->amount - (float)$payment->paid_amount,
                ];
            })->values(),
        ];
    }


    // update order status
    public function updateStatus(Request $request, $orderId)
    {
        try {
            // Validate the request
            $request->validate([
                'status' => 'required|integer|in:0,1,2,3,4',
            ]);

            // Find the order
            $order = Order::findOrFail($orderId);

            // Get the current status and the new status
            $currentStatus = $order->status;
            $newStatus = $request->input('status');

            $statusLabels = [
                0 => 'On Process',
                1 => 'Complete',
                2 => 'On Hold',
                3 => 'Cancelled',
                4 => 'Refund',
            ];

            $currentStatusLabel = $statusLabels[$currentStatus] ?? $currentStatus;
            $newStatusLabel     = $statusLabels[$newStatus] ?? $newStatus;

            $statusChangeDesc = "Order Id {$order->invoice_code} Status changed from {$currentStatusLabel} to {$newStatusLabel} at " . now()->format('Y-m-d H:i:s');

            // Update the order status and status change description
            $order->update([
                'status' => $newStatus,
                'status_change_desc' => $statusChangeDesc,
            ]);

            // Save activity
            ActivityHelper::logActivity($orderId, 'Order', $statusChangeDesc);

            // Return success response
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Order status updated successfully.',
                'data' => [
                    'order_id' => $order->id,
                    'new_status' => $order->status,
                    'status_change_desc' => $order->status_change_desc,
                ],
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Handle exceptions and return error response
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to update order status.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // Remove products from oder
    public function removeProductFromOrder(Request $request, $orderId, $productId)
    {
        DB::beginTransaction();
        try {
            // Find the order
            $order = Order::findOrFail($orderId);

            // Find the product in the order list
            $orderItem = Order_list::where('order_id', $orderId)
                ->where('product_id', $productId)
                ->firstOrFail();

            // Calculate the amount to be deducted
            $amountToDeduct = $orderItem->quantity * $orderItem->price;

            // Remove the product from the order list
            $orderItem->delete();

            // Update the total_amount in the order table
            $order->total_amount -= $amountToDeduct;
            $order->save();

            // Update the amount in the payment table
            $payment = Payment::where('order_id', $orderId)->first();
            if ($payment) {
                $payment->amount -= $amountToDeduct;
                $payment->save();
            }

            $product = Product::find($productId);

            $activityDesc = "Removed product from Order ID: {$orderId}, Product: {$product->name}, Quantity: {$orderItem->quantity}, Price: {$orderItem->price}, ";
            $activityDesc .= "Amount Deducted: {$amountToDeduct}, New Order Total: {$order->total_amount}, Updated at - " . now()->toDateTimeString();

            // Use helper instead of direct create
            ActivityHelper::logActivity(
                $orderId,
                'order',
                $activityDesc
            );


            // Commit the transaction
            DB::commit();

            // Return success response
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Product removed from order successfully.',
                'data' => [
                    'order_id' => $order->id,
                    'new_total_amount' => $order->total_amount,
                    'payment_amount' => $payment ? $payment->amount : null,
                ],
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Rollback the transaction in case of error
            DB::rollBack();

            // Handle exceptions and return error response
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to remove product from order.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // update product quantity
    public function updateProductQuantity(Request $request, $orderId, $productId)
    {
        DB::beginTransaction();
        try {
            // Validate the request
            $request->validate([
                'quantity' => 'required|integer|min:1',
                'price' => 'nullable|numeric|min:0',
            ]);

            // Find the order
            $order = Order::find($orderId);
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Order not found.',
                    'data' => null,
                    'errors' => 'No query results for model [App\Models\Order] ' . $orderId,
                ], 404);
            }

            // Find the product in the order list
            $orderItem = Order_list::where('order_id', $orderId)
                ->where('product_id', $productId)
                ->first();

            if (!$orderItem) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Product not found in the order.',
                    'data' => null,
                    'errors' => 'No query results for model [App\Models\Order_list] with product_id ' . $productId,
                ], 404);
            }

            // Calculate the old total for the product
            $oldTotal = $orderItem->quantity * $orderItem->price;

            // Update the product quantity and price (if provided)
            $orderItem->quantity = $request->input('quantity');
            if ($request->has('price')) {
                $orderItem->price = $request->input('price');
            }
            $orderItem->save();

            // Calculate the new total for the product
            $newTotal = $orderItem->quantity * $orderItem->price;

            // Calculate the difference in total amount
            $amountDifference = $newTotal - $oldTotal;

            // Update the total_amount in the order table
            $order->total_amount += $amountDifference;
            $order->save();

            // Update the amount in the payment table
            $payment = Payment::where('order_id', $orderId)->first();
            if ($payment) {
                $payment->amount += $amountDifference;
                $payment->save();
            }

            $product = Product::find($productId);

            $activityDesc = "Updated product in Order ID: {$orderId}, Product: {$product->name}, ";
            $activityDesc .= "Quantity: {$orderItem->quantity}, Price: {$orderItem->price}, ";
            $activityDesc .= "Old Total: {$oldTotal}, New Total: {$newTotal}, ";
            $activityDesc .= "Amount Difference: {$amountDifference}, ";
            $activityDesc .= "Order Total: {$order->total_amount}, ";
            $activityDesc .= "Payment Amount: " . ($payment ? $payment->amount : 'N/A') . ", ";
            $activityDesc .= "Updated at - " . now()->toDateTimeString();

            // Use helper instead of direct create
            ActivityHelper::logActivity(
                $orderId,
                'order',
                $activityDesc
            );

            // Commit the transaction
            DB::commit();

            // Return success response
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Product quantity and price updated successfully.',
                'data' => [
                    'order_id' => $order->id,
                    'product_id' => $productId,
                    'new_quantity' => $orderItem->quantity,
                    'new_price' => $orderItem->price,
                    'new_total_amount' => $order->total_amount,
                    'payment_amount' => $payment ? $payment->amount : null,
                ],
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Rollback the transaction in case of error
            DB::rollBack();
            // Extract only the main error message
            $errorMessage = $e->getMessage();

            // Check if it's a SQL Integrity Constraint Violation
            if (str_contains($errorMessage, 'Integrity constraint violation')) {
                preg_match("/Duplicate entry '(.+?)' for key '(.+?)'/", $errorMessage, $matches);
                if (!empty($matches)) {
                    $errorMessage = "Duplicate entry '{$matches[1]}' for key '{$matches[2]}'";
                }
            }
            // Handle exceptions and return error response
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to update product quantity and price.',
                'data' => null,
                'errors' => $errorMessage
            ], 500);
        }
    }

    public function addProductToOrder(Request $request, $orderId)
    {
        DB::beginTransaction();

        try {
            // validate request (same style as placeOrder)
            $validator = Validator::make($request->all(), [
                'product_id'     => 'required|exists:products,id',
                'product_sku_id' => 'required|exists:product_skus,id',
                'quantity'       => 'required|integer|min:1',
                'price'          => 'nullable|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'status' => 422,
                    'message' => 'Validation failed.',
                    'data' => null,
                    'errors' => $validator->errors(),
                ], 422);
            }

            // find order
            $order = Order::lockForUpdate()->find($orderId);
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Order not found.',
                    'data' => null,
                    'errors' => 'No query results for model [App\Models\Order] ' . $orderId,
                ], 404);
            }

            //  load sku and verify it belongs to product
            $sku = ProductSku::with([
                'product.parentCategory',
                'product.category',
                'skuAttributes.attribute',
                'skuAttributes.attributeValue',
            ])
                ->lockForUpdate()
                ->find($request->product_sku_id);

            if (!$sku) {
                throw new \Exception('SKU not found.', 404);
            }

            if ((int)$sku->product_id !== (int)$request->product_id) {
                return response()->json([
                    'success' => false,
                    'status' => 400,
                    'message' => 'SKU does not belong to this product.',
                    'data' => null,
                    'errors' => [
                        'product_id' => $request->product_id,
                        'product_sku_id' => $request->product_sku_id,
                    ],
                ], 400);
            }

            $product = $sku->product;
            if (!$product) {
                throw new \Exception('Product not found for this SKU.', 404);
            }

            $qtyToAdd = (int)$request->quantity;

            //  stock check and update SKU quantity
            if ((int)$sku->quantity < $qtyToAdd) {
                return response()->json([
                    'success' => false,
                    'status' => 409,
                    'message' => 'Insufficient stock for this SKU.',
                    'data' => null,
                    'errors' => [
                        'sku' => $sku->sku,
                        'available' => (int)$sku->quantity,
                        'requested' => $qtyToAdd,
                    ],
                ], 409);
            }

            $sku->quantity -= $qtyToAdd;
            $sku->save();

            // choose price: request price OR sku discount_price OR sku price OR product base_price
            $unitPrice = $request->input('price');
            if ($unitPrice === null) {
                $unitPrice = $sku->discount_price ?? $sku->price ?? $product->base_price;
            }
            $unitPrice = (float)$unitPrice;

            //  check if order already has same product + same sku
            $orderItem = Order_list::where('order_id', $orderId)
                ->where('product_id', $product->id)
                ->where('product_sku_id', $sku->id)
                ->first();

            if ($orderItem) {
                $oldLineTotal = (float)$orderItem->price * (int)$orderItem->quantity;

                $orderItem->quantity = (int)$orderItem->quantity + $qtyToAdd;
                $orderItem->price    = $unitPrice; // keep latest unit price
                $orderItem->save();

                $newLineTotal = (float)$orderItem->price * (int)$orderItem->quantity;
            } else {
                $oldLineTotal = 0;

                $orderItem = Order_list::create([
                    'order_id'        => $orderId,
                    'product_id'      => $product->id,
                    'product_sku_id'  => $sku->id,
                    'quantity'        => $qtyToAdd,
                    'price'           => $unitPrice,
                ]);

                $newLineTotal = (float)$orderItem->price * (int)$orderItem->quantity;
            }

            //  difference to apply to order totals
            $amountDifference = $newLineTotal - $oldLineTotal;

            //  update order item_subtotal + total_amount
            $order->item_subtotal = (float)$order->item_subtotal + $amountDifference;
            $order->total_amount  = (float)$order->total_amount + $amountDifference;
            $order->save();

            // update payment amount
            $payment = Payment::where('order_id', $orderId)->first();
            if ($payment) {
                $payment->amount = (float)$payment->amount + $amountDifference;
                $payment->save();
            }

            // regenerate order_description based on ALL order items (with sku + attrs + categories)
            $orderItemsForDesc = Order_list::where('order_id', $orderId)
                ->get(['product_id', 'product_sku_id', 'quantity'])
                ->map(function ($row) {
                    return [
                        'product_id' => $row->product_id,
                        'product_sku_id' => $row->product_sku_id,
                        'quantity' => $row->quantity,
                    ];
                })
                ->toArray();

            $order->order_description = $this->generateOrderDescription($orderItemsForDesc);
            $order->save();

            //  activity log
            $actionType = $oldLineTotal > 0 ? 'Updated existing product SKU' : 'Added new product SKU';

            $activityDesc  = "{$actionType} in Order ID: {$orderId}, ";
            $activityDesc .= "Product: {$product->name}, SKU: {$sku->sku}, ";
            $activityDesc .= "Qty Added: {$qtyToAdd}, Unit Price: {$unitPrice}, ";
            $activityDesc .= "Amount Change: {$amountDifference}, Updated at - " . now()->toDateTimeString();

            ActivityHelper::logActivity($orderId, 'order', $activityDesc);

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Product added to order successfully.',
                'data' => [
                    'order_id' => $order->id,
                    'order_item_id' => $orderItem->id,

                    'product_id' => $product->id,
                    'product_sku_id' => $sku->id,
                    'product_sku' => $sku->sku,

                    'quantity' => (int)$orderItem->quantity,
                    'unit_price' => (float)$orderItem->price,
                    'line_total' => (float)$orderItem->price * (int)$orderItem->quantity,

                    'amount_difference' => $amountDifference,

                    'new_item_subtotal' => (float)$order->item_subtotal,
                    'new_total_amount'  => (float)$order->total_amount,
                    'payment_amount'    => $payment ? (float)$payment->amount : null,

                    'order_description' => $order->order_description,
                ],
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            $statusCode = ($e->getCode() >= 400 && $e->getCode() < 500) ? $e->getCode() : 500;

            return response()->json([
                'success' => false,
                'status' => $statusCode,
                'message' => 'Failed to add product to order.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], $statusCode);
        }
    }


    // updaet customer address
    public function updateCustomerInfo(Request $request, $order_Id)
    {
        $request->validate([
            'user_name' => 'nullable|string',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
        ]);

        try {
            $order = Order::findOrFail($order_Id);

            // Ensure it's a guest order before updating
            if ($order->user_id !== null || $order->shipping_id !== null) {
                return response()->json([
                    'success' => false,
                    'message' => 'This order belongs to a registered user and cannot be updated this way.',
                ], 400);
            }

            $order->update([
                'user_name' => $request->input('user_name', $order->user_name),
                'user_phone' => $request->input('phone', $order->user_phone),
                'address' => $request->input('address', $order->address),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order customer info updated successfully!',
                'data' => $order
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong!',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function userOrderDetaileShow($invoiceCode)
    {
        try {
            $userId = Auth::id();

            $order = Order::with([
                'shippingAddress',
                'coupon',
                'payments',

                // order items
                'orderItems.item.parentCategory',
                'orderItems.item.category',
                'orderItems.item.primaryImage',
                'orderItems.item.images',

                // sku + attributes
                'orderItems.productSku.skuAttributes.attribute',
                'orderItems.productSku.skuAttributes.attributeValue',
            ])
                ->where('invoice_code', $invoiceCode)
                ->where('user_id', $userId)
                ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Order not found for this user',
                    'data' => null,
                ], 404);
            }

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Order fetched successfully.',
                'data' => $this->formatUserOrderResponse($order),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to fetch order.',
                'errors' => $e->getMessage(),
            ], 500);
        }
    }


    private function formatUserOrderResponse($order)
    {
        return [
            'order_id'        => $order->id,
            'user_id'         => $order->user_id,
            'shipping_id'     => $order->shipping_id,
            'invoice_code'    => $order->invoice_code,
            'status'          => $order->status,
            'item_subtotal'   => $order->item_subtotal,
            'shipping_charge' => $order->shipping_charge,
            'total_amount'    => $order->total_amount,
            'discount'        => $order->discount,
            'created_at'      => optional($order->created_at)->format('Y-m-d H:i:s'),

            'shipping_address' => $order->shippingAddress ? [
                'shipping_id' => $order->shippingAddress->id,
                'f_name'      => $order->shippingAddress->f_name,
                'l_name'      => $order->shippingAddress->l_name,
                'phone'       => $order->shippingAddress->phone,
                'address'     => $order->shippingAddress->address,
                'city'        => $order->shippingAddress->city,
                'zip'         => $order->shippingAddress->zip,
            ] : null,

            'coupon' => $order->coupon ? [
                'code'   => $order->coupon->code,
                'amount' => $order->coupon->amount,
            ] : null,

            'order_items' => $order->orderItems->map(function ($orderItem) {
                $product = $orderItem->item;
                $sku     = $orderItem->productSku;

                // attributes: Size: XL, Color: Red
                $attributes = collect($sku?->skuAttributes ?? [])
                    ->map(function ($skuAttr) {
                        return [
                            'attribute_name' => $skuAttr->attribute?->name,
                            'attribute_value' => $skuAttr->attributeValue?->name,
                        ];
                    })
                    ->filter(fn($a) => $a['attribute_name'] && $a['attribute_value'])
                    ->values();

                // grouped attributes
                $attributes_grouped = $attributes
                    ->groupBy('attribute_name')
                    ->map(fn($rows) => $rows->pluck('attribute_value')->unique()->values())
                    ->toArray();

                // attributes text
                $attributes_text = collect($attributes_grouped)
                    ->map(fn($values, $name) => "{$name}: " . collect($values)->join(', '))
                    ->values()
                    ->join(', ');

                // image priority: primary > first image
                $image = $product?->primaryImage?->image_url
                    ?? $product?->images?->first()?->image_url;

                return [
                    'product_id'   => $product?->id,
                    'product_name' => $product?->name,
                    'slug'         => $product?->slug,

                    'parent_category' => $product?->parentCategory?->name,
                    'category'        => $product?->category?->name,

                    'product_sku_id' => $sku?->id,
                    'product_sku'    => $sku?->sku,

                    'quantity'     => $orderItem->quantity,
                    'price'        => $orderItem->price,

                    // attributes
                    'attributes'        => $attributes,
                    'attributes_grouped' => $attributes_grouped,
                    'attributes_text'   => $attributes_text,

                    'image' => $image,
                ];
            })->values(),

            'payments' => $order->payments->map(function ($payment) {
                return [
                    'status'       => $payment->status,
                    'amount'       => $payment->amount,
                    'paid_amount'  => $payment->paid_amount,
                    'payment_type' => $payment->payment_type,
                    'phone'        => $payment->phone,
                    'due_amount'   => (float)$payment->amount - (float)$payment->paid_amount,
                ];
            })->values(),
        ];
    }

    public function deleteOrder($orderId)
    {
        DB::beginTransaction();

        try {
            $order = Order::with(['orderItems', 'payments'])->find($orderId);

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Order not found',
                    'data' => null,
                    'errors' => null,
                ], 404);
            }

            // If you want to prevent deleting completed orders, uncomment:
            // if ((int)$order->status === 4) {
            //     return response()->json([
            //         'success' => false,
            //         'status' => 403,
            //         'message' => 'Completed order cannot be deleted',
            //         'data' => null,
            //         'errors' => null,
            //     ], 403);
            // }

            //  Option A: If you added booted() deleting() in Order model
            $order->delete();

            //  Option B (if you did NOT add booted()): manual delete
            // $order->orderItems()->delete();
            // $order->payments()->delete();
            // $order->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Order deleted successfully',
                'data' => [
                    'order_id' => $orderId,
                    'invoice_code' => $order->invoice_code,
                ],
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to delete order',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }
}

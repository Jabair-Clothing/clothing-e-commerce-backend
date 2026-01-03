<?php

namespace App\Http\Controllers\Coupon;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Coupon;
use App\Models\Activity;
use App\Models\Coupon_Product;
use Illuminate\Validation\Rule;
use App\Models\Order;
use Illuminate\Support\Carbon;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use App\Helpers\ActivityHelper;

class CouponController extends Controller
{
    // Method to store a coupon
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:coupons,code',
            'amount' => 'required|numeric|min:0',
            'type' => 'required|in:flat,percent',
            'is_global' => 'required|boolean',
            'max_usage' => 'nullable|integer|min:1',
            'min_pur' => 'nullable|integer',
            'max_usage_per_user' => 'nullable|integer|min:1',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'product_ids' => 'sometimes|array',
            'product_ids.*' => 'exists:products,id',
            'status' => 'nullable|integer',
        ]);

        try {
            DB::beginTransaction();

            $coupon = Coupon::create($validated);

            // Attach products if not global
            if (!$validated['is_global'] && !empty($validated['product_ids'])) {
                $coupon->products()->attach($validated['product_ids']);
            }

            DB::commit();

            // Prepare activity description
            $productList = !$validated['is_global'] && !empty($validated['product_ids'])
                ? implode(', ', $validated['product_ids'])
                : 'All products (Global Coupon)';

            $activityDesc = "Created Coupon: Code - {$coupon->code}, Amount - {$coupon->amount} ({$coupon->type}), ";
            $activityDesc .= "Scope - " . ($coupon->is_global ? 'Global' : 'Specific Products') . ", Products - {$productList}, ";
            $activityDesc .= "Valid: " . ($coupon->start_date ?? 'N/A') . " to " . ($coupon->end_date ?? 'N/A') . ", ";
            $activityDesc .= "Date - " . now()->toDateTimeString();

            ActivityHelper::logActivity($coupon->id, 'Coupon', $activityDesc);

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Coupon created successfully',
                'data' => null
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to create coupon.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    // Method to update a coupon
    public function update(Request $request, $id)
    {
        try {
            $coupon = Coupon::findOrFail($id);

            $validated = $request->validate([
                'code' => [
                    'required',
                    'string',
                    Rule::unique('coupons', 'code')->ignore($coupon->id),
                ],
                'amount' => [
                    'required',
                    'numeric',
                    'min:0',
                    Rule::when($request->input('type') === 'percent', ['max:100']),
                ],
                'type' => 'required|in:flat,percent',
                'is_global' => 'required|boolean',
                'min_pur' => 'nullable|integer',
                'max_usage' => 'nullable|integer|min:1',
                'max_usage_per_user' => 'nullable|integer|min:1',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'product_ids' => 'sometimes|array',
                'product_ids.*' => 'exists:products,id',
                'status' => 'nullable|integer',
            ]);

            DB::beginTransaction();

            // Store old product IDs for activity logging
            $oldProductIds = DB::table('coupon__products')
                ->where('coupon_id', $coupon->id)
                ->pluck('item_id')
                ->toArray();

            // Update coupon basic data
            $coupon->update($validated);

            // Handle product associations in coupon__products table
            if ($validated['is_global']) {
                // If coupon is now global, delete all records from coupon__products
                DB::table('coupon__products')
                    ->where('coupon_id', $coupon->id)
                    ->delete();
            } else {
                // If coupon is not global and product_ids are provided
                if (isset($validated['product_ids'])) {
                    // sync() will update the coupon__products pivot table
                    $coupon->products()->sync($validated['product_ids']);
                }
            }

            // Get updated product IDs
            $newProductIds = DB::table('coupon__products')
                ->where('coupon_id', $coupon->id)
                ->pluck('item_id')
                ->toArray();

            // Calculate changes
            $addedProducts = array_diff($newProductIds, $oldProductIds);
            $removedProducts = array_diff($oldProductIds, $newProductIds);

            DB::commit();

            // Prepare activity description
            $productList = !$validated['is_global'] && count($newProductIds) > 0
                ? implode(', ', $newProductIds)
                : 'All products (Global Coupon)';

            $activityDesc = "Updated Coupon: Code - {$coupon->code}, Amount - {$coupon->amount} ({$coupon->type}), ";
            $activityDesc .= "Scope - " . ($coupon->is_global ? 'Global' : 'Specific Products') . ", Products - {$productList}, ";

            if (!empty($addedProducts)) {
                $activityDesc .= "Added Products: " . implode(', ', $addedProducts) . ", ";
            }
            if (!empty($removedProducts)) {
                $activityDesc .= "Removed Products: " . implode(', ', $removedProducts) . ", ";
            }

            $activityDesc .= "Valid: " . ($coupon->start_date ?? 'N/A') . " to " . ($coupon->end_date ?? 'N/A') . ", ";
            $activityDesc .= "Date - " . now()->toDateTimeString();

            ActivityHelper::logActivity($coupon->id, 'Coupon', $activityDesc);

            // Reload the relationship
            $coupon->load('products');

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Coupon updated successfully.',
                'data' => [
                    'coupon' => $coupon,
                    'changes' => [
                        'products_added' => array_values($addedProducts),
                        'products_removed' => array_values($removedProducts),
                    ]
                ]
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Coupon not found.',
                'data' => null,
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to update coupon.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Method to delete a coupon
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $coupon = Coupon::findOrFail($id);

            // Get item IDs before deletion (if not global)
            $itemIds = Coupon_Product::where('coupon_id', $coupon->id)->pluck('item_id')->toArray();
            $itemList = $coupon->is_global ? 'Global Coupon (All items)' : implode(', ', $itemIds);

            // Delete related entries from pivot table
            Coupon_Product::where('coupon_id', $coupon->id)->delete();

            // Delete the coupon
            $coupon->delete();

            DB::commit();

            // Prepare activity description
            $activityDesc = "Deleted Coupon: Code - {$coupon->code}, Type - {$coupon->type}, Amount - {$coupon->amount}, ";
            $activityDesc .= "Scope - " . ($coupon->is_global ? 'Global' : 'Specific Items') . ", Items - {$itemList}, ";
            $activityDesc .= "Deleted at - " . now()->toDateTimeString();

            // Save activity
            ActivityHelper::logActivity($id, 'Coupon', $activityDesc);

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Coupon and related products deleted successfully.',
                'data' => null,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Coupon not found.',
                'data' => null,
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to delete coupon.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    // Method to fetch all coupons
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('limit');
            $currentPage = $request->input('page');
            $search = $request->input('search');

            $couponsQuery = Coupon::with(['products:id,name']) // Changed from 'items' to 'products'
                ->withCount(['orders as total_orders' => function ($q) {
                    $q->where('status', 1); // only completed orders
                }])
                ->withSum(['orders as total_sales' => function ($q) {
                    $q->where('status', 1);
                }], 'total_amount') // sum only "total_amount"
                ->orderBy('created_at', 'desc');

            if ($search) {
                $couponsQuery->where('code', 'like', '%' . $search . '%');
            }

            if ($perPage && $currentPage) {
                $coupons = $couponsQuery->paginate($perPage, ['*'], 'page', $currentPage);

                return response()->json([
                    'success' => true,
                    'status' => 200,
                    'message' => 'Coupons retrieved successfully.',
                    'data' => $coupons->items(),
                    'pagination' => [
                        'total_rows' => $coupons->total(),
                        'current_page' => $coupons->currentPage(),
                        'per_page' => $coupons->perPage(),
                        'total_pages' => $coupons->lastPage(),
                        'has_more_pages' => $coupons->hasMorePages(),
                    ],
                    'errors' => null,
                ], 200);
            }

            $coupons = $couponsQuery->get();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Coupons retrieved successfully.',
                'data' => $coupons,
                'pagination' => null,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to retrieve coupons.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }


    // toggle status of coupons
    public function toggleStatus($id)
    {
        try {
            $coupon = Coupon::findOrFail($id);

            // Toggle the status
            $coupon->status = $coupon->status == 1 ? 0 : 1;
            $coupon->save();

            // Determine readable status
            $statusLabel = $coupon->status == 1 ? 'Active' : 'Inactive';

            // Prepare activity description
            $activityDesc = "Toggled Coupon Status: Code - {$coupon->code}, Status - {$statusLabel}, Date - " . now()->toDateTimeString();

            // Save activity
            ActivityHelper::logActivity($coupon->id, 'Coupon', $activityDesc);

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Coupon status updated successfully.',
                'data' => [
                    'id' => $coupon->id,
                    'status' => $coupon->status
                ]
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Coupon not found.',
                'data' => null,
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to update coupon status.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    public function addItems(Request $request, $id)
    {
        $request->validate([
            'item_ids' => 'required|array',
            'item_ids.*' => 'exists:products,id',
        ]);

        try {
            $coupon = Coupon::findOrFail($id);

            DB::beginTransaction();

            foreach ($request->item_ids as $itemId) {
                // Avoid duplicates
                Coupon_Product::firstOrCreate([
                    'coupon_id' => $coupon->id,
                    'item_id' => $itemId,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Items added to coupon successfully.',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to add items.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function removeItem(Request $request, $id)
    {
        $request->validate([
            'item_id' => 'required|exists:products,id',
        ]);

        try {
            $coupon = Coupon::findOrFail($id);

            DB::beginTransaction();

            Coupon_Product::where('coupon_id', $coupon->id)
                ->where('item_id', $request->item_id)
                ->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Item removed from coupon successfully.',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to remove item.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check coupon validity and calculate discount
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkCoupon(Request $request)
    {
        try {
            $request->validate([
                'coupon_code'   => 'required|string',
                'total_amount'  => 'required|numeric|min:0',
                'user_id'       => 'nullable|integer',
                'products'      => 'nullable|array',
                'products.*.product_id' => 'integer',
                'products.*.quantity'   => 'integer|min:1',
            ]);

            $coupon = Coupon::with('items')->where('code', $request->coupon_code)->first();

            if (!$coupon) {
                throw new \Exception('Invalid coupon code.', 404);
            }

            if ($coupon->status != 1) { // Changed to 1 assuming 1 is active
                throw new \Exception('This coupon is not active.', 400);
            }

            $now = Carbon::now();
            if ($coupon->start_date && $now->isBefore(Carbon::parse($coupon->start_date))) {
                throw new \Exception('This coupon is not yet valid.', 400);
            }
            if ($coupon->end_date && $now->isAfter(Carbon::parse($coupon->end_date))) {
                throw new \Exception('This coupon has expired.', 400);
            }

            // Usage checks
            $totalUsage = Order::where('coupons_id', $coupon->id)->count();
            if ($coupon->max_usage && $totalUsage >= $coupon->max_usage) {
                throw new \Exception('Coupon has reached maximum usage limit.', 400);
            }

            if ($request->user_id && $coupon->max_usage_per_user) {
                $userUsage = Order::where('coupons_id', $coupon->id)
                    ->where('user_id', $request->user_id)
                    ->count();
                if ($userUsage >= $coupon->max_usage_per_user) {
                    throw new \Exception('You have already used this coupon the maximum number of times.', 400);
                }
            }

            // Eligible amount
            $eligibleAmount = 0;
            if ($coupon->is_global) {
                $eligibleAmount = $request->total_amount;
            } else {
                $couponProductIds = $coupon->items->pluck('id')->toArray();
                $cartProducts = collect($request->products ?? []);
                $productIdsInCart = $cartProducts->pluck('product_id');
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

            if ($coupon->min_pur && $eligibleAmount < $coupon->min_pur) {
                throw new \Exception("Minimum purchase of {$coupon->min_pur} is required for this coupon.", 400);
            }

            // Calculate discount
            $discount = 0;
            if ($coupon->type === 'percent') {
                $discount = ($eligibleAmount * $coupon->amount) / 100;
            } elseif ($coupon->type === 'flat') {
                $discount = $coupon->amount;
            }
            $discount = min($discount, $eligibleAmount);

            return response()->json([
                'success' => true,
                'message' => 'Coupon is valid!',
                'data' => [
                    'coupon_id'   => $coupon->id,
                    'discount' => round($discount, 2),
                    'final_total' => round($request->total_amount - $discount, 2)
                ]
            ]);
        } catch (\Exception $e) {
            $status = $e->getCode();
            if (!is_int($status) || $status < 100 || $status > 599) {
                $status = 400; // Default to 400 Bad Request for robustness
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [
                    'coupon_id'   => isset($coupon) ? $coupon->id : null,
                    'discount' => 0,
                    'final_total' => $request->total_amount ?? 0
                ]
            ], $status);
        }
    }
}

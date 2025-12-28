<?php

namespace App\Http\Controllers\BundleItem;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BundleItem;
use Illuminate\Support\Facades\Validator;
use App\Models\Item;

class BundleItemController extends Controller
{
    //Add new items in bundle
    public function addItemsToBundle(Request $request, $bundleId)
    {
        try {
            // Validate the request data
            $validator = Validator::make($request->all(), [
                'items' => 'required|array',
                'items.*.item_id' => 'required|integer|exists:items,id',
                'items.*.bundle_quantity' => 'nullable|integer|min:1',
            ]);

            // If validation fails, return error response
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'status' => 422,
                    'message' => 'Validation failed.',
                    'data' => null,
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Check if the bundle exists and is marked as a bundle
            $bundle = Item::find($bundleId);
            if (!$bundle || $bundle->is_bundle != 1) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Bundle not found or invalid bundle ID.',
                    'data' => null,
                    'errors' => 'Invalid bundle ID or the item is not a bundle.',
                ], 404);
            }

            // Add items to the bundle
            $addedItems = [];
            foreach ($request->items as $item) {
                $itemId = $item['item_id'];
                $bundleQuantity = $item['bundle_quantity'] ?? 1;

                // Check if the item is already part of the bundle
                $existingBundleItem = BundleItem::where('bundle_item_id', $bundleId)
                    ->where('item_id', $itemId)
                    ->first();

                if (!$existingBundleItem) {
                    BundleItem::create([
                        'bundle_item_id' => $bundleId,
                        'item_id' => $itemId,
                        'bundle_quantity' => $bundleQuantity,
                    ]);

                    $addedItems[] = [
                        'item_id' => $itemId,
                        'bundle_quantity' => $bundleQuantity,
                    ];
                }
            }

            // Return success response
            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Items added to the bundle successfully.',
                'data' => [
                    'bundle_id' => $bundleId,
                    'added_items' => $addedItems,
                ],
                'errors' => null,
            ], 201);
        } catch (\Exception $e) {
            // Handle any exceptions
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while adding items to the bundle.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // bundle quantity changes
    public function updateQuantity(Request $request, $bundle_id)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer',
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

        // Find the bundle item
        $bundleItem = BundleItem::find($bundle_id);

        if (!$bundleItem) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Bundle item not found.',
                'data' => null,
                'errors' => null,
            ], 404);
        }

        // Update the quantity
        $bundleItem->bundle_quantity = $request->quantity;
        $bundleItem->save();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Quantity updated successfully.',
            'data' => $bundleItem,
            'errors' => null,
        ], 200);
    }

    // toggle bundles status
    public function toggleBundle(Request $request, $item_id)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'is_bundle' => 'required|boolean',
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

        // Find item
        $item = Item::find($item_id);

        if (!$item) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Item not found.',
                'data' => null,
                'errors' => null,
            ], 404);
        }

        // Toggle is_bundle
        $item->is_bundle = $request->is_bundle;
        $item->save();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Bundle status updated successfully.',
            'data' => $item,
            'errors' => null,
        ], 200);
    }

    // delete bundle product
    public function deleteBundle($bundle_id)
    {
        // Find the bundle item
        $bundleItem = BundleItem::find($bundle_id);

        if (!$bundleItem) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Bundle item not found.',
                'data' => null,
                'errors' => null,
            ], 404);
        }

        // Delete the bundle item
        $bundleItem->delete();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Bundle item deleted successfully.',
            'data' => null,
            'errors' => null,
        ], 200);
    }
}

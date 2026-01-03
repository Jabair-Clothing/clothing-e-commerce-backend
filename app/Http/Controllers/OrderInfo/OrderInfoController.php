<?php

namespace App\Http\Controllers\OrderInfo;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OrderInfo;
use Illuminate\Support\Facades\Validator;

class OrderInfoController extends Controller
{
    /**
     * Get the Order Info (Singleton)
     */
    public function index()
    {
        try {
            // Get the first record or create a default one if it doesn't exist
            $orderInfo = OrderInfo::first();

            if (!$orderInfo) {
                // Optional: return empty or create default
                // For now, let's return null or empty object if not found, or create default
                return response()->json([
                    'success' => true,
                    'status' => 200,
                    'message' => 'Order Info retrieved successfully.',
                    'data' => null,
                ], 200);
            }

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Order Info retrieved successfully.',
                'data' => $orderInfo,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to retrieve Order Info.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the Order Info
     */
    public function update(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'inside_dhaka' => 'nullable|numeric',
                'outside_dhaka' => 'nullable|numeric',
                'vat' => 'nullable|numeric',
                'bkash_changed' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'status' => 422,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Update or Create the first record
            $orderInfo = OrderInfo::first();

            if ($orderInfo) {
                $orderInfo->update($request->all());
            } else {
                $orderInfo = OrderInfo::create($request->all());
            }

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Order Info updated successfully.',
                'data' => $orderInfo,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to update Order Info.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers\Document;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Document;
use App\Models\OrderInfo;
use App\Models\Activity;
use Illuminate\Support\Facades\Auth;

class DocumentController extends Controller
{
    //shwo about
    public function showAbout()
    {
        try {
            // Fetch documents where type is 'about'
            $aboutDocuments = Document::where('type', 'about')->get();

            // Return the response in the specified format
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'About documents retrieved successfully.',
                'data' => $aboutDocuments,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Handle errors and return a consistent error response
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to retrieve about documents.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // show tram and conditions
    public function showTrueCondition()
    {
        try {
            $Documents = Document::where('type', 'terms&conditions')->get();

            // Return the response in the specified format
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'About documents retrieved successfully.',
                'data' => $Documents,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Handle errors and return a consistent error response
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to retrieve about documents.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // show privacy policy
    public function showPrivacyPolicy()
    {
        try {
            $Documents = Document::where('type', 'privacy&policy')->get();

            // Return the response in the specified format
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'About documents retrieved successfully.',
                'data' => $Documents,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Handle errors and return a consistent error response
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to retrieve about documents.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // show return policy
    public function showReturnPolicy()
    {
        try {
            $Documents = Document::where('type', 'return&policy')->get();

            // Return the response in the specified format
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'About documents retrieved successfully.',
                'data' => $Documents,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Handle errors and return a consistent error response
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to retrieve about documents.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // update all documents
    public function updateByType(Request $request, $type)
    {
        try {
            $request->validate([
                'text' => 'required|string',
            ]);

            // Find by type the type is :about,terms&conditions,privacy&policy,return&policy
            $document = Document::where('type', $type)->first();

            if (!$document) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Document not found for type: ' . $type,
                    'data' => null,
                    'errors' => null,
                ], 404);
            }

            $document->text = $request->text;
            $document->save();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => ucfirst($type) . ' updated successfully.',
                'data' => $document,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to update document.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }


    public function showOrderInfo()
    {
        try {
            $orderInfo = OrderInfo::first();

            if (!$orderInfo) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'OrderInfo data not found.',
                    'data' => null,
                    'errors' => null,
                ], 404);
            }

            // Transform data
            $formattedData = [
                'id' => (int) $orderInfo->id,
                'insideDhaka' => (int) $orderInfo->inside_dhaka,
                'outsideDhaka' => (int) $orderInfo->outside_dhaka,
                'vat' => (int) $orderInfo->vat,
                'bkashChangedParsentage' => (float) $orderInfo->bkash_changed,
                'created_at' => $orderInfo->created_at,
                'updated_at' => $orderInfo->updated_at,
            ];

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'OrderInfo data retrieved successfully.',
                'data' => $formattedData,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to retrieve OrderInfo data.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }


    // Method to update OrderInfo data and save activity
    public function updateorderInfo(Request $request, $id)
    {
        try {
            // Validate the request
            $request->validate([
                'inside_dhaka' => 'sometimes|numeric',
                'outside_dhaka' => 'sometimes|numeric',
                'vat' => 'sometimes|numeric',
                'bkash_changed' => 'sometimes|numeric',
            ]);

            // Find the OrderInfo record by ID
            $orderInfo = OrderInfo::find($id);

            // If the OrderInfo record doesn't exist, return a 404 response
            if (!$orderInfo) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'OrderInfo data not found.',
                    'data' => null,
                    'errors' => 'OrderInfo data not found.',
                ], 404);
            }

            // Get the authenticated user's ID
            $userId = Auth::id();

            // Track changes
            $changes = [];
            foreach ($request->all() as $key => $value) {
                if ($orderInfo->$key != $value) {
                    $changes[] = "$key changed from {$orderInfo->$key} to $value";
                }
            }

            // Update the OrderInfo record
            $orderInfo->update($request->all());

            // Save the activity
            if (!empty($changes)) {
                Activity::create([
                    'relatable_id' => $orderInfo->id,
                    'type' => 'orderinfo',
                    'user_id' => $userId,
                    'description' => implode(', ', $changes),
                ]);
            }

            // Return the response in the specified format
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'OrderInfo data updated successfully.',
                'data' => $orderInfo,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Handle errors and return a consistent error response
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to update OrderInfo data.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }
}

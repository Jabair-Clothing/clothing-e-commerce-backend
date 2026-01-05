<?php

namespace App\Http\Controllers\Wishlist;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Wishlist;
use App\Services\FileUploadService;
use Illuminate\Support\Facades\Validator;

class WishlistController extends Controller
{
    //store the wishlist
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
                'product_id' => 'required|exists:products,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'status' => 400,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $wishlistItem = Wishlist::firstOrCreate(
                [
                    'user_id' => $request->user_id,
                    'product_id' => $request->product_id,
                ],
                [
                    'user_id' => $request->user_id,
                    'product_id' => $request->product_id,
                ]
            );

            if (!$wishlistItem->wasRecentlyCreated) {
                return response()->json([
                    'success' => false,
                    'status' => 409,
                    'message' => 'This product is already in the wishlist.',
                    'data' => null,
                    'errors' => 'This product is already in the wishlist.',
                ], 409);
            }

            // Return product info also (nice for UI)
            $wishlistItem->load([
                'product:id,name',
                'product.primaryImage:id,product_id,image_url,image_path,is_primary',
            ]);

            $image = null;
            if ($wishlistItem->product && $wishlistItem->product->primaryImage) {
                $image = $wishlistItem->product->primaryImage->image_url
                    ?: $wishlistItem->product->primaryImage->image_path;
            }

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Wishlist item added successfully.',
                'data' => [
                    'wishlist_id' => $wishlistItem->id,
                    'user_id' => $wishlistItem->user_id,
                    'product_id' => $wishlistItem->product_id,
                    'product_name' => $wishlistItem->product?->name,
                    'product_image' => $image,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }




    // Show wishlist items for a specific user
    public function show(Request $request)
    {
        // Get user id from auth
        $user_id = auth()->id();

        // Get 'limit' and 'page' from request
        $perPage = $request->input('limit');
        $currentPage = $request->input('page');

        // Fetch wishlist items for the user with product details
        $wishlistQuery = Wishlist::where('user_id', $user_id)
            ->with('product.images')
            ->orderBy('created_at', 'desc');

        // Check if pagination parameters are provided
        if ($perPage && $currentPage) {
            $wishlistItems = $wishlistQuery->paginate($perPage);
        } else {
            $wishlistItems = $wishlistQuery->get();
        }

        // Check if wishlist is empty
        if ($wishlistItems->isEmpty()) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'No wishlist items found for this user.',
                'data' => [],
                'errors' => 'No wishlist items found for this user.',
            ], 404);
        }

        // Format response
        $wishlistData = $wishlistItems->map(function ($wishlist) {
            return [
                'wishlistId' => $wishlist->id,
                'product_id' => $wishlist->product->id,
                'product_slug' => $wishlist->product->slug,
                'name' => $wishlist->product->name,
                'price' => $wishlist->product->price,
                'discount' => $wishlist->product->discount,
                'image' => $wishlist->product->images->isNotEmpty()
                    ? FileUploadService::getUrls([$wishlist->product->images->first()->path])[0]
                    : null,

            ];
        });

        // Pagination details (only if paginated)
        $pagination = $perPage ? [
            'total_rows' => $wishlistItems->total(),
            'current_page' => $wishlistItems->currentPage(),
            'per_page' => $wishlistItems->perPage(),
            'total_pages' => $wishlistItems->lastPage(),
            'has_more_pages' => $wishlistItems->hasMorePages(),
        ] : null;

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Wishlist items retrieved successfully.',
            'data' => $wishlistData,
            'pagination' => $pagination,
            'error' => null,
        ], 200);
    }



    // remove items from wishlist
    public function destroy($wishlist_id)
    {
        // Find the wishlist item
        $wishlistItem = Wishlist::find($wishlist_id);

        // Check if the wishlist item exists
        if (!$wishlistItem) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Wishlist item not found.',
                'errors' => 'Wishlist item not found.',
            ], 404);
        }

        // Delete the wishlist item
        $wishlistItem->delete();

        // Return JSON response
        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Wishlist item deleted successfully.',
            'errors' => null,
        ], 200);
    }
}

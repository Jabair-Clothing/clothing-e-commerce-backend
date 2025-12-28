<?php

namespace App\Http\Controllers\Tag;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Item;
use App\Models\Tag;
use Illuminate\Support\Str;

// Add tags in product
class TagController extends Controller
{
    public function addTags(Request $request, $product_id)
    {
        try {
            // Validate the request data
            $validator = Validator::make($request->all(), [
                'tags' => 'required|array',
                'tags.*' => 'string|max:255',
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

            // Find the product
            $product = Item::find($product_id);

            // Check if the product exists
            if (!$product) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Product not found.',
                    'data' => null,
                    'errors' => 'Invalid product ID.',
                ], 404);
            }

            // Add tags to the product
            $tags = $request->tags; // Array of tags
            foreach ($tags as $tag) {
                Tag::create([
                    'item_id' => $product_id,
                    'tag' => $tag,
                    'slug' => Str::slug($tag),
                ]);
            }

            // Update the product's meta keywords
            $metaKeywords = $request->has('tags') ? implode(',', $request->tags) : null;
            $product->meta_keywords =  $metaKeywords;
            $product->save();

            // Return success response
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Tags added to the product successfully.',
                'data' => null,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Handle any exceptions
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while adding tags to the product.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // Remove tags from the product
    public function removeTags(Request $request, $product_id)
    {
        try {
            // Validate the request data
            $validator = Validator::make($request->all(), [
                'tag_ids' => 'required|array',
                'tag_ids.*' => 'exists:tags,id',
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

            // Find the product
            $product = Item::find($product_id);

            // Check if the product exists
            if (!$product) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Product not found.',
                    'data' => null,
                    'errors' => 'Invalid product ID.',
                ], 404);
            }

            // Remove tags from the product
            $tagIds = $request->tag_ids;
            Tag::where('item_id', $product_id)
                ->whereIn('id', $tagIds)
                ->delete();

            // Return success response
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Tags removed from the product successfully.',
                'data' => null,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Handle any exceptions
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while removing tags from the product.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers\Rating;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Reating;
use Illuminate\Support\Facades\Storage;


class RatingController extends Controller
{
    //store Rating
    public function store(Request $request)
    {
        try {
            // Validate the request data
            $validator = Validator::make($request->all(), [
                'star' => 'required|integer|between:1,5',
                'rating' => 'nullable|string|max:255',
                'product_id' => 'required|exists:products,id',
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

            // Create the rating
            $rating = Reating::create([
                'User_id' => $request->user()->id,
                'status' => 0,
                'star' => $request->star,
                'rating' => $request->rating,
                'product_id' => $request->product_id,
            ]);

            // Return success response
            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Rating created successfully.',
                'data' => $rating,
                'errors' => null,
            ], 201);
        } catch (\Exception $e) {
            // Handle any exceptions
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while creating the rating.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }
    // shwo all rating
    public function index(Request $request)
    {
        try {
            // Get 'limit', 'page', 'status', and 'star' from request
            $perPage = $request->input('limit');
            $currentPage = $request->input('page');
            $status = $request->input('status');
            $star = $request->input('star');

            // Build the base query with eager loading
            $query = Reating::with([
                'product.images',
                'user'
            ])->orderBy('id', 'desc');

            // Apply status filter if present
            if (!is_null($status)) {
                $query->where('status', $status);
            }

            // Apply star filter if present
            if (!is_null($star)) {
                $query->where('star', $star);
            }

            // Handle pagination
            if ($perPage && $currentPage) {
                if (!is_numeric($perPage) || !is_numeric($currentPage) || $perPage <= 0 || $currentPage <= 0) {
                    return response()->json([
                        'success' => false,
                        'status' => 400,
                        'message' => 'Invalid pagination parameters.',
                        'data' => null,
                        'errors' => 'Invalid pagination parameters.',
                    ], 400);
                }

                $ratings = $query->paginate($perPage, ['*'], 'page', $currentPage);
            } else {
                $ratings = $query->get();
            }

            // Format the response data
            $formattedRatings = $ratings->map(function ($rating) {
                // Get the first image URL or image_path
                $firstImage = null;
                if ($rating->product && $rating->product->images->isNotEmpty()) {
                    $image = $rating->product->images->first();
                    // Check if image_url exists, otherwise use image_path with storage disk
                    if ($image->image_url) {
                        $firstImage = $image->image_url;
                    } elseif ($image->image_path) {
                        $firstImage = Storage::disk('media')->url($image->image_path);
                    }
                }

                return [
                    'id' => $rating->id,
                    'star' => $rating->star,
                    'rating' => $rating->rating,
                    'status' => $rating->status,
                    'product' => $rating->product ? [
                        'id' => $rating->product->id,
                        'name' => $rating->product->name,
                        'image' => $firstImage,
                    ] : null,
                    'user' => $rating->user ? [
                        'name' => $rating->user->name,
                        'email' => $rating->user->email,
                        'phone' => $rating->user->phone,
                    ] : null,
                ];
            });


            // Return the JSON response
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Ratings retrieved successfully.',
                'data' => $formattedRatings,
                'pagination' => isset($ratings) && method_exists($ratings, 'total') ? [
                    'total_rows' => $ratings->total(),
                    'current_page' => $ratings->currentPage(),
                    'per_page' => $ratings->perPage(),
                    'total_pages' => $ratings->lastPage(),
                    'has_more_pages' => $ratings->hasMorePages(),
                ] : null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while retrieving ratings.',
                'data' => null,
                'errors' => $e->getMessage()
            ], 500);
        }
    }


    // Toggles the rating
    public function toggleStatus($id)
    {
        try {
            // Find the rating by ID
            $rating = Reating::find($id);

            // Check if the rating exists
            if (!$rating) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Rating not found.',
                    'data' => null,
                    'errors' => 'Invalid rating ID.',
                ], 404);
            }

            // Toggle the status
            $rating->status = $rating->status == 1 ? 0 : 1;
            $rating->save();

            // Return success response with the updated rating
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Rating status toggled successfully.',
                'data' => [
                    'id' => $rating->id,
                    'status' => $rating->status,
                ],
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Handle any exceptions
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while toggling the rating status.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // Delete rating
    public function destroy($id)
    {
        try {
            // Find the rating by ID
            $rating = Reating::find($id);

            // Check if the rating exists
            if (!$rating) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Rating not found.',
                    'data' => null,
                    'errors' => 'Invalid rating ID.',
                ], 404);
            }

            // Delete the rating
            $rating->delete();

            // Return success response
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Rating deleted successfully.',
                'data' => null,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Handle any exceptions
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while deleting the rating.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }
}

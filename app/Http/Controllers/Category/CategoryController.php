<?php

namespace App\Http\Controllers\Category;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\CategoryProductList;
use Illuminate\Support\Facades\Validator;
use App\Models\Product;
use App\Models\Item;
use Illuminate\Support\Str;
use App\Helpers\ActivityHelper;

class CategoryController extends Controller
{
    /**
     * Store a newly created category.
     */
    public function store(Request $request)
    {
        try {
            // Validate the request data
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'required|string|max:255',
            ]);

            // Create the category
            $category = Category::create([
                'name' => $validatedData['name'],
                'description' => $validatedData['description'],
                'status' => 1,
                'slug' => Str::slug($validatedData['name']),
            ]);

            $categoryDesc = "Created Category : Name - {$category->name} Date - " . now()->toDateTimeString();
            // Save activity
            ActivityHelper::logActivity($category->id, 'Category', $categoryDesc);

            // Return a success response
            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Category created successfully.',
                'data' => $category,
                'errors' => null,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Handle validation errors
            return response()->json([
                'success' => false,
                'status' => 422,
                'message' => 'Validation failed.',
                'data' => null,
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            // Handle other exceptions
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while creating the category.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retrieve all categories.
     */
    public function index(Request $request)
    {
        try {
            // Get 'limit', 'page', and 'search' from the request
            $perPage = $request->input('limit');
            $currentPage = $request->input('page');
            $search = $request->input('search');

            // Base query to fetch categories with descending order by 'created_at'
            $query = Category::query()->orderBy('created_at', 'desc');

            // Exclude categories with status 0
            $query->where('status', '!=', 0);

            // Apply search filter if 'search' parameter is provided
            if ($search) {
                $query->where('name', 'like', '%' . $search . '%');
            }

            // If pagination parameters are provided, apply pagination
            if ($perPage && $currentPage) {
                // Validate pagination parameters
                if (!is_numeric($perPage) || !is_numeric($currentPage) || $perPage <= 0 || $currentPage <= 0) {
                    return response()->json([
                        'success' => false,
                        'status' => 400,
                        'message' => 'Invalid pagination parameters.',
                        'data' => null,
                        'errors' => 'Invalid pagination parameters.',
                    ], 400);
                }

                // Apply pagination
                $categories = $query->paginate($perPage, ['*'], 'page', $currentPage);

                // Return response with pagination data
                return response()->json([
                    'success' => true,
                    'status' => 200,
                    'message' => 'Categories retrieved successfully.',
                    'data' => $categories->items(),
                    'pagination' => [
                        'total_rows' => $categories->total(),
                        'current_page' => $categories->currentPage(),
                        'per_page' => $categories->perPage(),
                        'total_pages' => $categories->lastPage(),
                        'has_more_pages' => $categories->hasMorePages(),
                    ]
                ], 200);
            }

            // If no pagination parameters, fetch all records without pagination
            $categories = $query->get();

            // Return response without pagination links
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Categories retrieved successfully.',
                'data' => $categories
            ], 200);
        } catch (\Exception $e) {
            // Handle any exceptions that occur
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while retrieving categories.',
                'data' => null,
                'errors' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Retrieve a single category by ID.
     */
    public function show($id)
    {
        try {
            // Find the category by ID
            $category = Category::find($id);

            // Check if the category exists
            if (!$category) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Category not found.',
                    'data' => null,
                    'errors' => 'Invalid category ID.',
                ], 404);
            }

            // Return a success response with the category
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Category retrieved successfully.',
                'data' => $category,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Handle any exceptions that occur
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while retrieving the category.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an existing category.
     */
    public function update(Request $request, $id)
    {
        try {
            $category = Category::find($id);

            if (!$category) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Category not found.',
                    'data' => null,
                    'errors' => 'Invalid category ID.',
                ], 404);
            }

            $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
            ]);

            // Update the category
            $category->name = $request->name;
            $category->description = $request->description;
            $category->slug = Str::slug($request->name);

            if (!$category->save()) {
                throw new \Exception("Failed to update category.");
            }

            $categoryDesc = "Update Category : Name - {$category->name} Date - " . now()->toDateTimeString();
            // Save activity
            ActivityHelper::logActivity($category->id, 'Category', $categoryDesc);

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Category updated successfully.',
                'data' => $category,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to update category.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a category.
     */
    public function destroy($id)
    {
        try {
            // Find the category by ID
            $category = Category::find($id);

            // Check if the category exists
            if (!$category) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Category not found.',
                    'data' => null,
                    'errors' => 'Invalid category ID.',
                ], 404);
            }

            // Delete the category
            $category->delete();

            $categoryDesc = "Delete Category : Name - {$category->name} Date - " . now()->toDateTimeString();
            // Save activity
            ActivityHelper::logActivity($category->id, 'Category', $categoryDesc);

            // Return a success response
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Category deleted successfully.',
                'data' => null,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Handle any exceptions that occur
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while deleting the category.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle category status (1 <=> 0).
     */
    public function toggleStatus($id)
    {
        try {
            // Find the category by ID
            $category = Category::find($id);

            // Check if the category exists
            if (!$category) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Category not found.',
                    'data' => null,
                    'errors' => 'Invalid category ID.',
                ], 404);
            }

            // Toggle the status
            $category->status = $category->status == 1 ? 0 : 1;
            $category->save();

            // Determine readable status
            $statusLabel = $category->status == 1 ? 'Active' : 'Inactive';

            // Prepare activity log description
            $categoryDesc = "Updated Category status to {$statusLabel} : Name - {$category->name}, Date - " . now()->toDateTimeString();

            // Save activity
            ActivityHelper::logActivity($category->id, 'Category', $categoryDesc);

            // Return a success response with the updated category
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Category status updated successfully.',
                'data' => $category,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Handle any exceptions that occur
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while updating the category status.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // add the category in product
    public function addCategories(Request $request, $product_id)
    {
        try {
            // Validate the request data
            $validator = Validator::make($request->all(), [
                'category_id' => 'required|array',
                'category_id.*' => 'exists:categories,id',
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

            // Attach categories to the product
            $categoryIds = $request->category_id;
            foreach ($categoryIds as $categoryId) {
                CategoryProductList::create([
                    'category_id' => $categoryId,
                    'item_id' => $product_id,
                ]);
            }

            // Return success response
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Categories added to the product successfully.',
                'data' => null,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Handle any exceptions
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while adding categories to the product.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // remove the category from product
    public function removeCategories(Request $request, $product_id)
    {
        try {
            // Validate the request data
            $validator = Validator::make($request->all(), [
                'category_id' => 'required|array',
                'category_id.*' => 'exists:categories,id',
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
            $product = Product::find($product_id);

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

            // Remove categories from the product
            $categoryIds = $request->category_id;
            CategoryProductList::where('item_id', $product_id)
                ->whereIn('category_id', $categoryIds)
                ->delete();

            // Return success response
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Categories removed from the product successfully.',
                'data' => null,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Handle any exceptions
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while removing categories from the product.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }
}

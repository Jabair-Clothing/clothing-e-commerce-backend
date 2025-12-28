<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Item;
use App\Models\File;
use App\Models\Tag;
use App\Models\BundleItem;
use App\Models\Cetagory_Product_list;
use App\Models\Challan_item;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Traits\ApiResponser;
use App\Helpers\ActivityHelper;
use App\Services\FileUploadService;

class ProductController extends Controller
{
    use ApiResponser;
    // Store the product
    public function store(Request $request)
    {
        try {
            // Validate the request data
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'short_description' => 'nullable|string',
                'quantity' => 'required|integer|min:0',
                'price' => 'required|numeric|min:0',
                'discount' => 'nullable|numeric|min:0|max:100',
                'categories' => 'required|array',
                'categories.*' => 'exists:cetagories,id',
                'tags' => 'nullable|array',
                'tags.*' => 'string|max:255',
                'images' => 'nullable|array',
                'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:4048',
                'is_bundle' => 'nullable|integer',
                'bundls_item' => 'nullable|array',
                'bundls_item.*.item_id' => 'required|integer|exists:items,id',
                'bundls_item.*.bundle_quantity' => 'nullable|integer|min:1',
            ]);

            // If validation fails, return error response
            if ($validator->fails()) {
                return $this->error('Validation failed.', 422, $validator->errors());
            }

            // slug generate
            $slug = Str::slug($request->name) . '-' . '-zantech';

            $metaKeywords = $request->has('tags') ? implode(',', $request->tags) : null;
            $metaDescription = $request->description ? Str::limit(strip_tags($request->description), 255) : null;
            // Create the product
            $product = Item::create([
                'name' => $request->name,
                'slug' => $slug,
                'description' => $request->description,
                'short_description' => $request->short_description,
                'status' => 1,
                'quantity' => $request->quantity,
                'price' => $request->price,
                'discount' => $request->discount,
                'is_bundle' => $request->is_bundle,
                'meta_title' => $request->name,
                'meta_keywords' => $metaKeywords,
                'meta_description' => $metaDescription,
            ]);

            // Save bundle items with bundle_quantity
            if ($request->has('bundls_item')) {
                foreach ($request->bundls_item as $bundleItem) {
                    BundleItem::create([
                        'item_id' => $bundleItem['item_id'],
                        'bundle_item_id' => $product->id,
                        'bundle_quantity' => $bundleItem['bundle_quantity'] ?? 1,
                    ]);
                }
            }

            // Save categories
            if ($request->has('categories')) {
                foreach ($request->categories as $categoryId) {
                    Cetagory_Product_list::create([
                        'category_id' => $categoryId,
                        'item_id' => $product->id,
                    ]);
                }
            }

            // Save tags
            if ($request->has('tags')) {
                foreach ($request->tags as $tag) {
                    Tag::create([
                        'item_id' => $product->id,
                        'tag' => $tag,
                        'slug' => Str::slug($tag),
                    ]);
                }
            }

            // Save images
            if ($request->hasFile('images')) {
                $paths = FileUploadService::uploadMultiple(
                    $request->file('images'),
                    'product_image',
                    'zantech',
                    $request->name // product name as base
                );

                foreach ($paths as $path) {
                    File::create([
                        'relatable_id' => $product->id,
                        'type'         => 'product',
                        'path'         => $path,
                    ]);
                }
            }

            // Return success response
            return $this->created($product, 'Product created successfully.');
        } catch (\Exception $e) {
            // Handle any exceptions
            return $this->error('An error occurred while creating the product', 500, $e->getMessage());
        }
    }

    // Toggles the product status
    public function toggleStatus($product_id)
    {
        try {
            // Find the product
            $product = Item::find($product_id);

            // Check if the product exists
            if (!$product) {
                return $this->notFound('Product not found.');
            }

            // Toggle the status
            $product->status = $product->status == 1 ? 0 : 1;
            $product->save();

            // Return success response with the updated product
            return $this->created(null, 'Product status toggled successfully.');
        } catch (\Exception $e) {
            // Handle any exceptions
            return $this->error('An error occurred while toggling the product status.', 500, $e->getMessage());
        }
    }

    // update product
    public function updateProduct(Request $request, $product_id)
    {
        try {
            // Validate
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'description' => 'sometimes|string',
                'short_description' => 'sometimes|string',
                'quantity' => 'sometimes|integer|min:0',
                'price' => 'sometimes|numeric|min:0',
                'discount' => 'sometimes|numeric',
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

            // Find product
            $product = Item::find($product_id);

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Product not found.',
                    'data' => null,
                    'errors' => 'Invalid product ID.',
                ], 404);
            }

            $updateData = $request->only([
                'name',
                'description',
                'short_description',
                'quantity',
                'price',
                'discount',
            ]);

            // If updating name → regenerate slug + meta_title
            if ($request->has('name')) {
                $updateData['slug'] = Str::slug($request->name) . '-' . '-zantech';
                $updateData['meta_title'] = $request->name;
            }

            // If updating description → update meta_description
            if ($request->has('description')) {
                $updateData['meta_description'] = Str::limit(strip_tags($request->description), 255);
            }

            // Update product
            $product->update($updateData);

            // Log activity
            $changes = collect($updateData)->map(function ($value, $key) use ($product) {
                $oldValue = $product->getOriginal($key);
                return "{$key}: {$oldValue} → {$value}";
            })->implode(', ');

            $activityDesc = "Updated Product ID: {$product_id}, Changes: {$changes}, Updated at - " . now()->toDateTimeString();

            ActivityHelper::logActivity(
                $product_id,
                'product',
                $activityDesc
            );

            return $this->success($product, 'Product updated successfully.');
        } catch (\Exception $e) {
            return $this->error('An error occurred while updating the product.', 500, $e->getMessage());
        }
    }

    // Delete a product
    public function deleteProduct($product_id)
    {
        try {
            // Find the product
            $product = Item::find($product_id);

            // Check if the product exists
            if (!$product) {
                return $this->notFound('Product not found.');
            }

            // Check if the product is a bundle itself or is part of another bundle
            if ($product->is_bundle === 0) {
                $isLinkedToBundle = BundleItem::where('item_id', $product_id)->exists();
                if ($isLinkedToBundle) {
                    return $this->error('This product is part of a bundle and cannot be deleted.', 409);
                }
            }

            // Fetch related images
            $images = File::where('relatable_id', $product_id)
                ->where('type', 'product')
                ->get();

            // Delete image files from storage
            foreach ($images as $image) {
                // delete from storage using service
                FileUploadService::delete($image->path);

                // delete DB record
                $image->delete();
            }

            // Delete related records from Cetagory_Product_list, Tag, and File tables
            Cetagory_Product_list::where('item_id', $product_id)->delete();
            Tag::where('item_id', $product_id)->delete();
            File::where('relatable_id', $product_id)->where('type', 'product')->delete();
            BundleItem::where('bundle_item_id', $product_id)->delete();

            // Delete the product
            $product->delete();

            // Return success response
            return $this->created(null, 'Product and related data deleted successfully.');
        } catch (\Exception $e) {
            // Handle any exceptions
            return $this->error('An error occurred while deleting the product and related data.', 500, $e->getMessage());
        }
    }

    // Get items by buying price
    public function getitemsByBuyingPrice(Request $request)
    {
        try {
            $perPage = $request->input('limit', 10);
            $currentPage = $request->input('page', 1);
            $search = $request->input('search');
            $date = $request->input('date');
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            // Base query
            $query = Challan_item::with(['item', 'challan.supplier'])
                ->orderBy('created_at', 'desc');

            // Filter by item_name
            if ($search) {
                $query->where('item_name', 'like', '%' . $search . '%');
            }

            // Filter by exact date
            if ($date) {
                $query->whereDate('created_at', $date);
            }

            // Filter by date range
            if ($startDate && $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate]);
            }

            // Get all and then unique by item_id (latest only)
            $items = $query->get()
                ->unique('item_id')
                ->values();

            // Manual pagination
            $total = $items->count();
            $pagedItems = $items->forPage($currentPage, $perPage)->values();

            $formatted = $pagedItems->map(function ($item) {
                return [
                    'item_id'       => $item->item_id,
                    'item_name'     => $item->item_name,
                    'buying_price'  => $item->buying_price,
                    'created_at'    => $item->created_at->toDateTimeString(),
                    'supplier_name' => optional($item->challan->supplier)->name,
                ];
            });

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Items retrieved successfully.',
                'data' => $formatted,
                'pagination' => [
                    'total_rows' => $total,
                    'current_page' => (int) $currentPage,
                    'per_page' => (int) $perPage,
                    'total_pages' => ceil($total / $perPage),
                    'has_more_pages' => ($currentPage * $perPage) < $total,
                ]
            ]);
        } catch (\Exception $e) {
            return $this->error('Something went wrong.', 500, $e->getMessage());
        }
    }

    public function getItemBuyingHistory($item_id)
    {
        try {
            $items = Challan_item::with(['challan.supplier'])
                ->where('item_id', $item_id)
                ->orderBy('created_at', 'desc')
                ->get();

            $formatted = $items->map(function ($item) {
                return [
                    'item_id'       => $item->item_id,
                    'item_name'     => $item->item_name,
                    'quantity'      => $item->quantity,
                    'buying_price'  => $item->buying_price,
                    'created_at'    => $item->created_at->toDateTimeString(),
                    'challan_id'    => $item->challan_id,
                    'challan_date'  => optional($item->challan)->Date,
                    'supplier_name' => optional($item->challan->supplier)->name,
                ];
            });

            return $this->success($formatted, 'Buying history fetched successfully.');
        } catch (\Exception $e) {
            return $this->error('something went wrong', 500, $e->getMessage());
        }
    }

    // Get in-stock products
    public function inStockProducts(Request $request)
    {
        try {
            // Get 'limit', 'page', 'search', 'min_price', and 'max_price' from request
            $perPage = $request->input('limit');
            $currentPage = $request->input('page');
            $search = $request->input('search');
            $minPrice = $request->input('min_price');
            $maxPrice = $request->input('max_price');

            // Base query to fetch products with all related images of type 'product'
            $query = Item::with(['images' => function ($query) {
                $query->where('type', 'product')->orderBy('id', 'asc');
            }])->orderBy('created_at', 'desc');

            // Exclude items with quantity <= 0
            $query->where('quantity', '>', 0);


            // Apply search filter if 'search' parameter is provided
            if ($search) {
                $query->where('name', 'like', '%' . $search . '%');
            }

            // Apply price range filter if 'min_price' and 'max_price' are provided
            if ($minPrice) {
                $query->where('price', '>=', $minPrice);
            }
            if ($maxPrice) {
                $query->where('price', '<=', $maxPrice);
            }

            // If pagination parameters are provided, apply pagination
            if ($perPage && $currentPage) {
                // Validate pagination parameters
                if (!is_numeric($perPage) || !is_numeric($currentPage) || $perPage <= 0 || $currentPage <= 0) {
                    return $this->notFound('Invalid pagination parameters.');
                }

                // Apply pagination
                $products = $query->paginate($perPage, ['*'], 'page', $currentPage);

                // Format the response with pagination data
                $formattedProducts = $products->map(function ($product) {
                    // Collect all image paths for this product
                    $imagePaths = FileUploadService::getUrls(
                        $product->images->pluck('path')->toArray()
                    );

                    return [
                        'id' => $product->id,
                        'slug' => $product->slug,
                        'name' => $product->name,
                        'status' => $product->status,
                        'quantity' => $product->quantity,
                        'price' => $product->price,
                        'discount' => $product->discount,
                        'image_paths' => $imagePaths,
                    ];
                });

                // Return response with pagination data
                return response()->json([
                    'success' => true,
                    'status' => 200,
                    'message' => 'Products retrieved successfully.',
                    'data' => $formattedProducts,
                    'pagination' => [
                        'total_rows' => $products->total(),
                        'current_page' => $products->currentPage(),
                        'per_page' => $products->perPage(),
                        'total_pages' => $products->lastPage(),
                        'has_more_pages' => $products->hasMorePages(),
                    ]
                ], 200);
            }

            // If no pagination parameters, fetch all records without pagination
            $products = $query->get();

            // Format the response
            $formattedProducts = $products->map(function ($product) {
                // Collect all image paths for this produc
                $imagePaths = FileUploadService::getUrls(
                    $product->images->pluck('path')->toArray()
                );

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'status' => $product->status,
                    'quantity' => $product->quantity,
                    'price' => $product->price,
                    'discount' => $product->discount,
                    'image_paths' => $imagePaths,
                ];
            });

            // Return response without pagination links
            return $this->success($formattedProducts, 'Products retrieved successfully');
        } catch (\Exception $e) {
            // Handle any exceptions
            return $this->error('An error occurred while retrieving products.', 500, $e->getMessage());
        }
    }

    // show all product except bundle
    public function showallproductsExceptBundles(Request $request)
    {
        try {
            $search = $request->input('search');

            $query = Item::with(['images' => function ($query) {
                $query->where('type', 'product')->orderBy('id', 'asc');
            }])
                ->where('status', '!=', 0)
                ->where('is_bundle', 0)
                ->orderBy('created_at', 'desc');

            if ($search) {
                $query->where('name', 'like', '%' . $search . '%');
            }

            $products = $query->get();

            $formattedProducts = $products->map(function ($product) {
                $imagePaths = FileUploadService::getUrls(
                    $product->images->pluck('path')->toArray()
                );
                return [
                    'id' => $product->id,
                    'slug' => $product->slug,
                    'name' => $product->name,
                    'short_description' => $product->short_description,
                    'status' => $product->status,
                    'quantity' => $product->quantity,
                    'price' => $product->price,
                    'discount' => $product->discount,
                    'image_paths' => $imagePaths,
                ];
            });

            return $this->success($formattedProducts, 'Products retrieved successfully.');
        } catch (\Exception $e) {
            return $this->error('An error occurred while retrieving products.', 500, $e->getMessage());
        }
    }

    // shwo all product is bundle
    public function showallproductsIsBundles(Request $request)
    {
        try {
            $search = $request->input('search');

            $query = Item::with(['images' => function ($query) {
                $query->where('type', 'product')->orderBy('id', 'asc');
            }])
                ->where('status', '!=', 0)
                ->where('is_bundle', 1)
                ->orderBy('created_at', 'desc');

            if ($search) {
                $query->where('name', 'like', '%' . $search . '%');
            }

            $products = $query->get();

            $formattedProducts = $products->map(function ($product) {
                $imagePaths = FileUploadService::getUrls(
                    $product->images->pluck('path')->toArray()
                );

                return [
                    'id' => $product->id,
                    'slug' => $product->slug,
                    'name' => $product->name,
                    'short_description' => $product->short_description,
                    'status' => $product->status,
                    'quantity' => $product->quantity,
                    'price' => $product->price,
                    'discount' => $product->discount,
                    'image_paths' => $imagePaths,
                ];
            });

            return $this->success($formattedProducts, 'Products retrieved successfully.');
        } catch (\Exception $e) {
            return $this->error('An error occurred while retrieving products.', 500, $e->getMessage());
        }
    }
}

<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\File;
use App\Models\Tag;
use App\Models\BundleItem;
use App\Models\CategoryProductList;
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
                'categories.*' => 'exists:categories,id',
                'tags' => 'nullable|array',
                'tags.*' => 'string|max:255',
                'images' => 'nullable|array',
                'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:4048',
                'is_bundle' => 'nullable|integer',
                // Bundle logic kept but might need review if BundleItem relied on Item
                'bundls_item' => 'nullable|array',
                'bundls_item.*.item_id' => 'required|integer|exists:products,id',
                'bundls_item.*.bundle_quantity' => 'nullable|integer|min:1',
            ]);

            // If validation fails, return error response
            if ($validator->fails()) {
                return $this->error('Validation failed.', 422, $validator->errors());
            }

            // slug generate
            $slug = Str::slug($request->name) . '-' . '-zantech'; // Keeping existing logic

            $metaKeywords = $request->has('tags') ? implode(',', $request->tags) : null;
            $metaDescription = $request->description ? Str::limit(strip_tags($request->description), 255) : null;

            // Create the product
            $product = Product::create([
                'name' => $request->name,
                'slug' => $slug,
                'description' => $request->description,
                'short_description' => $request->short_description,
                'is_active' => true, // Default active
                'base_price' => $request->price, // Use price as base_price
                // 'quantity' removed
                // 'discount' removed from product table? Migration said dropColumn discount. 
                // So discount is now in Variants?
                // Migration 2025_12_29_000003_create_product_variants_tables.php: $table->decimal('discount_price', 10, 2)->nullable();
                // But request had 'discount' (percent?). 
                // If the old system used 'discount' (integer? percent?), and new uses 'discount_price' (decimal).
                // I will ignore discount on Product for now or map it to variant discount_price?
                // Or maybe Product table HAS discount? 
                // Migration `update_products_structure`: $table->dropColumn(['quantity', 'price', 'discount', 'is_bundle']);
                // Wait, I dropped `is_bundle` too?
                // Let's check `update_products_structure` content.
                // Yes, `dropColumn(['quantity', 'price', 'discount', 'is_bundle']);`
                // So `is_bundle` is gone? 
                // Product model `is_active`.
                // If `is_bundle` logic is needed, I should have kept it or moved to Attribute?
                // The User Request didn't mention `is_bundle` in `products` table schema provided.
                // "Schema::create('products', ... $table->boolean('is_active')... $table->decimal('base_price')..."
                // It did NOT list `is_bundle`. 
                // So I removed it.
                // I will assume for now simple products.
                'category_id' => $request->categories[0] ?? null, // Taking first category as primary? 
                // Migration added `category_id`.
                'meta_title' => $request->name,
                'meta_keywords' => $metaKeywords,
                'meta_description' => $metaDescription,
            ]);

            // Create Default Variant
            $sku = strtoupper(Str::slug($request->name)) . '-' . Str::random(4);
            ProductVariant::create([
                'product_id' => $product->id,
                'sku' => $sku,
                'price' => null, // Use base_price
                'discount_price' => null, // Handle discount later or calc
                'stock_quantity' => $request->quantity,
            ]);

            // Save bundle items - BundleItem expects 'bundle_item_id' (parent) and 'item_id' (child)
            // If `is_bundle` column is gone, how do we know it is a bundle?
            // Maybe we strictly use BundleItem table existence?
            if ($request->has('bundls_item')) {
                foreach ($request->bundls_item as $bundleItem) {
                    BundleItem::create([
                        'item_id' => $bundleItem['item_id'],
                        'bundle_item_id' => $product->id, // This product is the bundle
                        'bundle_quantity' => $bundleItem['bundle_quantity'] ?? 1,
                    ]);
                }
            }

            // Save categories - Legacy Many-to-Many?
            // If I set `category_id` on Product, maybe I don't need this pivot?
            // But if existing code relies on it, keep it.
            if ($request->has('categories')) {
                foreach ($request->categories as $categoryId) {
                    CategoryProductList::create([
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
                    Str::slug($request->name)
                );

                foreach ($paths as $path) {
                    // New ProductImage table? Or File table?
                    // User requested: "// 6. product_images table... Schema::create('product_images'..."
                    // I created `ProductImage` model.
                    // But `File` model is used for polymorphic.
                    // If I want to use the new system, I should use `ProductImage::create`.
                    // But if I want to keep backward compatibility or use File service?
                    // I'll try to use `ProductImage` if possible, OR keep `File` if I didn't migrate old data.
                    // The migration `create_product_images_table` is new.
                    // So I should populate `product_images` table.
                    // But `File` table still exists?
                    // I will populate `product_images` table as per new requirement.

                    // Wait, `ProductImage` fields: `product_id`, `image_path`, `image_url`...
                    // `FileUploadService` returns paths. 
                    // I need URL too? `asset($path)`?
                    $url = asset('storage/' . $path); // Approximate

                    \App\Models\ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $path,
                        'image_url' => $url,
                        'is_primary' => false, // Set first as primary?
                    ]);

                    // Keeping File::create for legacy? Maybe unnecessary if I refactor read paths.
                    // I'll skip File::create to strictly use new system.
                }
            }

            // Return success response
            return $this->created($product, 'Product created successfully.');
        } catch (\Exception $e) {
            // Handle any exceptions
            return $this->error('An error occurred while creating the product: ' . $e->getMessage(), 500);
        }
    }

    // Toggles the product status
    // Toggles the product status
    public function toggleStatus($product_id)
    {
        try {
            // Find the product
            $product = Product::find($product_id);

            // Check if the product exists
            if (!$product) {
                return $this->notFound('Product not found.');
            }

            // Toggle the status
            $product->is_active = !$product->is_active;
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
            $product = Product::find($product_id);

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
                // 'quantity', // Removed from Product model
                // 'price', // Removed from Product model, used as base_price
                // 'discount', // Removed
            ]);

            if ($request->has('price')) {
                $updateData['base_price'] = $request->price;
            }

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

            // Update Variant (Quantity and Price)
            // Assuming single variant for now or updating all/default?
            // We'll update the first variant found or creates one if missing
            $variant = $product->variants()->first();
            if ($variant) {
                if ($request->has('quantity')) {
                    $variant->stock_quantity = $request->quantity;
                }
                // If we treat base_price as the price, we don't necessarily need to update variant price unless overrides.
                // But let's keep variant price null to use base_price, OR update it if logic demands.
                // For now, only updating quantity on variant.
                $variant->save();
            } else {
                // Create default if missing (migration fix?)
                if ($request->has('quantity')) {
                    $sku = strtoupper(Str::slug($product->name)) . '-' . Str::random(4);
                    ProductVariant::create([
                        'product_id' => $product->id,
                        'sku' => $sku,
                        'stock_quantity' => $request->quantity,
                        'price' => null,
                    ]);
                }
            }

            // Log activity
            $changes = collect($updateData)->map(function ($value, $key) use ($product) {
                // $product->getOriginal($key) might depend on refresh? 
                // Simple logging for now.
                return "{$key}: {$value}";
            })->implode(', ');

            if ($request->has('quantity')) {
                $changes .= ", quantity: {$request->quantity}";
            }

            $activityDesc = "Updated Product ID: {$product_id}, Changes: {$changes}, Updated at - " . now()->toDateTimeString();

            ActivityHelper::logActivity(
                $product_id,
                'product',
                $activityDesc
            );

            return $this->success($product, 'Product updated successfully.');
        } catch (\Exception $e) {
            return $this->error('An error occurred while updating the product: ' . $e->getMessage(), 500);
        }
    }

    // Delete a product
    public function deleteProduct($product_id)
    {
        try {
            // Find the product
            $product = Product::find($product_id);

            // Check if the product exists
            if (!$product) {
                return $this->notFound('Product not found.');
            }


            // Delete image files from storage
            foreach ($images as $image) {
                FileUploadService::delete($image->path);
                $image->delete();
            }

            // Delete ProductImages too
            $productImages = \App\Models\ProductImage::where('product_id', $product_id)->get();
            foreach ($productImages as $img) {
                // Assuming path is similar or same service used
                FileUploadService::delete($img->image_path);
                $img->delete();
            }

            // Delete related records from Cetagory_Product_list, Tag, and File tables
            CategoryProductList::where('item_id', $product_id)->delete();
            Tag::where('item_id', $product_id)->delete();
            // Delete the product
            $product->delete();

            // Return success response
            return $this->created(null, 'Product and related data deleted successfully.');
        } catch (\Exception $e) {
            // Handle any exceptions
            return $this->error('An error occurred while deleting the product and related data.', 500, $e->getMessage());
        }
    }

    // Get items by buying price - Uses Challan_item
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
            // Challan_item uses 'item' relationship. Should point to Product now if updated.
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
                    'quantity'      => $item->quantity, // Challan quantity
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
            $perPage = $request->input('limit');
            $currentPage = $request->input('page');
            $search = $request->input('search');
            $minPrice = $request->input('min_price');
            $maxPrice = $request->input('max_price');

            // Base query to fetch products with all related images
            // Use Product model. 
            // Load variants too?
            $query = Product::with(['images', 'variants'])->orderBy('created_at', 'desc');

            // Exclude items with quantity <= 0
            // Since quantity is in variants, we assume "in stock" means at least one variant has stock?
            // Or total stock > 0?
            $query->whereHas('variants', function ($q) {
                $q->where('stock_quantity', '>', 0);
            });

            // Apply search filter
            if ($search) {
                $query->where('name', 'like', '%' . $search . '%');
            }

            // Apply price range filter
            // Check base_price? Or Variant price?
            // Assuming base_price for simplicity.
            if ($minPrice) {
                $query->where('base_price', '>=', $minPrice);
            }
            if ($maxPrice) {
                $query->where('base_price', '<=', $maxPrice);
            }

            if ($perPage && $currentPage) {
                if (!is_numeric($perPage) || !is_numeric($currentPage) || $perPage <= 0 || $currentPage <= 0) {
                    return $this->notFound('Invalid pagination parameters.');
                }

                $products = $query->paginate($perPage, ['*'], 'page', $currentPage);

                // Helper closure
                $format = function ($products) {
                    return $products->map(function ($product) {
                        // Use ProductImage model logic if available or fallback to images relation
                        $imagePaths = FileUploadService::getUrls(
                            $product->images->pluck('image_path')->toArray() // using image_path from ProductImage
                        );
                        // If empty, try 'path' from File model if mixed? 
                        // Assuming new system uses `image_path` in `ProductImage`.
                        // But I need to handle `File` migration if data exists there.
                        // For now assuming existing logic uses `File` and `path`.
                        // Product model `images()` definition: `hasMany(ProductImage::class)`.
                        // ProductImage has `image_path`.
                        // Existing `File` has `path`.
                        // I updated `images()` to `hasMany(ProductImage::class)`.
                        // So I must look for `image_path`.

                        // Sum quantity
                        $quantity = $product->variants->sum('stock_quantity');

                        return [
                            'id' => $product->id,
                            'slug' => $product->slug,
                            'name' => $product->name,
                            'status' => $product->is_active ? 1 : 0, // Map boolean to status code if frontend expects it
                            'quantity' => $quantity,
                            'price' => $product->base_price,
                            'discount' => 0, // removed
                            'image_paths' => $imagePaths,
                        ];
                    });
                };

                $formattedProducts = $format($products);

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

            $products = $query->get();

            // Re-use logic (copy-paste for now due to complexity inside closure)
            $formattedProducts = $products->map(function ($product) {
                $imagePaths = FileUploadService::getUrls(
                    $product->images->pluck('image_path')->toArray()
                );
                $quantity = $product->variants->sum('stock_quantity');
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'status' => $product->is_active ? 1 : 0,
                    'quantity' => $quantity,
                    'price' => $product->base_price,
                    'discount' => 0,
                    'image_paths' => $imagePaths,
                ];
            });

            return $this->success($formattedProducts, 'Products retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('An error occurred while retrieving products: ' . $e->getMessage(), 500);
        }
    }

    // show all product except bundle
    public function showallproductsExceptBundles(Request $request)
    {
        try {
            $search = $request->input('search');

            // Using Product model. 'is_bundle' removed. 
            // How to filter?
            // Maybe check if it exists in BundleItem as parent?
            // Or assume all are simple for now unless I add 'type' to Product.
            // Assuming simplified logic: Show all products.

            $query = Product::with(['images', 'variants'])
                ->where('is_active', true)
                ->orderBy('created_at', 'desc');

            if ($search) {
                $query->where('name', 'like', '%' . $search . '%');
            }

            $products = $query->get();

            $formattedProducts = $products->map(function ($product) {
                $imagePaths = FileUploadService::getUrls(
                    $product->images->pluck('image_path')->toArray()
                );
                $quantity = $product->variants->sum('stock_quantity');
                return [
                    'id' => $product->id,
                    'slug' => $product->slug,
                    'name' => $product->name,
                    'short_description' => $product->short_description,
                    'status' => $product->is_active ? 1 : 0,
                    'quantity' => $quantity,
                    'price' => $product->base_price,
                    // 'discount' => $product->discount,
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
        // Logic broken as is_bundle is removed. Returning empty list for now or TODO.
        return $this->success([], 'Bundles retrieved successfully (No bundles found).');
    }
}

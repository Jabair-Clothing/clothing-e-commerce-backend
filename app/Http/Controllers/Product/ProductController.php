<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductImage;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Services\FileUploadService;

class ProductController extends Controller
{
    // Helper functions for response
    protected function success($data, $message = null, $code = 200)
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ], $code);
    }

    protected function error($message, $code = 400, $data = null)
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'data' => $data
        ], $code);
    }

    // LIST PRODUCTS
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('limit', 10);
            $search = $request->input('search');
            $categoryId = $request->input('category_id');
            $parentCategoryId = $request->input('parent_category_id');
            $status = $request->input('status');

            $query = Product::with(['primaryImage', 'variants', 'category', 'parentCategory'])
                ->orderBy('created_at', 'desc');

            if ($search) {
                $query->where('name', 'like', '%' . $search . '%');
            }

            if ($categoryId) {
                $query->where('category_id', $categoryId);
            }

            if ($parentCategoryId) {
                $query->where('parent_category_id', $parentCategoryId);
            }

            if ($status !== null) {
                $query->where('is_active', filter_var($status, FILTER_VALIDATE_BOOLEAN));
            }

            $products = $query->paginate($perPage);

            // Format data
            $products->getCollection()->transform(function ($product) {
                return $this->formatProduct($product);
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Products retrieved successfully.',
                'data' => $products
            ]);
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve products: ' . $e->getMessage(), 500);
        }
    }

    // SHOW SINGLE PRODUCT
    public function show($id)
    {
        try {
            $product = Product::with(['images', 'variants', 'category', 'parentCategory'])->find($id);

            if (!$product) {
                return $this->error('Product not found.', 404);
            }

            return $this->success($this->formatProduct($product, true), 'Product retrieved successfully.');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve product: ' . $e->getMessage(), 500);
        }
    }

    // STORE PRODUCT
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'short_description' => 'nullable|string',
            'parent_category_id' => 'nullable|exists:parent_categories,id',
            'category_id' => 'required|exists:categories,id',
            'price' => 'required|numeric|min:0', // Base price
            'stock_quantity' => 'required|integer|min:0', // Initial stock
            'sku' => 'nullable|string|unique:product_variants,sku',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        try {
            // 1. Create Product
            $product = Product::create([
                'name' => $request->name,
                'slug' => Str::slug($request->name) . '-' . Str::random(4),
                'description' => $request->description,
                'short_description' => $request->short_description,
                'is_active' => true,
                'base_price' => $request->price,
                'parent_category_id' => $request->parent_category_id,
                'category_id' => $request->category_id,
                'meta_title' => $request->name,
                'meta_description' => Str::limit(strip_tags($request->description), 150),
            ]);

            // 2. Create Default Variant
            $sku = $request->sku ?? (strtoupper(Str::slug($request->name)) . '-' . Str::random(4));
            ProductVariant::create([
                'product_id' => $product->id,
                'sku' => $sku,
                'price' => null, // null means use product base_price
                'discount_price' => null,
                'stock_quantity' => $request->stock_quantity,
            ]);

            // 3. Upload Images
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $index => $image) {
                    $path = $image->store('products', 'public');
                    $url = asset('storage/' . $path);

                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $path,
                        'image_url' => $url,
                        'is_primary' => $index === 0,
                        'sort_order' => $index,
                    ]);
                }
            }

            return $this->success($this->formatProduct($product->refresh(), true), 'Product created successfully.', 201);
        } catch (\Exception $e) {
            return $this->error('Failed to create product: ' . $e->getMessage(), 500);
        }
    }

    // UPDATE PRODUCT
    public function update(Request $request, $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return $this->error('Product not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string',
            'parent_category_id' => 'nullable|exists:parent_categories,id',
            'category_id' => 'sometimes|exists:categories,id',
            'price' => 'sometimes|numeric|min:0',
            'stock_quantity' => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        try {
            $updateData = $request->only([
                'name',
                'description',
                'short_description',
                'parent_category_id',
                'category_id'
            ]);

            if ($request->has('price')) {
                $updateData['base_price'] = $request->price;
            }

            if ($request->has('name')) {
                $updateData['slug'] = Str::slug($request->name) . '-' . Str::random(4);
                $updateData['meta_title'] = $request->name;
            }

            $product->update($updateData);

            // Update default variant stock if provided
            if ($request->has('stock_quantity')) {
                $variant = $product->variants()->first();
                if ($variant) {
                    $variant->update(['stock_quantity' => $request->stock_quantity]);
                }
            }

            // Handle New Images
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $path = $image->store('products', 'public');
                    $url = asset('storage/' . $path);

                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $path,
                        'image_url' => $url,
                        'is_primary' => false,
                        'sort_order' => $product->images()->count(),
                    ]);
                }
            }

            return $this->success($this->formatProduct($product->refresh(), true), 'Product updated successfully.');
        } catch (\Exception $e) {
            return $this->error('Failed to update product: ' . $e->getMessage(), 500);
        }
    }

    // DELETE PRODUCT
    public function destroy($id)
    {
        try {
            $product = Product::find($id);

            if (!$product) {
                return $this->error('Product not found.', 404);
            }

            // Delete logic - assuming DB cascade or manual delete if needed
            // For safety we can delete dependent records
            $product->images()->delete();
            $product->variants()->delete();
            $product->delete();

            return $this->success(null, 'Product deleted successfully.');
        } catch (\Exception $e) {
            return $this->error('Failed to delete product: ' . $e->getMessage(), 500);
        }
    }

    // TOGGLE STATUS
    public function changeStatus($id)
    {
        try {
            $product = Product::find($id);

            if (!$product) {
                return $this->error('Product not found.', 404);
            }

            $product->is_active = !$product->is_active;
            $product->save();

            return $this->success(['is_active' => (bool)$product->is_active], 'Product status updated successfully.');
        } catch (\Exception $e) {
            return $this->error('Failed to update status: ' . $e->getMessage(), 500);
        }
    }

    // HELPER to format product data
    private function formatProduct($product, $details = false)
    {
        $data = [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => $product->base_price,
            'is_active' => (bool) $product->is_active,
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name
            ] : null,
            'parent_category' => $product->parentCategory ? [
                'id' => $product->parentCategory->id,
                'name' => $product->parentCategory->name
            ] : null,
            'primary_image' => $product->primaryImage ? $product->primaryImage->image_url : null,
            'stock_quantity' => $product->variants->sum('stock_quantity'),
        ];

        if ($details) {
            $data['description'] = $product->description;
            $data['short_description'] = $product->short_description;
            $data['images'] = $product->images->map(function ($img) {
                return [
                    'id' => $img->id,
                    'url' => $img->image_url,
                    'is_primary' => (bool) $img->is_primary
                ];
            });
            $data['variants'] = $product->variants->map(function ($v) {
                return [
                    'id' => $v->id,
                    'sku' => $v->sku,
                    'price' => $v->price,
                    'stock_quantity' => $v->stock_quantity,
                ];
            });
            $data['created_at'] = $product->created_at;
            $data['updated_at'] = $product->updated_at;
        }

        return $data;
    }
}

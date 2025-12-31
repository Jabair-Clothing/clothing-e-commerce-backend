<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use App\Models\ProductSku;
use App\Models\ProductSkuAttribute;
use App\Models\AttributeValue;
use App\Models\ProductImage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ProductController extends Controller
{
    // ... helper functions omitted for brevity, keeping them as is ...
    // Helper functions
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

            $query = Product::with(['primaryImage', 'skus.attributes', 'category', 'parentCategory'])
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
            // Load skus.attributes.attribute AND skus.attributes.attributeValue
            $product = Product::with(['images', 'skus.attributes.attribute', 'skus.attributes.attributeValue', 'category', 'parentCategory'])->find($id);

            if (!$product) {
                return $this->error('Product not found.', 404);
            }

            return $this->success($this->formatProduct($product, true), 'Product retrieved successfully.');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve product: ' . $e->getMessage(), 500);
        }
    }

    // ... store and update methods will be updated in next chunk ... 

    // DELETE PRODUCT
    public function destroy($id)
    {
        try {
            $product = Product::find($id);

            if (!$product) {
                return $this->error('Product not found.', 404);
            }

            // Cleanup logic
            $product->images()->delete();
            $product->skus()->each(function ($sku) {
                $sku->attributes()->delete(); // clear pivot/related model
                $sku->delete();
            });
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

            // Variants validation (renamed to variants in API but handling as skus internally)
            'variants' => 'nullable|array',
            'variants.*.sku' => 'nullable|string',
            'variants.*.price' => 'nullable|numeric|min:0',
            'variants.*.stock_quantity' => 'required_with:variants|integer|min:0',
            'variants.*.attribute_values' => 'nullable|array',
            'variants.*.attribute_values.*' => 'exists:attribute_values,id',

            // If no variants, simple stock
            'stock_quantity' => 'required_without:variants|integer|min:0',

            // Images
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        DB::beginTransaction();
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

            // 2. Create SKUs (Variants)
            if ($request->has('variants') && is_array($request->variants) && count($request->variants) > 0) {
                foreach ($request->variants as $variantData) {
                    $skuCode = $variantData['sku'] ?? (strtoupper(Str::slug($request->name)) . '-' . Str::random(6));

                    $sku = ProductSku::create([
                        'product_id' => $product->id,
                        'sku' => $skuCode,
                        'price' => $variantData['price'] ?? null,
                        'quantity' => $variantData['stock_quantity'] ?? 0,
                    ]);

                    // Attach Attributes
                    if (isset($variantData['attribute_values']) && is_array($variantData['attribute_values'])) {
                        foreach ($variantData['attribute_values'] as $attrValueId) {
                            $attrValue = AttributeValue::find($attrValueId);
                            if ($attrValue) {
                                ProductSkuAttribute::create([
                                    'product_sku_id' => $sku->id,
                                    'attribute_id' => $attrValue->attribute_id,
                                    'attribute_value_id' => $attrValueId
                                ]);
                            }
                        }
                    }
                }
            } else {
                // Default Single SKU
                $skuCode = strtoupper(Str::slug($request->name)) . '-' . Str::random(4);
                ProductSku::create([
                    'product_id' => $product->id,
                    'sku' => $skuCode,
                    'price' => null, // Use base price
                    'quantity' => $request->stock_quantity ?? 0,
                ]);
            }

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

            DB::commit();
            return $this->success($this->formatProduct($product->refresh(), true), 'Product created successfully.', 201);
        } catch (\Exception $e) {
            DB::rollBack();
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

            // Variants update
            'variants' => 'nullable|array',
            'variants.*.id' => 'nullable|exists:product_skus,id', // Check against product_skus
            'variants.*.sku' => 'nullable|string',
            'variants.*.price' => 'nullable|numeric|min:0',
            'variants.*.stock_quantity' => 'required_with:variants|integer|min:0',
            'variants.*.attribute_values' => 'nullable|array',
            'variants.*.attribute_values.*' => 'exists:attribute_values,id',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed.', 422, $validator->errors());
        }

        DB::beginTransaction();
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

            // Handle Variants (SKUs)
            if ($request->has('variants') && is_array($request->variants)) {

                foreach ($request->variants as $variantData) {
                    if (isset($variantData['id'])) {
                        // Update existing SKU
                        $sku = ProductSku::find($variantData['id']);
                        if ($sku && $sku->product_id == $product->id) {
                            $sku->update([
                                'sku' => $variantData['sku'] ?? $sku->sku,
                                'price' => $variantData['price'] ?? $sku->price,
                                'quantity' => $variantData['stock_quantity'] ?? $sku->quantity,
                            ]);

                            if (isset($variantData['attribute_values'])) {
                                // Sync attributes: easier to delete all and recreate for simplicity or intricate sync?
                                // Let's delete old and create new to ensure IDs match
                                $sku->attributes()->delete();
                                foreach ($variantData['attribute_values'] as $attrValueId) {
                                    $attrValue = AttributeValue::find($attrValueId);
                                    if ($attrValue) {
                                        ProductSkuAttribute::create([
                                            'product_sku_id' => $sku->id,
                                            'attribute_id' => $attrValue->attribute_id,
                                            'attribute_value_id' => $attrValueId
                                        ]);
                                    }
                                }
                            }
                        }
                    } else {
                        // Create New SKU
                        $skuCode = $variantData['sku'] ?? (strtoupper(Str::slug($product->name)) . '-' . Str::random(6));
                        $sku = ProductSku::create([
                            'product_id' => $product->id,
                            'sku' => $skuCode,
                            'price' => $variantData['price'] ?? null,
                            'quantity' => $variantData['stock_quantity'] ?? 0,
                        ]);

                        if (isset($variantData['attribute_values'])) {
                            foreach ($variantData['attribute_values'] as $attrValueId) {
                                $attrValue = AttributeValue::find($attrValueId);
                                if ($attrValue) {
                                    ProductSkuAttribute::create([
                                        'product_sku_id' => $sku->id,
                                        'attribute_id' => $attrValue->attribute_id,
                                        'attribute_value_id' => $attrValueId
                                    ]);
                                }
                            }
                        }
                    }
                }
            } elseif ($request->has('stock_quantity')) {
                // Simple update for default SKU
                $sku = $product->skus()->first();
                if ($sku) {
                    $sku->update(['quantity' => $request->stock_quantity]);
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

            DB::commit();
            return $this->success($this->formatProduct($product->refresh(), true), 'Product updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to update product: ' . $e->getMessage(), 500);
        }
    }



    // HELPER to format product data
    // formatProduct helper updated
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
            'stock_quantity' => $product->skus->sum('quantity'),
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
            $data['skus'] = $product->skus->map(function ($sku) {
                return [
                    'id' => $sku->id,
                    'sku' => $sku->sku,
                    'price' => $sku->price,
                    'quantity' => $sku->quantity,
                    'attributes' => $sku->attributes->map(function ($skuAttr) {
                        return [
                            'id' => $skuAttr->attribute_value_id,
                            'name' => $skuAttr->attributeValue->name ?? null,
                            'attribute_name' => $skuAttr->attribute->name ?? null,
                            'code' => $skuAttr->attributeValue->code ?? null
                        ];
                    })
                ];
            });
            $data['created_at'] = $product->created_at;
            $data['updated_at'] = $product->updated_at;
        }

        return $data;
    }
}

<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use App\Models\ProductSku;
use App\Models\ProductSkuAttribute;
use App\Models\AttributeValue;
use App\Models\ProductImage;
use Illuminate\Support\Str;
use App\Traits\ApiResponser;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    use ApiResponser;

    /**
     * Display a listing of the products.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('limit', 10);
            $search = $request->input('search');
            $categoryId = $request->input('category_id');
            $parentCategoryId = $request->input('parent_category_id');
            $status = $request->input('status');

            $query = Product::with(['primaryImage', 'skus', 'category', 'parentCategory'])
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
            $formattedData = $products->getCollection()->transform(function ($product) {
                return $this->formatProduct($product);
            });

            // Replace collection with formatted data
            $products->setCollection($formattedData);

            return $this->success($products, 'Products retrieved successfully.');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve products.', 500, $e->getMessage());
        }
    }

    /**
     * Store a newly created product in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'short_description' => 'nullable|string',
            'parent_category_id' => 'nullable|exists:parent_categories,id',
            'category_id' => 'required|exists:categories,id',
            'price' => 'required|numeric|min:0', // Base price

            // Images
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:2048',

            // Variants Logic
            // If strict variants are passed
            'variants' => 'nullable|array',
            'variants.*.sku' => 'nullable|string',
            'variants.*.price' => 'nullable|numeric|min:0',
            'variants.*.discount_price' => 'nullable|numeric|min:0',
            'variants.*.quantity' => 'required_with:variants|integer|min:0',
            'variants.*.attributes' => 'nullable|array', // Array of attribute_value_ids
            'variants.*.attributes.*' => 'exists:attribute_values,id',

            // Simple product stock (if no variants)
            'quantity' => 'required_without:variants|integer|min:0',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
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

            // 2. Handle SKUs
            if ($request->has('variants') && is_array($request->variants) && count($request->variants) > 0) {
                foreach ($request->variants as $index => $variantData) {
                    $skuCode = $variantData['sku'] ?? (strtoupper(Str::slug($request->name)) . '-' . Str::random(6));

                    // Handle Variant Image
                    $skuImagePath = null;
                    $skuImageUrl = null;

                    // Check for file in variants array (variants[0][image])
                    // When using array inputs, Laravel organizes files in request->file('variants')[index]['image']
                    // or request->all()['variants'][index]['image'] if it's an UploadedFile object.
                    // Safer to check input array if it contains uploaded file instances.

                    $productImageId = null;

                    if (isset($variantData['image']) && $request->hasFile("variants.{$index}.image")) {
                        $image = $request->file("variants.{$index}.image");
                        $skuImagePath = $image->store('product_variants', 'public');
                        $skuImageUrl = asset('storage/' . $skuImagePath);

                        // Create ProductImage record
                        $productImage = ProductImage::create([
                            'product_id' => $product->id,
                            'image_path' => $skuImagePath,
                            'image_url' => $skuImageUrl,
                            'is_primary' => false,
                            'sort_order' => $product->images()->count() + 1,
                        ]);

                        $productImageId = $productImage->id;
                    }

                    $sku = ProductSku::create([
                        'product_id' => $product->id,
                        'sku' => $skuCode,
                        'price' => $variantData['price'] ?? $request->price, // Default to base price if not set
                        'quantity' => $variantData['quantity'] ?? 0,
                        'product_image_id' => $productImageId,
                    ]);

                    // Attach Attributes
                    if (isset($variantData['attributes']) && is_array($variantData['attributes'])) {
                        foreach ($variantData['attributes'] as $attrValueId) {
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
                // Default Single SKU (Simple Product)
                $skuCode = strtoupper(Str::slug($request->name)) . '-' . Str::random(4);
                ProductSku::create([
                    'product_id' => $product->id,
                    'sku' => $skuCode,
                    'price' => $request->price,
                    'quantity' => $request->quantity ?? 0,
                ]);
            }

            // 3. Upload Images
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $index => $image) {
                    $path = $image->store('products', 'public');
                    // Assuming you have storage linked: php artisan storage:link
                    // Or standard asset url
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
            return $this->created($this->formatProduct($product->refresh(), true), 'Product created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to create product.', 500, $e->getMessage());
        }
    }

    /**
     * Display the specified product.
     */
    public function show($id)
    {
        try {
            $product = Product::with(['images', 'skus.productImage', 'skus.attributes.attribute', 'skus.attributes.attributeValue', 'category', 'parentCategory'])->find($id);

            if (!$product) {
                return $this->error('Product not found.', 404);
            }

            return $this->success($this->formatProduct($product, true), 'Product retrieved successfully.');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve product.', 500, $e->getMessage());
        }
    }

    /**
     * Update the specified product in storage.
     */
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
            'is_active' => 'boolean',
            'base_price' => 'nullable|numeric|min:0',
            'parent_category_id' => 'nullable|exists:parent_categories,id',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        DB::beginTransaction();
        try {
            // Prepare data for update
            $data = $request->only([
                'name',
                'description',
                'short_description',
                'is_active',
                'base_price',
                'parent_category_id',
                'category_id'
            ]);

            // Auto-generate meta data if name is present
            if ($request->has('name')) {
                $data['slug'] = Str::slug($request->name) . '-' . Str::random(4);
                $data['meta_title'] = $request->name;
                $data['meta_keywords'] = str_replace(' ', ', ', $request->name);
            }

            // Auto-generate meta description if description is present
            if ($request->has('description')) {
                $data['meta_description'] = Str::limit(strip_tags($request->description), 150);
            }

            $product->update($data);

            DB::commit();
            return $this->success($this->formatProduct($product->refresh(), true), 'Product updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to update product.', 500, $e->getMessage());
        }
    }

    /**
     * Remove the specified product from storage.
     */
    public function destroy($id)
    {
        try {
            $product = Product::with(['images', 'skus'])->find($id);

            if (!$product) {
                return $this->error('Product not found.', 404);
            }

            // 1. Delete Product Images (Files + Records)
            if ($product->images && $product->images->count() > 0) {
                foreach ($product->images as $image) {
                    if ($image->image_path && Storage::disk('public')->exists($image->image_path)) {
                        Storage::disk('public')->delete($image->image_path);
                    }
                    $image->delete();
                }
            }

            // 2. Delete SKUs (and implicitly their attributes via cascade if set, but we can do manually for safety)
            // SKUs might have individual images too if they weren't in the main images list (though logic puts them there too now)
            foreach ($product->skus as $sku) {
                // If SKU has an image path that ISN'T in the main product images table (unlikely with current store logic, 
                // but possible from older logic or direct DB edits), we should check.
                // However, current logic saves to ProductImage, so strict deleting from ProductImage loop above covers it.
                // Just to be safe, if we had standalone sku images in future:
                if ($sku->image_path && Storage::disk('public')->exists($sku->image_path)) {
                    // Check if this file was already deleted via product->images loop?
                    // If it points to same file, Storage::delete won't error if missing usually, or we can check exists.
                    Storage::disk('public')->delete($sku->image_path);
                }

                $sku->attributes()->delete(); // Clear pivot
                $sku->delete();
            }

            $product->delete();

            return $this->success(null, 'Product and associated data deleted successfully.');
        } catch (\Exception $e) {
            return $this->error('Failed to delete product.', 500, $e->getMessage());
        }
    }

    /**
     * Update the product status.
     */
    public function toggleStatus(Request $request, $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return $this->error('Product not found.', 404);
        }

        try {
            $product->update(['is_active' => !$product->is_active]);
            return $this->success(['is_active' => (bool)$product->is_active], 'Product status updated successfully.');
        } catch (\Exception $e) {
            return $this->error('Failed to update product status.', 500, $e->getMessage());
        }
    }

    /**
     * Helper to format product data.
     */
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
            'stock_quantity' => $product->skus->sum('quantity'), // Sum of all SKU quantities
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
                    'image' => $sku->productImage ? $sku->productImage->image_url : null,
                    'attributes' => $sku->attributes->map(function ($skuAttr) {
                        return [
                            'attribute_id' => $skuAttr->attribute_id,
                            'attribute_name' => $skuAttr->attribute->name ?? null,
                            'value_id' => $skuAttr->attribute_value_id,
                            'value_name' => $skuAttr->attributeValue->name ?? null,
                            'value_code' => $skuAttr->attributeValue->code ?? null
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

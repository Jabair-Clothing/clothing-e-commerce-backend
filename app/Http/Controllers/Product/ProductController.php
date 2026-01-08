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
            $perPage          = $request->input('limit', 10);
            $search           = $request->input('search');
            $categoryId       = $request->input('category_id');
            $parentCategoryId = $request->input('parent_category_id');
            $status           = $request->input('status');

            $query = Product::with([
                'primaryImage',
                'category',
                'parentCategory',
                'skus.skuAttributes.attribute',
                'skus.skuAttributes.attributeValue',
            ])
                ->orderBy('created_at', 'desc');

            if (!empty($search)) {
                $query->where('name', 'like', '%' . $search . '%');
            }

            if (!empty($categoryId)) {
                $query->where('category_id', $categoryId);
            }

            if (!empty($parentCategoryId)) {
                $query->where('parent_category_id', $parentCategoryId);
            }

            if ($status !== null) {
                $query->where('is_active', filter_var($status, FILTER_VALIDATE_BOOLEAN));
            }

            $products = $query->paginate($perPage);

            // format collection
            $formatted = $products->getCollection()->map(function ($product) {
                return $this->formatProducts($product);
            });

            $products->setCollection($formatted);

            return $this->success($products, 'Products retrieved successfully.');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve products.', 500, $e->getMessage());
        }
    }

    private function formatProducts($product)
    {
        // Get all SKUs
        $activeSkus = $product->skus;

        // Calculate total stock
        $stockQty = $activeSkus->sum('quantity');

        // Get minimum regular price and minimum discount price from SKUs
        $minRegularPrice = $activeSkus->pluck('price')->filter()->min();
        $minDiscountPrice = $activeSkus->pluck('discount_price')->filter()->min();

        // The final price to display (discount if available, otherwise regular)
        $finalMinPrice = $minDiscountPrice ?? $minRegularPrice;

        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => $finalMinPrice ? (string) $finalMinPrice : (string) $product->base_price,
            'regular_price' => $minRegularPrice ? (string) $minRegularPrice : (string) $product->base_price,
            'discount_price' => $minDiscountPrice ? (string) $minDiscountPrice : null,
            'base_price' => (string) $product->base_price,
            'is_active' => (bool) $product->is_active,

            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
            ] : null,

            'parent_category' => $product->parentCategory ? [
                'id' => $product->parentCategory->id,
                'name' => $product->parentCategory->name,
            ] : null,

            'primary_image' => $product->primaryImage?->image_url,

            'stock_quantity' => (int) $stockQty,

            // add skus with attributes
            'skus' => $activeSkus
                ->values()
                ->map(function ($sku) {

                    $attributes = $sku->skuAttributes
                        ->map(function ($skuAttr) {
                            return [
                                'attribute_id'   => $skuAttr->attribute_id,
                                'attribute_name' => $skuAttr->attribute?->name,

                                'value_id'       => $skuAttr->attribute_value_id,
                                'value_name'     => $skuAttr->attributeValue?->name,
                                'value_code'     => $skuAttr->attributeValue?->code,
                            ];
                        })
                        ->filter(fn($a) => $a['attribute_name'] && $a['value_name'])
                        ->values();

                    return [
                        'id'       => $sku->id,
                        'sku'      => $sku->sku,
                        'price'    => (string) $sku->price,
                        'discount_price' => $sku->discount_price ? (string) $sku->discount_price : null,
                        'final_price' => (string) ($sku->discount_price ?? $sku->price),
                        'quantity' => (int) $sku->quantity,
                        'attributes' => $attributes,
                    ];
                }),
        ];
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
                        // product_image_id removed from ProductSku
                    ]);

                    // Attach Attributes
                    if (isset($variantData['attributes']) && is_array($variantData['attributes'])) {
                        foreach ($variantData['attributes'] as $attrValueId) {
                            $attrValue = AttributeValue::find($attrValueId);
                            if ($attrValue) {
                                ProductSkuAttribute::create([
                                    'product_sku_id' => $sku->id,
                                    'attribute_id' => $attrValue->attribute_id,
                                    'attribute_value_id' => $attrValueId,
                                    'product_image_id' => $productImageId // Link image to attribute. Note: This links it to ALL attributes of this SKU if image is present.
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
            $product = Product::with([
                'images',
                'skus.productImage',
                'skus.skuAttributes.attribute',
                'skus.skuAttributes.attributeValue',
                'skus.skuAttributes.productImage',
                'category',
                'parentCategory'
            ])->find($id);

            if (!$product) {
                return $this->error('Product not found.', 404);
            }

            return $this->success($this->formatProduct($product, true), 'Product retrieved successfully.');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve product.', 500, $e->getMessage());
        }
    }

    public function productView(Request $request)
    {
        try {
            $request->validate([
                'product_id' => 'required|exists:products,id',
            ]);

            $product = Product::find($request->product_id);

            if (!$product) {
                return $this->notFound('Product not found');
            }

            $product->increment('count_view');

            return $this->success(
                ['count_view' => $product->count_view],
                'Product view counted'
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationError($e->errors());
        } catch (\Exception $e) {
            return $this->serverError();
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
            'quantity' => 'nullable|integer|min:0',
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
                'quantity',
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

            // Update quantity for simple products (single SKU)
            if ($request->has('quantity')) {
                // Check if product has exactly one SKU or if it's considered a simple product
                // Generally simple products have 1 SKU.
                if ($product->skus()->count() === 1) {
                    $product->skus()->first()->update(['quantity' => $request->quantity]);
                }
            }

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

                $sku->skuAttributes()->delete(); // Clear pivot
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
        // Get minimum prices from SKUs
        $minRegularPrice = $product->skus->pluck('price')->filter()->min();
        $minDiscountPrice = $product->skus->pluck('discount_price')->filter()->min();
        $finalMinPrice = $minDiscountPrice ?? $minRegularPrice;

        $data = [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => $finalMinPrice ? (string) $finalMinPrice : (string) $product->base_price,
            'regular_price' => $minRegularPrice ? (string) $minRegularPrice : (string) $product->base_price,
            'discount_price' => $minDiscountPrice ? (string) $minDiscountPrice : null,
            'base_price' => (string) $product->base_price,
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
            'stock_quantity' => (int) $product->skus->sum('quantity'),
        ];

        if ($details) {
            $data['description'] = $product->description;
            $data['short_description'] = $product->short_description;
            $data['images'] = $product->images->map(function ($img) {
                return [
                    'id' => $img->id,
                    'url' => $img->image_url,
                    'is_primary' => (bool) $img->is_primary,
                    'sort_order' => $img->sort_order ?? 0
                ];
            });
            $data['skus'] = $product->skus->map(function ($sku) {
                // Find first attribute with an image to use as "SKU Image" for main display
                $skuImage = null;
                foreach ($sku->skuAttributes as $attr) {
                    if ($attr->productImage) {
                        $skuImage = $attr->productImage->image_url;
                        break;
                    }
                }

                return [
                    'id' => $sku->id,
                    'sku' => $sku->sku,
                    'price' => (string) $sku->price,
                    'discount_price' => $sku->discount_price ? (string) $sku->discount_price : null,
                    'final_price' => (string) ($sku->discount_price ?? $sku->price),
                    'quantity' => (int) $sku->quantity,
                    'image' => $skuImage, // Fallback to attribute image
                    'attributes' => $sku->skuAttributes->map(function ($skuAttr) {
                        return [
                            'attribute_id' => $skuAttr->attribute_id,
                            'attribute_name' => $skuAttr->attribute->name ?? null,
                            'value_id' => $skuAttr->attribute_value_id,
                            'value_name' => $skuAttr->attributeValue->name ?? null,
                            'value_code' => $skuAttr->attributeValue->code ?? null,
                            'product_image_id' => $skuAttr->product_image_id,
                            'image_url' => $skuAttr->productImage ? $skuAttr->productImage->image_url : null,
                        ];
                    })
                ];
            });
            $data['created_at'] = $product->created_at;
            $data['updated_at'] = $product->updated_at;
        }

        return $data;
    }

    /**
     * Upload an image for a product.
     */
    public function uploadImage(Request $request, $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return $this->error('Product not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048',
            'product_sku_attribute_id' => 'nullable|exists:product_sku_attributes,id',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        try {
            DB::beginTransaction();

            $image = $request->file('image');
            $path = $image->store('products', 'public');
            $url = asset('storage/' . $path);

            // Create ProductImage
            $productImage = ProductImage::create([
                'product_id' => $product->id,
                'image_path' => $path,
                'image_url' => $url,
                'is_primary' => false,
                'sort_order' => $product->images()->count() + 1,
            ]);

            // Link to SKU Attribute if provided
            if ($request->product_sku_attribute_id) {
                // Verify this attribute belongs to one of the product's SKUs
                // Ideally check relation, but simpler query:
                $skuAttr = ProductSkuAttribute::where('id', $request->product_sku_attribute_id)
                    ->whereHas('productSku', function ($query) use ($product) {
                        $query->where('product_id', $product->id);
                    })
                    ->first();

                if ($skuAttr) {
                    $skuAttr->update(['product_image_id' => $productImage->id]);
                }
            }

            DB::commit();
            return $this->created($productImage, 'Image uploaded successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to upload image.', 500, $e->getMessage());
        }
    }

    /**
     * Update image details.
     */
    public function updateImage(Request $request, $id, $image_id)
    {
        $product = Product::find($id);

        if (!$product) {
            return $this->error('Product not found.', 404);
        }

        $image = ProductImage::where('product_id', $id)->find($image_id);

        if (!$image) {
            return $this->error('Image not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'is_primary' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        try {
            DB::beginTransaction();

            // Handle Primary Image Logic
            if ($request->has('is_primary') && $request->is_primary) {
                // Set all other images to not primary
                ProductImage::where('product_id', $id)
                    ->where('id', '!=', $image_id)
                    ->update(['is_primary' => false]);
            }

            $image->update($request->only(['is_primary', 'sort_order']));

            DB::commit();
            return $this->success($image, 'Image updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to update image.', 500, $e->getMessage());
        }
    }

    /**
     * Update product SKU details.
     */
    public function updateSku(Request $request, $id, $sku_id)
    {
        $product = Product::find($id);

        if (!$product) {
            return $this->error('Product not found.', 404);
        }

        $sku = ProductSku::where('product_id', $id)->find($sku_id);

        if (!$sku) {
            return $this->error('SKU not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'quantity' => 'nullable|integer|min:0',
            'price' => 'nullable|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        try {
            $sku->update($request->only(['quantity', 'price', 'discount_price']));

            return $this->success($sku, 'SKU updated successfully.');
        } catch (\Exception $e) {
            return $this->error('Failed to update SKU.', 500, $e->getMessage());
        }
    }

    /**
     * Add a new SKU to a product.
     */
    public function addSku(Request $request, $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return $this->error('Product not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'variants' => 'required|array',
            'variants.*.sku' => 'nullable|string',
            'variants.*.price' => 'nullable|numeric|min:0',
            'variants.*.quantity' => 'required_with:variants|integer|min:0',
            'variants.*.discount_price' => 'nullable|numeric|min:0',
            'variants.*.image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'variants.*.attributes' => 'nullable|array',
            'variants.*.attributes.*' => 'exists:attribute_values,id',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        DB::beginTransaction();
        try {
            $createdSkus = [];

            foreach ($request->variants as $index => $variantData) {
                $skuCode = $variantData['sku'] ?? (strtoupper(Str::slug($product->name)) . '-' . Str::random(6));

                // Handle Image
                $productImageId = null;
                if (isset($variantData['image']) && $request->hasFile("variants.{$index}.image")) {
                    $image = $request->file("variants.{$index}.image");
                    $path = $image->store('product_variants', 'public');
                    $url = asset('storage/' . $path);

                    $productImage = ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $path,
                        'image_url' => $url,
                        'is_primary' => false,
                        'sort_order' => $product->images()->count() + 1,
                    ]);
                    $productImageId = $productImage->id;
                }

                // Create SKU
                $sku = ProductSku::create([
                    'product_id' => $product->id,
                    'sku' => $skuCode,
                    'price' => $variantData['price'] ?? $product->base_price,
                    'quantity' => $variantData['quantity'] ?? 0,
                    'discount_price' => $variantData['discount_price'] ?? null,
                ]);

                // Attach Attributes
                if (isset($variantData['attributes']) && is_array($variantData['attributes'])) {
                    foreach ($variantData['attributes'] as $attrValueId) {
                        $attrValue = AttributeValue::find($attrValueId);
                        if ($attrValue) {
                            ProductSkuAttribute::create([
                                'product_sku_id' => $sku->id,
                                'attribute_id' => $attrValue->attribute_id,
                                'attribute_value_id' => $attrValueId,
                                'product_image_id' => $productImageId // Link image to attribute
                            ]);
                        }
                    }
                }

                $createdSkus[] = $sku->load('skuAttributes.attribute', 'skuAttributes.attributeValue', 'skuAttributes.productImage');
            }

            DB::commit();
            return $this->created($createdSkus, 'SKUs added successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to add SKUs.', 500, $e->getMessage());
        }
    }

    /**
     * Delete SKU data (SKU or Attribute).
     */
    public function deleteSkuData(Request $request, $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return $this->error('Product not found.', 404);
        }

        $validator = Validator::make($request->all(), [
            'sku_id' => 'nullable|exists:product_skus,id',
            'sku_attribute_id' => 'nullable|exists:product_sku_attributes,id',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        if (!$request->sku_id && !$request->sku_attribute_id) {
            return $this->error('Either sku_id or sku_attribute_id is required.', 400);
        }

        try {
            DB::beginTransaction();

            if ($request->sku_id) {
                // Delete SKU and its attributes
                $sku = ProductSku::where('product_id', $product->id)->where('id', $request->sku_id)->first();
                if ($sku) {
                    $sku->skuAttributes()->delete(); // Delete linked attributes first
                    $sku->delete();
                    DB::commit();
                    return $this->success(null, 'SKU and its attributes deleted successfully.');
                } else {
                    DB::rollBack();
                    return $this->error('SKU not found for this product.', 404);
                }
            }

            if ($request->sku_attribute_id) {
                // Delete specific SKU attribute
                // Verify it belongs to this product indirectly
                $skuAttr = ProductSkuAttribute::where('id', $request->sku_attribute_id)
                    ->whereHas('productSku', function ($q) use ($product) {
                        $q->where('product_id', $product->id);
                    })->first();

                if ($skuAttr) {
                    $skuAttr->delete();
                    DB::commit();
                    return $this->success(null, 'SKU attribute deleted successfully.');
                } else {
                    DB::rollBack();
                    return $this->error('SKU attribute not found for this product.', 404);
                }
            }

            DB::commit(); // Should not reach here due to logic check above, but safe keep.
            return $this->success(null, 'Operation completed.');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to delete SKU data.', 500, $e->getMessage());
        }
    }

    /**
     * Delete a product image.
     */
    public function deleteImage($id, $image_id)
    {
        $product = Product::find($id);

        if (!$product) {
            return $this->error('Product not found.', 404);
        }

        $image = ProductImage::where('product_id', $id)->find($image_id);

        if (!$image) {
            return $this->error('Image not found.', 404);
        }

        try {
            // Delete file
            if ($image->image_path && Storage::disk('public')->exists($image->image_path)) {
                Storage::disk('public')->delete($image->image_path);
            }

            // Remove reference from SKU Attributes
            ProductSkuAttribute::where('product_image_id', $image->id)->update(['product_image_id' => null]);

            $image->delete();

            return $this->success(null, 'Image deleted successfully.');
        } catch (\Exception $e) {
            return $this->error('Failed to delete image.', 500, $e->getMessage());
        }
    }

    /**
     * Get SKU attributes for a product.
     */
    public function getSkuAttributes($id)
    {
        $product = Product::with(['skus.skuAttributes.attribute', 'skus.skuAttributes.attributeValue', 'skus.skuAttributes.productImage'])->find($id);

        if (!$product) {
            return $this->error('Product not found.', 404);
        }

        $data = $product->skus->map(function ($sku) {
            return [
                'sku_id' => $sku->id,
                'sku_code' => $sku->sku,
                'attributes' => $sku->skuAttributes->map(function ($skuAttr) {
                    return [
                        'sku_attribute_id' => $skuAttr->id,
                        'attribute_name' => $skuAttr->attribute->name ?? null,
                        'value_name' => $skuAttr->attributeValue->name ?? null,
                        'product_image_id' => $skuAttr->product_image_id,
                        'image_url' => $skuAttr->productImage ? $skuAttr->productImage->image_url : null,
                    ];
                })
            ];
        });

        return $this->success($data, 'SKU attributes retrieved successfully.');
    }
}

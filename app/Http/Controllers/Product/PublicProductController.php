<?php

namespace App\Http\Controllers\product;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Item;
use App\Models\CategoryProductList;
use App\Models\Category;
use App\Models\Order_list;
use Illuminate\Support\Facades\DB;
use App\Traits\ApiResponser;
use App\Services\FileUploadService;

class PublicProductController extends Controller
{
    use ApiResponser;
    // Show the all active product
    public function index(Request $request)
    {
        try {
            // Get parameters from request
            $perPage = $request->input('limit');
            $currentPage = $request->input('page');
            $search = $request->input('search');
            $minPrice = $request->input('min_price');
            $maxPrice = $request->input('max_price');
            $categoryId = $request->input('category_id');
            $categorySlug = $request->input('category_slug');

            // Base query to fetch products with all related images of type 'product'
            $query = Item::with(['images' => function ($query) {
                $query->where('type', 'product')->orderBy('id', 'asc');
            }])->orderBy('created_at', 'desc');

            // Exclude items with status 0
            $query->where('status', '!=', 0);

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

            // Apply category filter if category_id or category_slug is provided
            if ($categoryId || $categorySlug) {
                $query->whereHas('categories', function ($categoryQuery) use ($categoryId, $categorySlug) {
                    if ($categoryId) {
                        $categoryQuery->where('category_id', $categoryId);
                    }
                    if ($categorySlug) {
                        $categoryQuery->whereHas('category', function ($catQuery) use ($categorySlug) {
                            $catQuery->where('slug', $categorySlug);
                        });
                    }
                });
            }

            // If pagination parameters are provided, apply pagination
            if ($perPage && $currentPage) {
                // Validate pagination parameters
                if (!is_numeric($perPage) || !is_numeric($currentPage) || $perPage <= 0 || $currentPage <= 0) {
                    return $this->error('Invalid pagination parameters', 400, 'Invalid pagination parameters');
                }

                // Apply pagination
                $products = $query->paginate($perPage, ['*'], 'page', $currentPage);

                // Format the response with pagination data
                $formattedProducts = $products->map(function ($product) {
                    // Collect all image paths for this product
                    $imagePath = null;
                    if ($product->images->isNotEmpty()) {
                        $imagePath = FileUploadService::getUrls([$product->images->first()->path])[0];
                    }


                    // Get product categories
                    $categories = $product->categories->map(function ($categoryProduct) {
                        return [
                            'id' => $categoryProduct->category->id,
                            'name' => $categoryProduct->category->name,
                            'slug' => $categoryProduct->category->slug ?? null,
                        ];
                    })->toArray();

                    // Calculate discounted price and percentage
                    $discountedPrice = null;
                    $discountPercentage = null;
                    if ($product->discount && $product->price) {
                        $discountedPrice = $product->price - $product->discount;
                        $discountPercentage = round(($product->discount / $product->price) * 100, 2); // 2 decimal
                    }

                    return [
                        'id' => $product->id,
                        'slug' => $product->slug,
                        'name' => $product->name,
                        'short_description' => $product->short_description,
                        'status' => $product->status,
                        'quantity' => $product->quantity,
                        'price' => $product->price,
                        'discount' => $product->discount,
                        'discountedPrice' => $discountedPrice,
                        'discountPercentage' => $discountPercentage,
                        'image' => $imagePath,
                        'categories' => $categories,
                    ];
                });

                // Return response with pagination data
                return $this->withPagination($products, $formattedProducts, 'Products retrieved successfully.');
            }

            // If no pagination parameters, fetch all records without pagination
            $products = $query->get();

            // Format the response
            $formattedProducts = $products->map(function ($product) {
                // Collect all image paths for this product
                $imagePath = null;
                if ($product->images->isNotEmpty()) {
                    $imagePath = FileUploadService::getUrls([$product->images->first()->path])[0];
                }

                // Get product categories
                $categories = $product->categories->map(function ($categoryProduct) {
                    return [
                        'id' => $categoryProduct->category->id,
                        'name' => $categoryProduct->category->name,
                        'slug' => $categoryProduct->category->slug ?? null,
                    ];
                })->toArray();

                // Calculate discounted price and percentage
                $discountedPrice = null;
                $discountPercentage = null;
                if ($product->discount && $product->price) {
                    $discountedPrice = $product->price - $product->discount;
                    $discountPercentage = round(($product->discount / $product->price) * 100, 2); // 2 decimal
                }
                return [
                    'id' => $product->id,
                    'slug' => $product->slug,
                    'name' => $product->name,
                    'short_description' => $product->short_description,
                    'status' => $product->status,
                    'quantity' => $product->quantity,
                    'price' => $product->price,
                    'discount' => $product->discount,
                    'discountedPrice' => $discountedPrice,
                    'discountPercentage' => $discountPercentage,
                    'image' => $imagePath,
                    'categories' => $categories,
                ];
            });

            // Return response without pagination links
            return $this->success($formattedProducts, 'Products retrieved successfully.');
        } catch (\Exception $e) {
            // Handle any exceptions
            return $this->error('An error occurred while retrieving products.', 500, $e->getMessage());
        }
    }

    // show all products
    public function allProduct(Request $request)
    {
        try {
            // Get parameters from request
            $perPage = $request->input('limit');
            $currentPage = $request->input('page');
            $search = $request->input('search');
            $minPrice = $request->input('min_price');
            $maxPrice = $request->input('max_price');
            $categoryId = $request->input('category_id');
            $categorySlug = $request->input('category_slug');

            // Base query to fetch products with all related images of type 'product'
            $query = Item::with(['images' => function ($query) {
                $query->where('type', 'product')->orderBy('id', 'asc');
            }])->orderBy('created_at', 'desc');


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

            // Apply category filter if category_id or category_slug is provided
            if ($categoryId || $categorySlug) {
                $query->whereHas('categories', function ($categoryQuery) use ($categoryId, $categorySlug) {
                    if ($categoryId) {
                        $categoryQuery->where('category_id', $categoryId);
                    }
                    if ($categorySlug) {
                        $categoryQuery->whereHas('category', function ($catQuery) use ($categorySlug) {
                            $catQuery->where('slug', $categorySlug);
                        });
                    }
                });
            }

            // If pagination parameters are provided, apply pagination
            if ($perPage && $currentPage) {
                // Validate pagination parameters
                if (!is_numeric($perPage) || !is_numeric($currentPage) || $perPage <= 0 || $currentPage <= 0) {
                    return $this->error('Invalid pagination parameters', 400, 'Invalid pagination parameters');
                }

                // Apply pagination
                $products = $query->paginate($perPage, ['*'], 'page', $currentPage);

                // Format the response with pagination data
                $formattedProducts = $products->map(function ($product) {
                    // Collect all image paths for this product
                    $imagePaths = FileUploadService::getUrls(
                        $product->images->pluck('path')->toArray()
                    );


                    // Get product categories
                    $categories = $product->categories->map(function ($categoryProduct) {
                        return [
                            'id' => $categoryProduct->category->id,
                            'name' => $categoryProduct->category->name,
                            'slug' => $categoryProduct->category->slug ?? null,
                        ];
                    })->toArray();

                    // Calculate discounted price and percentage
                    $discountedPrice = null;
                    $discountPercentage = null;
                    if ($product->discount && $product->price) {
                        $discountedPrice = $product->price - $product->discount;
                        $discountPercentage = round(($product->discount / $product->price) * 100, 2); // 2 decimal
                    }

                    return [
                        'id' => $product->id,
                        'slug' => $product->slug,
                        'name' => $product->name,
                        'short_description' => $product->short_description,
                        'status' => $product->status,
                        'quantity' => $product->quantity,
                        'price' => $product->price,
                        'discount' => $product->discount,
                        'discountedPrice' => $discountedPrice,
                        'discountPercentage' => $discountPercentage,
                        'image_paths' => $imagePaths,
                        'categories' => $categories,
                    ];
                });

                // Return response with pagination data
                return $this->withPagination($products, $formattedProducts, 'Products retrieved successfully.');
            }

            // If no pagination parameters, fetch all records without pagination
            $products = $query->get();

            // Format the response
            $formattedProducts = $products->map(function ($product) {
                // Collect all image paths for this product
                $imagePaths = FileUploadService::getUrls(
                    $product->images->pluck('path')->toArray()
                );

                // Get product categories
                $categories = $product->categories->map(function ($categoryProduct) {
                    return [
                        'id' => $categoryProduct->category->id,
                        'name' => $categoryProduct->category->name,
                        'slug' => $categoryProduct->category->slug ?? null,
                    ];
                })->toArray();

                // Calculate discounted price and percentage
                $discountedPrice = null;
                $discountPercentage = null;
                if ($product->discount && $product->price) {
                    $discountedPrice = $product->price - $product->discount;
                    $discountPercentage = round(($product->discount / $product->price) * 100, 2); // 2 decimal
                }
                return [
                    'id' => $product->id,
                    'slug' => $product->slug,
                    'name' => $product->name,
                    'short_description' => $product->short_description,
                    'status' => $product->status,
                    'quantity' => $product->quantity,
                    'price' => $product->price,
                    'discount' => $product->discount,
                    'discountedPrice' => $discountedPrice,
                    'discountPercentage' => $discountPercentage,
                    'image_paths' => $imagePaths,
                    'categories' => $categories,
                ];
            });

            // Return response without pagination links
            return $this->success($formattedProducts, 'Products retrieved successfully.');
        } catch (\Exception $e) {
            // Handle any exceptions
            return $this->error('An error occurred while retrieving products.', 500, $e->getMessage());
        }
    }

    // shwo single products
    public function show($id)
    {
        try {
            // Fetch the product by ID with its relationships
            $product = Item::with([
                'categories.category',
                'tags',
                'images',
                'bundleItems.item.images'
            ])->find($id);

            // Check if the product exists
            if (!$product) {
                return $this->notFound('Product not found.');
            }

            // Calculate discounted price and percentage
            $discountedPrice = null;
            $discountPercentage = null;
            if ($product->discount && $product->price) {
                $discountedPrice = $product->price - $product->discount;
                $discountPercentage = round(($product->discount / $product->price) * 100, 2); // 2 decimal
            }

            // Format the response
            $formattedProduct = [
                'id' => $product->id,
                'slug' => $product->slug,
                'name' => $product->name,
                'description' => $product->description,
                'short_description' => $product->short_description,
                'is_bundle' => $product->is_bundle,
                'status' => $product->status,
                'quantity' => $product->quantity,
                'price' => $product->price,
                'discount' => $product->discount,
                'discountedPrice' => $discountedPrice,
                'discountPercentage' => $discountPercentage,
                'meta_title' => $product->meta_title,
                'meta_keywords' => $product->meta_keywords,
                'meta_description' => $product->meta_description,
                'categories' => $product->categories->map(function ($category) {
                    return [
                        'id' => $category->category->id,
                        'name' => $category->category->name,
                    ];
                }),
                'tags' => $product->tags->map(function ($tag) {
                    return [
                        'id' => $tag->id,
                        'tag' => $tag->tag,
                        'slug' => $tag->slug,
                    ];
                }),
                'images' => $product->images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'path' => FileUploadService::getUrl($image->path),
                    ];
                }),
            ];

            // Add bundle items if the product is a bundle
            if ($product->is_bundle == 1) {
                $formattedProduct['bundle_items'] = $product->bundleItems->map(function ($bundleItem) {
                    $item = $bundleItem->item; // Get the related item
                    return [
                        'bundle_id' => $bundleItem->id,
                        'item_id' => $item->id,
                        'name' => $item->name,
                        'price' => $item->price,
                        'discount' => $item->discount,
                        'bundle_quantity' => $bundleItem->bundle_quantity,
                        'image' => $item->images->isNotEmpty()
                            ? FileUploadService::getUrl($item->images->first()->path)
                            : null,
                    ];
                });
            }

            // Return success response
            return $this->success($formattedProduct, 'Product retrieved successfully.');
        } catch (\Exception $e) {
            // Handle any exceptions
            return $this->error('An error occurred while retrieving the product', 500, $e->getMessage());
        }
    }

    // Showing single products by slug
    public function showSingleProductBySlug($slug)
    {
        try {
            // Fetch product with all relations
            $product = Item::with([
                'categories.category',
                'tags',
                'images',
                'bundleItems.item.images',
                'ratings' => function ($query) {
                    $query->where('status', 1);
                },
                'ratings.user'
            ])->where('slug', $slug)->first();

            if (!$product) {
                return $this->notFound('Product not found.');
            }

            // Main product discount calculation
            $discountedPrice = null;
            $discountPercentage = null;
            if ($product->discount && $product->price) {
                $discountedPrice = $product->price - $product->discount;
                $discountPercentage = round(($product->discount / $product->price) * 100, 2);
            }

            // Base product format
            $formattedProduct = [
                'id' => $product->id,
                'slug' => $product->slug,
                'name' => $product->name,
                'description' => $product->description,
                'short_description' => $product->short_description,
                'is_bundle' => $product->is_bundle,
                'status' => $product->status,
                'quantity' => $product->quantity,
                'price' => $product->price,
                'discount' => $product->discount,
                'discountedPrice' => $discountedPrice,
                'discountPercentage' => $discountPercentage,
                'meta_title' => $product->meta_title,
                'meta_keywords' => $product->meta_keywords,
                'meta_description' => $product->meta_description,
                'categories' => $product->categories->map(function ($category) {
                    return [
                        'id' => $category->category->id,
                        'name' => $category->category->name,
                        'slug' => $category->category->slug,
                    ];
                }),
                'tags' => $product->tags->map(function ($tag) {
                    return [
                        'id' => $tag->id,
                        'tag' => $tag->tag,
                        'slug' => $tag->slug,
                    ];
                }),
                'images' => $product->images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'path' => FileUploadService::getUrl($image->path),
                    ];
                }),
                'ratings' => $product->ratings->map(function ($rating) {
                    return [
                        'id' => $rating->id,
                        'star' => $rating->star,
                        'rating' => $rating->rating,
                        'user' => $rating->user ? $rating->user->name : null,
                    ];
                }),
                'average_rating' => $product->ratings->avg('star'),
            ];

            // Add bundle items if product is a bundle
            if ($product->is_bundle == 1) {
                $formattedProduct['bundle_items'] = $product->bundleItems->map(function ($bundleItem) {
                    $item = $bundleItem->item;

                    // Calculate bundle item discount
                    $itemDiscountedPrice = null;
                    $itemDiscountPercentage = null;
                    if ($item->discount && $item->price) {
                        $itemDiscountedPrice = $item->price - $item->discount;
                        $itemDiscountPercentage = round(($item->discount / $item->price) * 100, 2);
                    }

                    return [
                        'bundle_id' => $bundleItem->id,
                        'item_id' => $item->id,
                        'name' => $item->name,
                        'slug' => $item->slug,
                        'price' => $item->price,
                        'discount' => $item->discount,
                        'discountedPrice' => $itemDiscountedPrice,
                        'discountPercentage' => $itemDiscountPercentage,
                        'bundle_quantity' => $bundleItem->bundle_quantity,
                        'image' => $item->images->isNotEmpty()
                            ? FileUploadService::getUrl($item->images->first()->path)
                            : null,
                    ];
                });
            }

            return $this->success($formattedProduct, 'Product retrieved successfully.');
        } catch (\Exception $e) {
            return $this->error('An error occurred while retrieving the product.', 500, $e->getMessage());
        }
    }


    // Best selling product
    public function bestSellingProducts(Request $request)
    {
        try {
            $limit = $request->input('limit', 10); // default 10 best-selling
            $query = Order_list::select('product_id', DB::raw('SUM(quantity) as total_sold'))
                ->groupBy('product_id')
                ->orderByDesc('total_sold')
                ->with(['item.images'])
                ->take($limit)
                ->get();

            $products = $query->map(function ($orderList) {
                $product = $orderList->item;

                if (!$product) {
                    return null; // skip if no product found
                }

                $imagePath = null;
                if ($product->images->isNotEmpty()) {
                    $firstPath = $product->images->first()->path;
                    $imagePath = FileUploadService::getUrls([$firstPath])[0];
                }

                // Calculate discounted price and percentage
                $discountedPrice = null;
                $discountPercentage = null;
                if ($product->discount && $product->price) {
                    $discountedPrice = $product->price - $product->discount;
                    $discountPercentage = round(($product->discount / $product->price) * 100, 2); // 2 decimal
                }
                return [
                    'id' => $product->id,
                    'slug' => $product->slug,
                    'name' => $product->name,
                    'short_description' => $product->short_description,
                    'status' => $product->status,
                    'quantity' => $product->quantity,
                    'price' => $product->price,
                    'discount' => $product->discount,
                    'discountedPrice' => $discountedPrice,
                    'discountPercentage' => $discountPercentage,
                    'total_sold' => $orderList->total_sold,
                    'image' => $imagePath,
                ];
            })->filter();

            return $this->success($products, 'Best-selling products retrieved successfully');
        } catch (\Exception $e) {
            return $this->error('An error occurred while fetching best-selling products.', 500, $e->getMessage());
        }
    }

    // New product
    public function newProducts(Request $request)
    {
        try {
            $limit = $request->input('limit', 10); // default 10 latest products

            $products = Item::with(['images' => function ($query) {
                $query->where('type', 'product')->orderBy('id', 'asc');
            }])
                ->where('status', '!=', 0)
                ->orderBy('created_at', 'desc')
                ->take($limit)
                ->get();


            $formattedProducts = $products->map(function ($product) {
                $firstImage = $product->images->first();
                $imagePath = $firstImage ? FileUploadService::getUrls([$firstImage->path])[0] : null;



                // Calculate discounted price and percentage
                $discountedPrice = null;
                $discountPercentage = null;
                if ($product->discount && $product->price) {
                    $discountedPrice = $product->price - $product->discount;
                    $discountPercentage = round(($product->discount / $product->price) * 100, 2); // 2 decimal
                }
                return [
                    'id' => $product->id,
                    'slug' => $product->slug,
                    'name' => $product->name,
                    'short_description' => $product->short_description,
                    'status' => $product->status,
                    'quantity' => $product->quantity,
                    'price' => $product->price,
                    'discount' => $product->discount,
                    'discountedPrice' => $discountedPrice,
                    'discountPercentage' => $discountPercentage,
                    'image' => $imagePath,
                ];
            });


            return $this->success($formattedProducts, 'New products retrieved successfully.');
        } catch (\Exception $e) {
            return $this->error('An error occurred while fetching new products.', 500, $e->getMessage());
        }
    }

    public function shwoProductCategorySlug($slug, Request $request)
    {
        try {
            $perPage     = $request->input('limit');
            $currentPage = $request->input('page');

            // Fetch category
            $category = Category::where('slug', $slug)->first();

            if (!$category) {
                return $this->notFound('Category not found.');
            }

            // Fetch product IDs under this category
            $productIds = CategoryProductList::where('category_id', $category->id)
                ->pluck('item_id');

            // Fetch products with first image
            $query = Item::with('firstImage')
                ->whereIn('id', $productIds)
                ->where('status', '!=', 0)
                ->orderBy('created_at', 'desc');

            // If pagination requested
            if ($perPage && $currentPage) {
                if (!is_numeric($perPage) || !is_numeric($currentPage) || $perPage <= 0 || $currentPage <= 0) {
                    return $this->error('Invalid pagination parameters.', 400);
                }

                $products = $query->paginate($perPage, ['*'], 'page', $currentPage);

                $formattedProducts = $products->map(function ($product) {
                    $discountedPrice = null;
                    $discountPercentage = null;
                    if ($product->discount && $product->price) {
                        $discountedPrice = $product->price - $product->discount;
                        $discountPercentage = round(($product->discount / $product->price) * 100, 2);
                    }
                    return [
                        'id'                => $product->id,
                        'name'              => $product->name,
                        'slug'              => $product->slug,
                        'short_description' => $product->short_description,
                        'status'            => $product->status,
                        'quantity'          => $product->quantity,
                        'price'             => $product->price,
                        'discount'          => $product->discount,
                        'discountedPrice'   => $discountedPrice,
                        'discountPercentage' => $discountPercentage,
                        'image'             => $product->firstImage
                            ? FileUploadService::getUrl($product->firstImage->path)
                            : null,
                    ];
                });

                return $this->withPagination($products, $formattedProducts, 'Products retrieved successfully.');
            }

            // If no pagination
            $products = $query->get();

            $formattedProducts = $products->map(function ($product) {
                $discountedPrice = null;
                $discountPercentage = null;
                if ($product->discount && $product->price) {
                    $discountedPrice = $product->price - $product->discount;
                    $discountPercentage = round(($product->discount / $product->price) * 100, 2);
                }
                return [
                    'id'                => $product->id,
                    'name'              => $product->name,
                    'slug'              => $product->slug,
                    'short_description' => $product->short_description,
                    'status'            => $product->status,
                    'quantity'          => $product->quantity,
                    'price'             => $product->price,
                    'discount'          => $product->discount,
                    'discountedPrice'   => $discountedPrice,
                    'discountPercentage' => $discountPercentage,
                    'image'             => $product->firstImage
                        ? FileUploadService::getUrl($product->firstImage->path)
                        : null,
                ];
            });

            return $this->success($formattedProducts, 'Products retrieved successfully for category');
        } catch (\Exception $e) {
            return $this->error('An error occurred while retrieving products.', 500, $e->getMessage());
        }
    }
}

<?php

namespace App\Http\Controllers\Category;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\ParentCategory;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Helpers\ActivityHelper;
use App\Services\FileUploadService;

class CategoryController extends Controller
{
    // ==========================================
    // PARENT CATEGORY METHODS (Main: Man, Woman)
    // ==========================================

    public function indexParents(Request $request)
    {
        try {
            $query = ParentCategory::query();

            if ($request->has('search')) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }

            $parents = $query->orderBy('created_at', 'asc')->get();

            // Append Image URLs
            foreach ($parents as $parent) {
                if ($parent->image) {
                    $parent->image_url = FileUploadService::getUrl($parent->image);
                }
            }

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Parent categories retrieved successfully.',
                'data' => $parents
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'status' => 500, 'message' => $e->getMessage()], 500);
        }
    }

    public function storeParent(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:parent_categories,name',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'status' => 422, 'errors' => $validator->errors()], 422);
            }

            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = FileUploadService::upload($request->file('image'), 'parent_categories');
            }

            $parent = ParentCategory::create([
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'image' => $imagePath,
                'status' => 1,
            ]);

            ActivityHelper::logActivity($parent->id, 'ParentCategory', "Created Parent Category: {$parent->name}");

            if ($parent->image) {
                $parent->image_url = FileUploadService::getUrl($parent->image);
            }

            return response()->json(['success' => true, 'status' => 201, 'message' => 'Parent Category created.', 'data' => $parent], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'status' => 500, 'message' => $e->getMessage()], 500);
        }
    }

    public function showParent($id)
    {
        try {
            $parent = ParentCategory::with('categories')->find($id);

            if (!$parent) {
                return response()->json(['success' => false, 'status' => 404, 'message' => 'Parent Category not found.'], 404);
            }

            if ($parent->image) {
                $parent->image_url = FileUploadService::getUrl($parent->image);
            }
            foreach ($parent->categories as $cat) {
                if ($cat->image) {
                    $cat->image_url = FileUploadService::getUrl($cat->image);
                }
            }

            return response()->json(['success' => true, 'status' => 200, 'data' => $parent], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'status' => 500, 'message' => $e->getMessage()], 500);
        }
    }

    public function updateParent(Request $request, $id)
    {
        try {
            $parent = ParentCategory::find($id);
            if (!$parent) {
                return response()->json(['success' => false, 'status' => 404, 'message' => 'Parent Category not found.'], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:parent_categories,name,' . $id,
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'status' => 422, 'errors' => $validator->errors()], 422);
            }

            $parent->name = $request->name;
            $parent->slug = Str::slug($request->name);

            if ($request->hasFile('image')) {
                if ($parent->image) FileUploadService::delete($parent->image);
                $parent->image = FileUploadService::upload($request->file('image'), 'parent_categories');
            }

            $parent->save();

            ActivityHelper::logActivity($parent->id, 'ParentCategory', "Updated Parent Category: {$parent->name}");

            if ($parent->image) {
                $parent->image_url = FileUploadService::getUrl($parent->image);
            }

            return response()->json(['success' => true, 'status' => 200, 'message' => 'Parent Category updated.', 'data' => $parent], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'status' => 500, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroyParent($id)
    {
        try {
            $parent = ParentCategory::find($id);
            if (!$parent) {
                return response()->json(['success' => false, 'status' => 404, 'message' => 'Parent Category not found.'], 404);
            }

            if ($parent->image) FileUploadService::delete($parent->image);
            $parent->delete();

            ActivityHelper::logActivity($parent->id, 'ParentCategory', "Deleted Parent Category: {$parent->name}");

            return response()->json(['success' => true, 'status' => 200, 'message' => 'Parent Category deleted.'], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'status' => 500, 'message' => $e->getMessage()], 500);
        }
    }

    // ==========================================
    // SUB CATEGORY METHODS (Sub: Shirt, Pant)
    // ==========================================

    public function indexSubCategories(Request $request)
    {
        try {
            $query = Category::with('parentCategory');

            if ($request->has('search')) {
                $query->where('name', 'like', '%' . $request->search . '%');
            }
            if ($request->has('parent_category_id')) {
                $query->where('parent_category_id', $request->parent_category_id);
            }

            $categories = $query->orderBy('created_at', 'desc')->get();

            // Append Image URLs
            foreach ($categories as $cat) {
                if ($cat->image) {
                    $cat->image_url = FileUploadService::getUrl($cat->image);
                }
                if ($cat->parentCategory && $cat->parentCategory->image) {
                    $cat->parentCategory->image_url = FileUploadService::getUrl($cat->parentCategory->image);
                }
            }

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Sub Categories retrieved successfully.',
                'data' => $categories
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'status' => 500, 'message' => $e->getMessage()], 500);
        }
    }

    public function storeSubCategory(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'parent_category_id' => 'required|exists:parent_categories,id',
                'description' => 'nullable|string',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'status' => 422, 'errors' => $validator->errors()], 422);
            }

            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = FileUploadService::upload($request->file('image'), 'categories');
            }

            $category = Category::create([
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'parent_category_id' => $request->parent_category_id,
                'description' => $request->description,
                'image' => $imagePath,
                'status' => 1,
            ]);

            ActivityHelper::logActivity($category->id, 'Category', "Created Sub Category: {$category->name}");

            if ($category->image) {
                $category->image_url = FileUploadService::getUrl($category->image);
            }

            return response()->json(['success' => true, 'status' => 201, 'message' => 'Sub Category created.', 'data' => $category], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'status' => 500, 'message' => $e->getMessage()], 500);
        }
    }

    public function showSubCategory($id)
    {
        try {
            $category = Category::with('parentCategory')->find($id);

            if (!$category) {
                return response()->json(['success' => false, 'status' => 404, 'message' => 'Sub Category not found.'], 404);
            }

            if ($category->image) {
                $category->image_url = FileUploadService::getUrl($category->image);
            }
            if ($category->parentCategory && $category->parentCategory->image) {
                $category->parentCategory->image_url = FileUploadService::getUrl($category->parentCategory->image);
            }

            return response()->json(['success' => true, 'status' => 200, 'data' => $category], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'status' => 500, 'message' => $e->getMessage()], 500);
        }
    }

    public function updateSubCategory(Request $request, $id)
    {
        try {
            $category = Category::find($id);
            if (!$category) {
                return response()->json(['success' => false, 'status' => 404, 'message' => 'Sub Category not found.'], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'parent_category_id' => 'required|exists:parent_categories,id',
                'description' => 'nullable|string',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'status' => 422, 'errors' => $validator->errors()], 422);
            }

            $category->name = $request->name;
            $category->slug = Str::slug($request->name);
            $category->parent_category_id = $request->parent_category_id;
            $category->description = $request->description;

            if ($request->hasFile('image')) {
                if ($category->image) FileUploadService::delete($category->image);
                $category->image = FileUploadService::upload($request->file('image'), 'categories');
            }

            $category->save();

            ActivityHelper::logActivity($category->id, 'Category', "Updated Sub Category: {$category->name}");

            if ($category->image) {
                $category->image_url = FileUploadService::getUrl($category->image);
            }

            return response()->json(['success' => true, 'status' => 200, 'message' => 'Sub Category updated.', 'data' => $category], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'status' => 500, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroySubCategory($id)
    {
        try {
            $category = Category::find($id);
            if (!$category) {
                return response()->json(['success' => false, 'status' => 404, 'message' => 'Sub Category not found.'], 404);
            }

            if ($category->image) FileUploadService::delete($category->image);
            $category->delete();

            ActivityHelper::logActivity($category->id, 'Category', "Deleted Sub Category: {$category->name}");

            return response()->json(['success' => true, 'status' => 200, 'message' => 'Sub Category deleted.'], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'status' => 500, 'message' => $e->getMessage()], 500);
        }
    }
}

<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Helpers\ActivityHelper;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Traits\ApiResponser;


class ProductAttributeController extends Controller
{
    use ApiResponser;

    /**
     * Display a listing of the attributes.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('limit', 10);
            $currentPage = $request->input('page', 1);

            $query = Attribute::with('values')->orderBy('created_at', 'desc');

            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            }

            $attributes = $query->paginate($perPage, ['*'], 'page', $currentPage);

            return $this->success($attributes, 'Attributes retrieved successfully.');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve attributes.', 500, $e->getMessage());
        }
    }

    /**
     * Store a newly created attribute in storage.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $slug = Str::slug($request->name);
            // Ensure unique slug
            $originalSlug = $slug;
            $count = 1;
            while (Attribute::where('slug', $slug)->exists()) {
                $slug = $originalSlug . '-' . $count++;
            }

            $attribute = Attribute::create([
                'name' => $request->name,
                'slug' => $slug,
            ]);

            ActivityHelper::logActivity(null, 'Attribute', "Created Attribute: {$attribute->name}");

            return $this->created($attribute->load('values'), 'Attribute created successfully.');
        } catch (\Exception $e) {
            return $this->error('Failed to create attribute.', 500, $e->getMessage());
        }
    }

    /**
     * Display the specified attribute.
     */
    public function show($id)
    {
        try {
            $attribute = Attribute::with('values')->find($id);

            if (!$attribute) {
                return $this->error('Attribute not found.', 404);
            }

            return $this->success($attribute, 'Attribute details retrieved successfully.');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve attribute.', 500, $e->getMessage());
        }
    }

    /**
     * Update the specified attribute in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            $attribute = Attribute::find($id);

            if (!$attribute) {
                return $this->error('Attribute not found.', 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            // Only update slug if name changed? Or always auto-generate from new name?
            // Usually, changing name updates slug, but good to check.
            // If explicit slug logic desired, user would say. "slug is auto created" implies from name.
            $slug = Str::slug($request->name);
            if ($attribute->slug !== $slug) {
                // Ensure unique if changing
                $originalSlug = $slug;
                $count = 1;
                while (Attribute::where('slug', $slug)->where('id', '!=', $id)->exists()) {
                    $slug = $originalSlug . '-' . $count++;
                }
            }

            $attribute->update([
                'name' => $request->name,
                'slug' => $slug,
            ]);

            ActivityHelper::logActivity(null, 'Attribute', "Updated Attribute: {$attribute->name}");

            return $this->success($attribute->load('values'), 'Attribute updated successfully.');
        } catch (\Exception $e) {
            return $this->error('Failed to update attribute.', 500, $e->getMessage());
        }
    }

    /**
     * Remove the specified attribute from storage.
     */
    public function destroy($id)
    {
        try {
            $attribute = Attribute::find($id);

            if (!$attribute) {
                return $this->error('Attribute not found.', 404);
            }

            $attribute->delete();

            ActivityHelper::logActivity(null, 'Attribute', "Deleted Attribute: {$attribute->name}");

            return $this->success(null, 'Attribute deleted successfully.');
        } catch (\Exception $e) {
            return $this->error('Failed to delete attribute.', 500, $e->getMessage());
        }
    }
}

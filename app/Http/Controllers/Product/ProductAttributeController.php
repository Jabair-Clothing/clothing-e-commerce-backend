<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Helpers\ActivityHelper;
use Illuminate\Support\Facades\Validator;

class ProductAttributeController extends Controller
{
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
                    ->orWhere('code', 'like', "%{$search}%");
            }

            $attributes = $query->paginate($perPage, ['*'], 'page', $currentPage);

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Attributes retrieved successfully.',
                'data' => $attributes,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to retrieve attributes.',
                'errors' => $e->getMessage(),
            ], 500);
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
                'code' => 'required|string|max:255|unique:attributes,code',
                'values' => 'nullable|array',
                'values.*.value' => 'required|string',
                'values.*.color_code' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'status' => 422,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $attribute = Attribute::create([
                'name' => $request->name,
                'code' => $request->code,
            ]);

            if ($request->has('values')) {
                foreach ($request->values as $val) {
                    AttributeValue::create([
                        'attribute_id' => $attribute->id,
                        'value' => $val['value'],
                        'color_code' => $val['color_code'] ?? null,
                    ]);
                }
            }

            ActivityHelper::logActivity(null, 'Attribute', "Created Attribute: {$attribute->name}");

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Attribute created successfully.',
                'data' => $attribute->load('values'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to create attribute.',
                'errors' => $e->getMessage(),
            ], 500);
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
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Attribute not found.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Attribute details retrieved successfully.',
                'data' => $attribute,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to retrieve attribute.',
                'errors' => $e->getMessage(),
            ], 500);
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
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Attribute not found.',
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'code' => "required|string|max:255|unique:attributes,code,{$id}",
                'values' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'status' => 422,
                    'message' => 'Validation failed.',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $attribute->update([
                'name' => $request->name,
                'code' => $request->code,
            ]);

            // Sync values (Simple strategy: delete all and recreate, or update existing? 
            // Better to handle add/update/delete of values separately usually, but for simple update:
            // If values provided, we can sync. For now, let's just create new ones and delete old?
            // Or maybe just let user add via separate API? 
            // Request said "api for create atribute post, get, create, update and deleate".
            // I'll update basic info. And if 'values' array is passed, I'll assume replace logic or add logic.
            // Let's go with replace logic for 'values' if provided.

            if ($request->has('values')) {
                // Delete existing
                $attribute->values()->delete();
                // Create new
                foreach ($request->values as $val) {
                    AttributeValue::create([
                        'attribute_id' => $attribute->id,
                        'value' => $val['value'],
                        'color_code' => $val['color_code'] ?? null,
                    ]);
                }
            }

            ActivityHelper::logActivity(null, 'Attribute', "Updated Attribute: {$attribute->name}");

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Attribute updated successfully.',
                'data' => $attribute->load('values'),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to update attribute.',
                'errors' => $e->getMessage(),
            ], 500);
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
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Attribute not found.',
                ], 404);
            }

            $attribute->delete(); // Values cascade delete usually if DB set, otherwise should delete manually.
            // Laravel relationship doesn't auto delete unless onDelete cascade is in migration.
            // Migration had: $table->foreignId('attribute_id')->constrained()->onDelete('cascade');
            // So it should be fine.

            ActivityHelper::logActivity(null, 'Attribute', "Deleted Attribute: {$attribute->name}");

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Attribute deleted successfully.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to delete attribute.',
                'errors' => $e->getMessage(),
            ], 500);
        }
    }
}

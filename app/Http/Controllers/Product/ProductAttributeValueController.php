<?php

namespace App\Http\Controllers\Product;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Helpers\ActivityHelper;
use Illuminate\Support\Facades\Validator;
use App\Traits\ApiResponser;

class ProductAttributeValueController extends Controller
{
    use ApiResponser;

    /**
     * Store a newly created attribute value in storage.
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'attribute_id' => 'required|exists:attributes,id',
                'name' => 'required|string|max:255',
                'code' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $attributeValue = AttributeValue::create([
                'attribute_id' => $request->attribute_id,
                'name' => $request->name,
                'code' => $request->code,
            ]);

            ActivityHelper::logActivity(null, 'AttributeValue', "Created Attribute Value: {$attributeValue->name}");

            return $this->created($attributeValue, 'Attribute Value created successfully.');
        } catch (\Exception $e) {
            return $this->error('Failed to create attribute value.', 500, $e->getMessage());
        }
    }

    /**
     * Update the specified attribute value in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            $attributeValue = AttributeValue::find($id);

            if (!$attributeValue) {
                return $this->error('Attribute Value not found.', 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'code' => 'nullable|string|max:255',
                'attribute_id' => 'sometimes|exists:attributes,id', // Optional if moving to another attribute
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $attributeValue->update($request->only(['name', 'code', 'attribute_id']));

            ActivityHelper::logActivity(null, 'AttributeValue', "Updated Attribute Value: {$attributeValue->name}");

            return $this->success($attributeValue, 'Attribute Value updated successfully.');
        } catch (\Exception $e) {
            return $this->error('Failed to update attribute value.', 500, $e->getMessage());
        }
    }

    /**
     * Remove the specified attribute value from storage.
     */
    public function destroy($id)
    {
        try {
            $attributeValue = AttributeValue::find($id);

            if (!$attributeValue) {
                return $this->error('Attribute Value not found.', 404);
            }

            $attributeValue->delete();

            ActivityHelper::logActivity(null, 'AttributeValue', "Deleted Attribute Value: {$attributeValue->name}");

            return $this->success(null, 'Attribute Value deleted successfully.');
        } catch (\Exception $e) {
            return $this->error('Failed to delete attribute value.', 500, $e->getMessage());
        }
    }
}

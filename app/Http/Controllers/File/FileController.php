<?php

namespace App\Http\Controllers\File;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Item;
use App\Models\File;
use Illuminate\Support\Facades\Validator;
use App\Services\FileUploadService;

class FileController extends Controller
{

    // shwo all product image
    public function getProductFiles()
    {
        $files = File::where('type', 'product')
            ->orderBy('id', 'desc')
            ->get()
            ->unique('path')
            ->values();

        if ($files->isEmpty()) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'No product files found.',
                'data' => null,
                'errors' => null
            ], 404);
        }

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Product files retrieved successfully.',
            'data' => $files,
            'errors' => null
        ], 200);
    }

    // Add image in product
    public function addImagesProduct(Request $request, $product_id)
    {
        try {
            // Validate the request data
            $validator = Validator::make($request->all(), [
                'images' => 'sometimes|array',
                'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:4048',
                'file_id' => 'sometimes|exists:files,id'
            ]);

            // If validation fails, return error response
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'status' => 422,
                    'message' => 'Validation failed.',
                    'data' => null,
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Find the product
            $product = Item::find($product_id);

            // Check if the product exists
            if (!$product) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Product not found.',
                    'data' => null,
                    'errors' => 'Invalid product ID.',
                ], 404);
            }

            // If file_id is provided, use the existing image path
            if ($request->has('file_id')) {
                $existingFile = File::find($request->file_id);

                if ($existingFile) {
                    File::create([
                        'relatable_id' => $product_id,
                        'type' => 'product',
                        'path' => $existingFile->path,
                    ]);
                }
            }

            if ($request->hasFile('images')) {
                $paths = FileUploadService::uploadMultiple(
                    $request->file('images'),
                    'product_image',
                    'zantech',
                    $product->name // base name for all images
                );

                foreach ($paths as $path) {
                    File::create([
                        'relatable_id' => $product->id,
                        'type'         => 'product',
                        'path'         => $path,
                    ]);
                }
            }


            // Return success response
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Images added to the product successfully.',
                'data' => null,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Handle any exceptions
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while adding images to the product.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }


    // Remove images from the product
    public function removePeoductImage(Request $request, $product_id)
    {
        try {
            // Validate the request data
            $validator = Validator::make($request->all(), [
                'image_id' => 'required|exists:files,id',
            ]);

            // If validation fails, return error response
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'status' => 422,
                    'message' => 'Validation failed.',
                    'data' => null,
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Find the product
            $product = Item::find($product_id);

            // Check if the product exists
            if (!$product) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Product not found.',
                    'data' => null,
                    'errors' => 'Invalid product ID.',
                ], 404);
            }

            // Find the image
            $image = File::where('id', $request->image_id)
                ->where('relatable_id', $product_id)
                ->where('type', 'product')
                ->first();

            // Check if the image exists
            if (!$image) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Image not found.',
                    'data' => null,
                    'errors' => 'Invalid image ID or image does not belong to the product.',
                ], 404);
            }

            // Delete the physical file from public directory
            FileUploadService::delete($image->path);

            // Delete the image record from the database
            $image->delete();

            // Return success response
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Image removed from the product successfully.',
                'data' => null,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Handle any exceptions
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while removing the image from the product.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers\Ambassador;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AmbassadorApplication;
use Illuminate\Support\Facades\Validator;
use App\Services\FileUploadService;

class AmbassadorController extends Controller
{
    // Create
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'    => 'required|string',
            'email'   => 'required|email',
            'campus'  => 'required|string',
            'phone'   => 'required|string',
            'message' => 'nullable|string',
            'image'   => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 422,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $imagePath = null;

        if ($request->hasFile('image')) {
            $imagePath = FileUploadService::upload(
                $request->file('image'),
                'ambassador',
                'zantech'
            );
        }

        $app = AmbassadorApplication::create([
            'name'    => $request->name,
            'email'   => $request->email,
            'campus'  => $request->campus,
            'phone'   => $request->phone,
            'status'  => '0',
            'message' => $request->message,
            'image'   => $imagePath,
        ]);

        return response()->json([
            'success' => true,
            'status' => 201,
            'message' => 'Application created successfully.',
            'data' => $app
        ], 201);
    }

    // Show all (desc order)
    public function index()
    {
        $apps = AmbassadorApplication::orderBy('created_at', 'desc')->get()->map(function ($app) {
            return [
                'id'      => $app->id,
                'name'    => $app->name,
                'email'   => $app->email,
                'campus'  => $app->campus,
                'phone'   => $app->phone,
                'status'  => $app->status,
                'message' => $app->message,
                'image' => $app->image
                    ? FileUploadService::getUrl($app->image)
                    : null,
                'created_at' => $app->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Applications retrieved successfully.',
            'data' => $apps
        ], 200);
    }

    // Delete
    public function destroy($id)
    {
        $app = AmbassadorApplication::find($id);

        if (!$app) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Application not found',
                'data' => null
            ], 404);
        }

        // Safely delete image file from the 'media' disk if it exists
        if ($app->image) {
            FileUploadService::delete($app->image);
        }

        // Delete the DB record
        $app->delete();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Application and image deleted successfully.',
            'data' => null
        ], 200);
    }
}

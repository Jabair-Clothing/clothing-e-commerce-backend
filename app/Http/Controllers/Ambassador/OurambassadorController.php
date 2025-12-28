<?php

namespace App\Http\Controllers\Ambassador;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Ourambassador;
use Illuminate\Support\Facades\Validator;
use App\Services\FileUploadService;

class OurambassadorController extends Controller
{
    // Create
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'   => 'required|string',
            'campus' => 'required|string',
            'image'  => 'nullable|image',
            'bio'    => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status'  => 422,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $data = $request->except('image');

        // Handle image upload if present
        if ($request->hasFile('image')) {
            $data['image'] = FileUploadService::upload(
                $request->file('image'),
                'ourambassadors',
                'zantech'
            );
        }

        $ambassador = Ourambassador::create($data);

        return response()->json([
            'success' => true,
            'status'  => 201,
            'message' => 'Ambassador created.',
            'data'    => $ambassador
        ]);
    }

    // Update
    public function update(Request $request, $id)
    {
        $ambassador = Ourambassador::find($id);
        if (!$ambassador) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Ambassador not found.'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string',
            'campus' => 'nullable|string',
            'image' => 'nullable|image',
            'status' => 'nullable|in:0,1',
            'bio' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 422,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $request->except('image');

        // Handle image upload + delete old
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if (!empty($ambassador->image)) {
                FileUploadService::delete($ambassador->image);
            }

            // Upload new image
            $data['image'] = FileUploadService::upload(
                $request->file('image'),
                'ourambassadors',
                'zantech'
            );
        }

        $ambassador->update($data);

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Ambassador updated.',
            'data' => $ambassador
        ]);
    }

    // Show all
    public function index()
    {
        $ambassadors = Ourambassador::all();

        foreach ($ambassadors as $amb) {
            $amb->image_url = $amb->image ? FileUploadService::getUrl($amb->image) : null;
        }

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'All ambassadors fetched.',
            'data' => $ambassadors
        ]);
    }

    // Show active (status=1)
    public function active()
    {
        $activeAmbassadors = Ourambassador::where('status', '1')->get();

        foreach ($activeAmbassadors as $amb) {
            $amb->image_url = $amb->image ? FileUploadService::getUrl($amb->image) : null;
        }

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Active ambassadors fetched.',
            'data' => $activeAmbassadors
        ]);
    }

    // Delete
    public function destroy($id)
    {
        $ambassador = Ourambassador::find($id);
        if (!$ambassador) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Ambassador not found.'
            ], 404);
        }

        // Delete image file from storage if it exists
        if (!empty($ambassador->image)) {
            FileUploadService::delete($ambassador->image);
        }

        $ambassador->delete();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Ambassador deleted.'
        ]);
    }
}

<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\SocialLink;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class CompanyController extends Controller
{
    // Show (first one for now)
    public function show()
    {
        $company = Company::with('socialLinks')->first();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Company info retrieved.',
            'data' => $company
        ]);
    }

    // Update
    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'nullable|string',
                'hero_title' => 'nullable|string',
                'hero_subtitle' => 'nullable|string',
                'hero_description' => 'nullable|string',
                'about_title' => 'nullable|string',
                'about_description1' => 'nullable|string',
                'about_description2' => 'nullable|string',
                'email' => 'nullable|email',
                'phone' => 'nullable|string',
                'location' => 'nullable|string',
                'footer_text' => 'nullable|string',
            ]);


            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'status' => 422,
                    'message' => 'Validation failed.',
                    'data' => null,
                    'errors' => $validator->errors(),
                ], 422);
            }

            $company = Company::findOrFail($id);

            if (!$company) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'company not found.',
                    'data' => null,
                    'errors' => 'Invalid company ID.',
                ], 404);
            }
            $company->update($request->all());

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Company updated.',
                'data' => $company
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while updating the company.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }
}

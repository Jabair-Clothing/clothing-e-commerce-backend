<?php

namespace App\Http\Controllers\Carrer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Career;
use Illuminate\Support\Facades\Validator;
use Exception;
use App\Helpers\ActivityHelper;


class CarrerController extends Controller
{
    // Get all careers
    public function index()
    {
        try {
            $careers = Career::all();
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Careers retrieved successfully.',
                'data' => $careers,
                'errors' => null,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Something went wrong.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    public function activeCareers()
    {
        try {
            $careers = Career::where('status', 1)->get();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Active careers retrieved successfully.',
                'data' => $careers,
                'errors' => null,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Something went wrong.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }


    // Show single career
    public function show($id)
    {
        try {
            $career = Career::find($id);
            if (!$career) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Career not found.',
                    'data' => null,
                    'errors' => null,
                ], 404);
            }
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Career retrieved successfully.',
                'data' => $career,
                'errors' => null,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Something went wrong.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // Create career
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'job_title'        => 'required|string|max:255',
                'description'      => 'required|string',
                'vacancy'          => 'required|integer',
                'job_type'         => 'required|string',
                'salary'           => 'required|string',
                'deadline'         => 'required|date',
                'department'       => 'required|string|max:255',
                'responsibilities' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'status' => 422,
                    'message' => 'Validation errors.',
                    'data' => null,
                    'errors' => $validator->errors(),
                ], 422);
            }

            $career = Career::create($request->all());

            // Prepare activity log details
            $careerId = $career->id;
            $careerDesc = "Created career post: Title - {$career->job_title}, Type - {$career->job_type}, Date - " . now()->toDateTimeString();

            // Save activity
            ActivityHelper::logActivity($careerId, 'Career', $careerDesc);

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Career created successfully.',
                'data' => $career,
                'errors' => null,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Something went wrong.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // Update career
    public function update(Request $request, $id)
    {
        try {
            $career = Career::find($id);
            if (!$career) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Career not found.',
                    'data' => null,
                    'errors' => null,
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'job_title'        => 'sometimes|string|max:255',
                'description'      => 'sometimes|string',
                'vacancy'          => 'sometimes|integer',
                'job_type'         => 'sometimes|string',
                'salary'           => 'sometimes|string',
                'deadline'         => 'sometimes|date',
                'department'       => 'sometimes|string|max:255',
                'responsibilities' => 'sometimes|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'status' => 422,
                    'message' => 'Validation errors.',
                    'data' => null,
                    'errors' => $validator->errors(),
                ], 422);
            }

            $career->update([
                'job_title' => $request->job_title ?? $career->job_title,
                'description' => $request->description ?? $career->description,
                'vacancy' => $request->vacancy ?? $career->vacancy,
                'job_type' => $request->job_type ?? $career->job_type,
                'salary' => $request->salary ?? $career->salary,
                'deadline' => $request->deadline ?? $career->deadline,
                'department' => $request->department ?? $career->department,
                'responsibilities' => $request->responsibilities ?? $career->responsibilities,
            ]);

            // Prepare activity log details
            $careerId = $career->id;
            $careerDesc = "Update career post: Title - {$career->job_title}, Type - {$career->job_type}, Date - " . now()->toDateTimeString();
            // Save activity
            ActivityHelper::logActivity($careerId, 'Career', $careerDesc);

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Career updated successfully.',
                'data' => $career,
                'errors' => null,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Something went wrong.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }


    // Delete career
    public function destroy($id)
    {
        try {
            $career = Career::find($id);

            if (!$career) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Career not found.',
                    'data' => null,
                    'errors' => null,
                ], 404);
            }

            // Loop through related forms and delete CV files
            foreach ($career->forms as $form) {
                if ($form->cv) {
                    $fullPath = public_path($form->cv);
                    if (file_exists($fullPath)) {
                        unlink($fullPath); // delete file from storage
                    }
                }
                $form->delete(); // delete DB record
            }

            // Finally delete the career
            $career->delete();

            // Prepare activity log details
            $careerId = $career->id;
            $careerDesc = "Delete career post: Title - {$career->job_title}, Type - {$career->job_type}, Date - " . now()->toDateTimeString();
            // Save activity
            ActivityHelper::logActivity($careerId, 'Career', $careerDesc);

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Career and related forms deleted successfully.',
                'data' => null,
                'errors' => null,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Something went wrong.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }


    // Patch: Change status 0/1
    public function changeStatus($id)
    {
        try {
            $career = Career::find($id);
            if (!$career) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Career not found.',
                    'data' => null,
                    'errors' => null,
                ], 404);
            }

            // Determine status label
            $statusLabel = $career->status == 1 ? 'Active' : 'Inactive';

            // Prepare activity log details
            $careerId = $career->id;
            $careerDesc = "Updated career status to {$statusLabel}: Title - {$career->job_title}, Type - {$career->job_type}, Date - " . now()->toDateTimeString();

            // Save activity
            ActivityHelper::logActivity($careerId, 'Career', $careerDesc);
            // Save activity
            ActivityHelper::logActivity($careerId, 'Career', $careerDesc);

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Career status updated successfully.',
                'data' => ['status' => $career->status],
                'errors' => null,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Something went wrong.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }
}

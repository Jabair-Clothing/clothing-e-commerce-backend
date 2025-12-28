<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\Technology;
use Illuminate\Support\Facades\Validator;
use App\Services\FileUploadService;

class ProjectController extends Controller
{

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'           => 'required|string',
            'description'     => 'nullable|string',
            'longdescription' => 'nullable|string',
            'image'           => 'nullable|image',
            'technologies'    => 'required|array',
            'technologies.*'  => 'required|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status'  => 422,
                'message' => 'Validation error',
                'errors'  => $validator->errors()
            ], 422);
        }

        $imagePath = null;

        if ($request->hasFile('image')) {
            $imagePath = FileUploadService::upload(
                $request->file('image'),
                'project',
                'zantech'
            );
        }

        $app = Project::create([
            'title'           => $request->title,
            'description'     => $request->description,
            'longdescription' => $request->longdescription,
            'image'           => $imagePath,
        ]);

        // Attach technologies
        foreach ($request->technologies as $techName) {
            Technology::create([
                'name'       => $techName,
                'project_id' => $app->id
            ]);
        }

        return response()->json([
            'success' => true,
            'status'  => 201,
            'message' => 'Project created successfully.',
            'data'    => $app
        ], 201);
    }



    // update project
    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'nullable|string',
                'description' => 'nullable|string',
                'longdescription' => 'nullable|string',
                'status' => 'nullable|string',
                'image' => 'nullable|image',
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

            $Project = Project::findOrFail($id);

            if (!$Project) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Project not found.',
                    'data' => null,
                    'errors' => 'Invalid Project ID.',
                ], 404);
            }

            // Handle image update
            if ($request->hasFile('image')) {
                // Delete old image if exists
                if ($Project->image) {
                    FileUploadService::delete($Project->image);
                }

                // Upload new image
                $Project->image = FileUploadService::upload(
                    $request->file('image'),
                    'project',
                    'zantech'
                );
            }

            // Update other fields including longdescription
            $Project->fill($request->except('image'));
            $Project->save();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Project updated successfully.',
                'data' => $Project
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while updating the Project.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }




    // GET ALL PROJECTS
    public function index()
    {
        try {
            $projects = Project::with('technologies')
                ->orderBy('id', 'desc')
                ->get();

            // Attach full image URL to each project
            // Slug is already included automatically in the response
            $projects->each(function ($project) {
                $project->image_url = $project->image
                    ? FileUploadService::getUrl($project->image)
                    : null;
            });

            return response()->json([
                'success' => true,
                'status'  => 200,
                'message' => 'Project list fetched.',
                'data'    => $projects
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'Failed to fetch projects.',
                'data'    => null,
                'errors'  => $e->getMessage(),
            ], 500);
        }
    }


    public function show($slug)
    {
        try {
            // Find project by slug instead of ID
            $project = Project::with('technologies')
                ->where('slug', $slug)
                ->firstOrFail();

            // Attach full image URL
            $project->image_url = $project->image
                ? FileUploadService::getUrl($project->image)
                : null;

            return response()->json([
                'success' => true,
                'status'  => 200,
                'message' => 'Project details fetched.',
                'data'    => $project
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'status'  => 404,
                'message' => 'Project not found.',
                'data'    => null,
                'errors'  => 'Invalid project slug.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'Failed to fetch project.',
                'data'    => null,
                'errors'  => $e->getMessage(),
            ], 500);
        }
    }


    // get all active projects
    public function getallactiveproject()
    {
        $projects = Project::with('technologies')
            ->where('status', 'active')
            ->orderBy('id', 'desc')
            ->get();

        // Attach full image URL to each project
        foreach ($projects as $project) {
            $project->image_url = $project->image
                ? FileUploadService::getUrl($project->image)
                : null;
        }

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Active project list fetched.',
            'data' => $projects
        ]);
    }




    // DELETE PROJECT
    public function destroy($id)
    {
        $project = Project::findOrFail($id);

        if (!empty($project->image)) {
            FileUploadService::delete($project->image);
        }

        // Delete related tech
        Technology::where('project_id', $project->id)->delete();

        $project->delete();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Project and image deleted successfully.',
            'data' => null
        ]);
    }


    // add new technologies in project
    public function addTechnologies(Request $request, $project_id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'status'  => 422,
                    'message' => 'Validation error',
                    'errors'  => $validator->errors()
                ], 422);
            }

            // Check if project exists
            $project = Project::find($project_id);
            if (!$project) {
                return response()->json([
                    'success' => false,
                    'status'  => 404,
                    'message' => 'Project not found.'
                ], 404);
            }

            // Create technology
            Technology::create([
                'name'       => $request->name,
                'project_id' => $project_id
            ]);

            // Refresh project with technologies
            $project->load('technologies');

            return response()->json([
                'success' => true,
                'status'  => 201,
                'message' => 'Technology added successfully.',
                'data'    => $project
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'An error occurred while adding technology.',
                'errors'  => $e->getMessage(),
            ], 500);
        }
    }


    // delete technologies from project
    public function deleteTechnologies($id)
    {
        $tech = Technology::find($id);

        if (!$tech) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Technology not found.'
            ], 404);
        }

        $tech->delete();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Technology deleted successfully.'
        ]);
    }
}

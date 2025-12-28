<?php

namespace App\Http\Controllers\Expense;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\File;
use App\Models\Activity;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Helpers\ActivityHelper;
use App\Services\FileUploadService;

class ExpenseController extends Controller
{
    //store the expense
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'date' => 'required|date',
                'title' => 'required|string|max:255',
                'amount' => 'required|numeric|min:0',
                'prove.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:3048',
                'prove' => 'nullable|array',
                'description' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'status' => 400,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors(),
                ], 400);
            }

            // Create the expense record
            $expense = Expense::create([
                'date' => $request->date,
                'user_id' => $request->user()->id,
                'title' => $request->title,
                'amount' => $request->amount,
                'description' => $request->description,
            ]);

            $fileCount = 0;

            // Handle multiple file uploads
            if ($request->hasFile('prove')) {
                $paths = FileUploadService::uploadMultiple(
                    $request->file('prove'),
                    'expense',
                    'zantech'
                );

                foreach ($paths as $path) {
                    File::create([
                        'relatable_id' => $expense->id,
                        'type'         => 'expense',
                        'path'         => $path,
                    ]);
                }

                $fileCount = count($paths);
            }

            // Prepare activity description
            $activityDesc = "Created Expense: Title - {$expense->title}, Amount - {$expense->amount}, Date - {$expense->date}, ";
            $activityDesc .= "Description - " . ($expense->description ?? 'N/A') . ", Files Uploaded - {$fileCount}, ";
            $activityDesc .= "Created at - " . now()->toDateTimeString();

            // Save activity
            ActivityHelper::logActivity($expense->id, 'Expense', $activityDesc);

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Expense created successfully.',
                'data' => [
                    'id' => $expense->id,
                    'date' => $expense->date,
                    'user_id' => $expense->user_id,
                    'title' => $expense->title,
                    'amount' => $expense->amount,
                    'description' => $expense->description,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while creating the expense.',
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // shwo all expense
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('limit');
            $currentPage = $request->input('page');
            $exactDate = $request->input('date');
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date');

            $query = Expense::with('proveFiles')->orderBy('created_at', 'desc');

            // Filter by exact date
            if ($exactDate) {
                $query->whereDate('date', $exactDate);
            }

            // Filter by date range
            if ($startDate && $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            }

            if ($perPage && $currentPage) {
                if (!is_numeric($perPage) || !is_numeric($currentPage) || $perPage <= 0 || $currentPage <= 0) {
                    return response()->json([
                        'success' => false,
                        'status' => 400,
                        'message' => 'Invalid pagination parameters.',
                        'data' => null,
                        'errors' => 'Invalid pagination parameters.',
                    ], 400);
                }

                $expenses = $query->paginate($perPage, ['*'], 'page', $currentPage);

                $formattedExpenses = $expenses->map(function ($expense) {
                    return [
                        'id' => $expense->id,
                        'date' => $expense->date,
                        'user_id' => $expense->user_id,
                        'title' => $expense->title,
                        'amount' => $expense->amount,
                        'description' => $expense->description,
                        'proves' => $expense->proveFiles->map(function ($proveFile) {
                            return [
                                'id' => $proveFile->id,
                                'type' => $proveFile->type,
                                'url' => url('public/' . $proveFile->path),
                            ];
                        }),
                    ];
                });

                return response()->json([
                    'success' => true,
                    'status' => 200,
                    'message' => 'Expenses retrieved successfully.',
                    'data' => $formattedExpenses,
                    'pagination' => [
                        'total' => $expenses->total(),
                        'per_page' => $expenses->perPage(),
                        'current_page' => $expenses->currentPage(),
                        'last_page' => $expenses->lastPage(),
                        'from' => $expenses->firstItem(),
                        'to' => $expenses->lastItem(),
                    ],
                ], 200);
            }

            // If no pagination
            $expenses = $query->get();

            $formattedExpenses = $expenses->map(function ($expense) {
                return [
                    'id' => $expense->id,
                    'date' => $expense->date,
                    'user_id' => $expense->user_id,
                    'title' => $expense->title,
                    'amount' => $expense->amount,
                    'description' => $expense->description,
                    'proves' => $expense->proveFiles->map(function ($proveFile) {
                        return [
                            'id' => $proveFile->id,
                            'type' => $proveFile->type,
                            'url' => asset('storage/expense/' . basename($proveFile->path)),
                        ];
                    }),
                ];
            });

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Expenses retrieved successfully.',
                'data' => $formattedExpenses,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while retrieving expenses.',
                'errors' => $e->getMessage(),
            ], 500);
        }
    }



    // update the expense
    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|string|max:255',
                'description' => 'sometimes|string',
                'amount' => 'sometimes|numeric|min:0',
                'proves.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:3048',
                'proves' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'status' => 400,
                    'message' => 'Validation errors',
                    'errors' => $validator->errors(),
                ], 400);
            }

            $expense = Expense::find($id);

            if (!$expense) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Expense not found.',
                    'data' => null,
                    'errors' => 'Expense not found.',
                ], 404);
            }

            $oldValues = $expense->getAttributes();
            $expense->update($request->only(['title', 'description', 'amount', 'user_id']));
            $newValues = $expense->getAttributes();

            $changes = [];
            foreach ($request->all() as $key => $value) {
                if (array_key_exists($key, $oldValues) && $oldValues[$key] != $newValues[$key]) {
                    $changes[] = "{$key} changed from '{$oldValues[$key]}' to '{$newValues[$key]}'";
                }
            }

            $fileCount = 0;
            if ($request->hasFile('proves')) {
                $paths = FileUploadService::uploadMultiple(
                    $request->file('proves'),
                    'expense',
                    'zantech'
                );

                foreach ($paths as $path) {
                    File::create([
                        'relatable_id' => $expense->id,
                        'type'         => 'expense',
                        'path'         => $path,
                    ]);
                }

                $fileCount = count($paths);
            }

            // Log changes and file uploads
            if (!empty($changes) || $fileCount > 0) {
                $activityDesc = 'Expense updated: ';
                if (!empty($changes)) {
                    $activityDesc .= implode(', ', $changes) . '. ';
                }
                if ($fileCount > 0) {
                    $activityDesc .= "Uploaded {$fileCount} new file(s). ";
                }
                $activityDesc .= 'Updated at - ' . now()->toDateTimeString();

                Activity::create([
                    'relatable_id' => $expense->id,
                    'type' => 'expense',
                    'user_id' => $request->user()->id,
                    'description' => $activityDesc,
                ]);
            }

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Expense updated successfully.',
                'data' => $expense->load('proveFiles'),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while updating the expense.',
                'errors' => $e->getMessage(),
            ], 500);
        }
    }


    // single expense
    public function show($id)
    {
        try {
            $expense = Expense::with('proveFiles')->find($id);

            if (!$expense) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Expense not found.',
                    'data' => null,
                    'errors' => 'Expense not found.',
                ], 404);
            }

            $formattedExpense = [
                'id' => $expense->id,
                'date' => $expense->date,
                'user_id' => $expense->user_id,
                'title' => $expense->title,
                'amount' => $expense->amount,
                'description' => $expense->description,
                'proves' => $expense->proveFiles->map(function ($proveFile) {
                    return [
                        'id' => $proveFile->id,
                        'type' => $proveFile->type,
                        'url' => FileUploadService::getUrl($proveFile->path),
                    ];
                }),
            ];

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Expense retrieved successfully.',
                'data' => $formattedExpense,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while retrieving the expense.',
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // delete the expense proves
    public function destroyProve($id)
    {
        try {
            // Find the file record
            $file = File::find($id);

            if (!$file) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Invoice file not found.',
                    'data' => null,
                    'errors' => 'Invalid file ID.',
                ], 404);
            }
            FileUploadService::delete($file->path);

            // Delete the image record from the database
            $file->delete();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Invoice file deleted successfully.',
                'data' => null,
                'errors' => null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while deleting the invoice file.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // delete the expense
    public function destroy($id)
    {
        try {
            // Find the expense
            $expense = Expense::find($id);

            if (!$expense) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Expense not found.',
                    'data' => null,
                ], 404);
            }

            // Get related files
            $files = File::where('relatable_id', $expense->id)->where('type', 'expense')->get();
            $fileCount = $files->count();

            // Delete each file from storage and database
            foreach ($files as $file) {
                FileUploadService::delete($file->path);
                $file->delete();
            }

            // Store details before deletion
            $title = $expense->title;
            $amount = $expense->amount;
            $date = $expense->date;

            // Delete the expense
            $expense->delete();

            // Prepare activity description
            $activityDesc = "Deleted Expense: Title - {$title}, Amount - {$amount}, Date - {$date}, ";
            $activityDesc .= "Files Deleted - {$fileCount}, Deleted at - " . now()->toDateTimeString();

            // Save activity
            ActivityHelper::logActivity($id, 'Expense', $activityDesc);

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Expense and associated files deleted successfully.',
                'data' => null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while deleting the expense.',
                'errors' => $e->getMessage(),
            ], 500);
        }
    }
}

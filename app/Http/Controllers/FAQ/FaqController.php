<?php

namespace App\Http\Controllers\FAQ;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Exception;
use App\Models\FAQ;

class FaqController extends Controller
{
    // Store new FAQ (status default 0)
    public function store(Request $request)
    {
        try {
            $request->validate([
                'question' => 'required|string',
                'answer'   => 'required|string',
                'category' => 'required|string',
            ]);

            $faq = FAQ::create([
                'question' => $request->question,
                'answer'   => $request->answer,
                'category' => $request->category,
                'status'   => 0,
            ]);

            return response()->json([
                'success' => true,
                'status'  => 201,
                'message' => 'FAQ created successfully.',
                'data'    => $faq,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'An error occurred while creating the FAQ.',
                'errors'  => $e->getMessage(),
            ], 500);
        }
    }

    // Show all FAQs, optional category filter, latest first
    public function index(Request $request)
    {
        try {
            $query = FAQ::query();

            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            $faqs = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'status'  => 200,
                'message' => 'FAQ list retrieved successfully.',
                'data'    => $faqs,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'An error occurred while fetching FAQs.',
                'errors'  => $e->getMessage(),
            ], 500);
        }
    }

    // Update FAQ (question, answer, category only)
    public function update(Request $request, $id)
    {
        try {
            $request->validate([
                'question' => 'sometimes|string',
                'answer'   => 'sometimes|string',
                'category' => 'sometimes|string',
            ]);

            $faq = FAQ::findOrFail($id);
            $faq->update($request->only(['question', 'answer', 'category']));

            return response()->json([
                'success' => true,
                'status'  => 200,
                'message' => 'FAQ updated successfully.',
                'data'    => $faq,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'An error occurred while updating the FAQ.',
                'errors'  => $e->getMessage(),
            ], 500);
        }
    }


    // Delete FAQ
    public function destroy($id)
    {
        try {
            $faq = FAQ::findOrFail($id);
            $faq->delete();

            return response()->json([
                'success' => true,
                'status'  => 200,
                'message' => 'FAQ deleted successfully.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'An error occurred while deleting the FAQ.',
                'errors'  => $e->getMessage(),
            ], 500);
        }
    }

    // Toggle status (0 ↔ 1)
    public function toggleStatus($id)
    {
        try {
            $faq = FAQ::findOrFail($id);
            $faq->status = $faq->status == 1 ? 0 : 1;
            $faq->save();

            return response()->json([
                'success' => true,
                'status'  => 200,
                'message' => 'FAQ status updated successfully.',
                'data'    => $faq,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'An error occurred while updating the FAQ status.',
                'errors'  => $e->getMessage(),
            ], 500);
        }
    }

    // Active faq
    public function indexactive(Request $request)
    {
        try {
            $query = FAQ::where('status', 1); 

            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            $faqs = $query->orderBy('created_at', 'asc')->get();

            return response()->json([
                'success' => true,
                'status'  => 200,
                'message' => 'Active FAQ list retrieved successfully.',
                'data'    => $faqs,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'An error occurred while fetching FAQs.',
                'errors'  => $e->getMessage(),
            ], 500);
        }
    }
}

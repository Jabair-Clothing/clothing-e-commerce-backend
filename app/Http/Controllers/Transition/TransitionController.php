<?php

namespace App\Http\Controllers\Transition;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Transition;

class TransitionController extends Controller
{
    //show all transitions
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('limit');
            $currentPage = $request->input('page');

            $startDate = $request->input('start_date'); // format: Y-m-d
            $endDate = $request->input('end_date');     // format: Y-m-d
            $duration = $request->input('duration');    // e.g., 'today', 'this_week', 'this_month'

            $query = Transition::with('payment')->orderBy('created_at', 'desc');

            // Apply duration filters
            if ($duration) {
                switch ($duration) {
                    case 'today':
                        $query->whereDate('created_at', now()->toDateString());
                        break;
                    case 'this_week':
                        $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                        break;
                    case 'this_month':
                        $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
                        break;
                }
            }

            // Apply custom date range filter if provided
            if ($startDate && $endDate) {
                $query->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            }

            // Pagination or fetch all
            if ($perPage && $currentPage) {
                $transitions = $query->paginate($perPage, ['*'], 'page', $currentPage);
            } else {
                $transitions = $query->get();
            }

            // Format transitions
            $formattedTransitions = $transitions->map(function ($transition) {
                return [
                    'transition_id' => $transition->id,
                    'payment_id' => $transition->payment_id,
                    'amount' => $transition->amount,
                    'payment_details' => [
                        'order_id' => $transition->payment->order_id,
                        'status' => $transition->payment->status,
                        'total_amount' => $transition->payment->amount,
                        'padi_amount' => $transition->payment->padi_amount,
                        'due_amount' => $transition->payment->amount - $transition->payment->padi_amount,
                        'payment_type' => $transition->payment->payment_type,
                        'trxed' => $transition->payment->trxed,
                        'phone' => $transition->payment->phone,
                    ],
                    'created_at' => $transition->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $transition->updated_at->format('Y-m-d H:i:s'),
                ];
            });

            $response = [
                'success' => true,
                'status' => 200,
                'message' => 'Transitions fetched successfully.',
                'data' => $formattedTransitions,
                'errors' => null,
            ];

            // Add pagination metadata
            if ($perPage && $currentPage) {
                $response['pagination'] = [
                    'total' => $transitions->total(),
                    'per_page' => $transitions->perPage(),
                    'current_page' => $transitions->currentPage(),
                    'last_page' => $transitions->lastPage(),
                    'from' => $transitions->firstItem(),
                    'to' => $transitions->lastItem(),
                ];
            }

            return response()->json($response, 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to fetch transitions.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }
}

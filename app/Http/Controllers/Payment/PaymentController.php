<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Transition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Models\Activity;
use Illuminate\Support\Facades\Auth;
use App\Helpers\ActivityHelper;

class PaymentController extends Controller
{
    //update payment status
    public function updatePaymentStatus(Request $request, $paymentId)
    {
        DB::beginTransaction();
        try {
            // Validate the request
            $request->validate([
                'status' => 'required|integer|in:0,1,2,3',
            ]);

            // Find the payment
            $payment = Payment::find($paymentId);
            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Payment not found.',
                    'data' => null,
                    'errors' => 'No query results for model [App\Models\Payment] ' . $paymentId,
                ], 404);
            }

            // Store the old status for activity logging
            $oldStatus = $payment->status;
            $inputStatus = $request->input('status');

            // Update payment status and payment_type based on the new logic
            if ($inputStatus == 0) {
                // If status is 0, both payment status and payment_type are 0
                $payment->status = 0;
                $payment->payment_type = 0;
            } else {
                // If status is 1, 2, 3, or 4, payment status becomes 1 and payment_type is the provided status
                $payment->status = 1;
                $payment->payment_type = $inputStatus;
            }

            $payment->save();

            // Handle Transition entry (update if exists, create if not)
            if ($payment->status == 1) { // Only create/update transition when status is 1
                $amount = $payment->payment_type == 1 ? $payment->amount : $payment->padi_amount;

                $existingTransition = Transition::where('payment_id', $payment->id)->first();
                if ($existingTransition) {
                    $existingTransition->amount = $amount;
                    $existingTransition->save();
                } else {
                    Transition::create([
                        'payment_id' => $payment->id,
                        'amount' => $amount,
                    ]);
                }
            } else {
                // If status is 0, remove any existing transition
                Transition::where('payment_id', $payment->id)->delete();
            }

            // Define readable labels for payment_type
            $paymentTypeLabels = [
                0 => 'Unpaid',
                1 => 'Cash Payment',
                2 => 'Credit Card',
                3 => 'Online Payment',
            ];

            $paymentTypeLabel = $paymentTypeLabels[$payment->payment_type] ?? 'Unknown';
            $transitionAction = $payment->status == 1
                ? ($existingTransition ? 'Updated Transition' : 'Created Transition')
                : 'Deleted Transition';

            $amountUsed = $payment->status == 1
                ? ($payment->payment_type == 1 ? $payment->amount : $payment->padi_amount)
                : 0;

            $activityDesc = "Updated Payment ID: {$paymentId}, Status: {$oldStatus} → {$payment->status}, ";
            $activityDesc .= "Payment Type: {$paymentTypeLabel}, Amount: {$amountUsed}, {$transitionAction}, ";
            $activityDesc .= "Updated at - " . now()->toDateTimeString();

            // Use helper instead of direct create
            ActivityHelper::logActivity(
                $paymentId,
                'payment',
                $activityDesc
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Payment status updated successfully.',
                'data' => [
                    'payment_id' => $payment->id,
                    'new_status' => $payment->status,
                    'payment_type' => $payment->payment_type,
                ],
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to update payment status.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }


    // update padi amount
    public function updatePadiAmount(Request $request, $paymentId)
    {
        DB::beginTransaction();
        try {
            // Validate the request
            $request->validate([
                'padi_amount' => 'required|numeric|min:0',
            ]);

            // Find the payment
            $payment = Payment::find($paymentId);
            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Payment not found.',
                    'data' => null,
                    'errors' => 'No query results for model [App\Models\Payment] ' . $paymentId,
                ], 404);
            }

            // Ensure padi_amount is not greater than amount
            if ($request->input('padi_amount') > $payment->amount) {
                return response()->json([
                    'success' => false,
                    'status' => 400,
                    'message' => 'padi_amount cannot be greater than the total amount.',
                    'data' => null,
                    'errors' => 'padi_amount exceeds the total amount.',
                ], 400);
            }

            // Update the padi_amount
            $payment->padi_amount = $request->input('padi_amount');
            $payment->save();

            // Save activity
            $oldPadiAmount = $payment->getOriginal('padi_amount');
            $newPadiAmount = $request->input('padi_amount');

            $activityDesc = "Updated padi_amount for Payment ID: {$paymentId}, Amount: {$oldPadiAmount} → {$newPadiAmount}, ";
            $activityDesc .= "Total Payment Amount: {$payment->amount}, Updated at - " . now()->toDateTimeString();

            // Use helper instead of direct create
            ActivityHelper::logActivity(
                $paymentId,
                'payment',
                $activityDesc
            );



            // Commit the transaction
            DB::commit();

            // Return success response
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'padi_amount updated successfully.',
                'data' => [
                    'payment_id' => $payment->id,
                    'new_padi_amount' => $payment->padi_amount,
                    'total_amount' => $payment->amount,
                ],
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Rollback the transaction in case of error
            DB::rollBack();

            // Handle exceptions and return error response
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to update padi_amount.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }
}

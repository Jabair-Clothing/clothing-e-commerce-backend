<?php

namespace App\Http\Controllers\Email;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use App\Mail\VerifyEmail;
use Illuminate\Auth\Events\Verified;

class EmailController extends Controller
{
    /**
     * Verify Email Address
     */
    public function verify(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return redirect('https://zantechbd.com/login')->with('error', 'User not found.');
        }

        // Validate the hash to prevent tampering
        if (!hash_equals(sha1($user->email), $request->hash)) {
            return redirect('https://zantechbd.com/login')->with('error', 'Invalid verification link.');
        }

        // Check if email is already verified
        if ($user->hasVerifiedEmail()) {
            return redirect('https://zantechbd.com/login')->with('info', 'Email already verified.');
        }

        // Mark email as verified
        $user->markEmailAsVerified();
        event(new Verified($user));

        return redirect('https://zantechbd.com/login')->with('success', 'Email verified successfully!');
    }

    /**
     * Resend Verification Email
     */
    public function resendVerificationEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Email not found. Please register first.',
            ], 404);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => 'Email already verified.',
            ], 400);
        }

        // Send verification email
        Mail::to($user->email)->send(new VerifyEmail($user));

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Verification email resent successfully.',
        ], 200);
    }
}

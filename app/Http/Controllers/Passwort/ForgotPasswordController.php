<?php

namespace App\Http\Controllers\Passwort;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class ForgotPasswordController extends Controller
{
    // Forgot Password

    public function forgotPassword(Request $request)
    {
        // Validate request (ensure email exists)
        $request->validate(['email' => 'required|email|exists:users,email']);

        // Generate the reset link
        $status = Password::sendResetLink(
            $request->only('email'),
            function ($user, $token) {
                $resetUrl = env('FRONTEND_URL') . "/auth/reset?token={$token}&email={$user->email}";
                Mail::send('emails.reset-password', ['url' => $resetUrl, 'user' => $user], function ($message) use ($user) {
                    $message->to($user->email)->subject('Reset Your Password');
                });
            }
        );

        // Return success response
        return response()->json([
            'success' => $status === Password::RESET_LINK_SENT,
            'status' => $status === Password::RESET_LINK_SENT ? 200 : 400,
            'message' => __($status),
        ]);
    }

    // Reset Password
    public function resetPassword(Request $request)
    {
        // Validate the request data
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'token' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Attempt to reset the password
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                // Update the user's password
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();
            }
        );

        // Return response based on status
        return response()->json([
            'success' => $status === Password::PASSWORD_RESET,
            'status' => $status === Password::PASSWORD_RESET ? 200 : 400,
            'message' => __($status),
        ]);
    }
}

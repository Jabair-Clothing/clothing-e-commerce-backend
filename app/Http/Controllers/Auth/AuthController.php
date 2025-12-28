<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use App\Mail\VerifyEmail;
use Illuminate\Support\Facades\Cookie;

class AuthController extends Controller
{
    /**
     * Register a new user.
     */
    public function register(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'phone' => 'required|string|size:11|unique:users',
                'type' => 'required|string',
                'password' => 'required|string|min:6',
            ]);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'type' => $request->type,
                'role' => $request->type,
                'password' => Hash::make($request->password),
            ]);

            $token = auth('api')->login($user);

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'User registered successfully.',
                'data' => [
                    'user' => $user,
                    'token' => $token,
                ],
                'errors' => null,
            ], 201);
        } catch (\Exception $e) {
            // Extract only the main error message
            $errorMessage = $e->getMessage();

            // Check if it's a SQL Integrity Constraint Violation
            if (str_contains($errorMessage, 'Integrity constraint violation')) {
                preg_match("/Duplicate entry '(.+?)' for key '(.+?)'/", $errorMessage, $matches);
                if (!empty($matches)) {
                    $errorMessage = "Duplicate entry '{$matches[1]}' for key '{$matches[2]}'";
                }
            }
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to register user. Please try again later.',
                'data' => null,
                'errors' => $errorMessage,
            ], 500);
        }
    }


    /**
     * Login user and return token.
     */
    public function login(Request $request)
    {
        try {
            $credentials = $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);

            if (! $token = auth('api')->attempt($credentials)) {
                return response()->json([
                    'success' => false,
                    'status' => 401,
                    'message' => 'Invalid credentials.',
                    'data' => null,
                    'errors' => 'Invalid credentials.',
                ], 401);
            }

            $user = auth('api')->user();

            // Check role exists
            if (empty($user->role)) {
                auth('api')->logout();
                return response()->json([
                    'success' => false,
                    'status' => 403,
                    'message' => 'No role assigned. Please contact admin.',
                    'data' => null,
                    'errors' => 'Role missing.',
                ], 403);
            }

            // Check status is active (1)
            if ($user->status != 1) {
                auth('api')->logout();
                return response()->json([
                    'success' => false,
                    'status' => 403,
                    'message' => 'You do not have access. Please contact admin.',
                    'data' => null,
                    'errors' => 'Account inactive.',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Login successful.',
                'data' => [
                    'user' => $user,
                    'token' => $token,
                ],
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();

            if (str_contains($errorMessage, 'Integrity constraint violation')) {
                preg_match("/Duplicate entry '(.+?)' for key '(.+?)'/", $errorMessage, $matches);
                if (!empty($matches)) {
                    $errorMessage = "Duplicate entry '{$matches[1]}' for key '{$matches[2]}'";
                }
            }

            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to process login. Please try again later.',
                'data' => null,
                'errors' => $errorMessage,
            ], 500);
        }
    }



    // Delete admin stuff and mamber
    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'User not found',
                'data' => null,
                'errors' => null,
            ], 404);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'User deleted successfully',
            'data' => null,
            'errors' => null,
        ], 200);
    }

    // Toggele user status
    public function toggleStatus($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'User not found',
                'data' => null,
                'errors' => null,
            ], 404);
        }

        // Flip status (if null, default to 0 first)
        $user->status = $user->status == 1 ? 0 : 1;
        $user->save();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'User status updated successfully.',
            'data' => $user->status,
            'errors' => null,
        ], 200);
    }

    /**
     * Logout user (delete token).
     */
    public function logout(Request $request)
    {
        try {
            auth('api')->logout();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Logged out successfully.',
                'data' => null,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Extract only the main error message
            $errorMessage = $e->getMessage();

            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to log out. Please try again later.',
                'data' => null,
                'errors' => $errorMessage,
            ], 500);
        }
    }


    /**
     * Get authenticated user details.
     */
    public function profile(Request $request)
    {
        try {
            $user = auth('api')->user();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'User profile retrieved successfully.',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'type' => $user->type,
                    'address' => $user->address,
                    'role_id' => $user->role_id,
                    'status' => $user->status,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ],
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Extract only the main error message
            $errorMessage = $e->getMessage();

            // Check if it's a SQL Integrity Constraint Violation
            if (str_contains($errorMessage, 'Integrity constraint violation')) {
                preg_match("/Duplicate entry '(.+?)' for key '(.+?)'/", $errorMessage, $matches);
                if (!empty($matches)) {
                    $errorMessage = "Duplicate entry '{$matches[1]}' for key '{$matches[2]}'";
                }
            }
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to retrieve user profile. Please try again later.',
                'data' => null,
                'errors' => $errorMessage,
            ], 500);
        }
    }


    // User registration
    public function Userregister(Request $request)
    {
        try {
            // Validate the request data
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8',
                'phone' => 'required|string|size:11',
            ]);

            // If validation fails, return error response
            if ($validator->fails()) {
                // Get the first error message
                $errorMessage = $validator->errors()->first();

                return response()->json([
                    'success' => false,
                    'status' => 422,
                    'message' => 'Validation failed.',
                    'data' => null,
                    'errors' => $errorMessage,
                ], 422);
            }

            // Create the user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
                'type' => 'user',
                'status' => 1,
            ]);

            // Send the verification email
            Mail::to($user->email)->send(new VerifyEmail($user));

            // Return success response
            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'User registered successfully. Please check your email to verify your account.',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'type' => $user->type,
                    'status' => $user->status,
                ],
                'errors' => null,
            ], 201);
        } catch (\Exception $e) {
            // Extract only the main error message
            $errorMessage = $e->getMessage();

            // // Check if it's a SQL Integrity Constraint Violation
            // if (str_contains($errorMessage, 'Integrity constraint violation')) {
            //     preg_match("/Duplicate entry '(.+?)' for key '(.+?)'/", $errorMessage, $matches);
            //     if (!empty($matches)) {
            //         $errorMessage = "Duplicate entry '{$matches[1]}' for key '{$matches[2]}'";
            //     }
            // }
            // Handle any exceptions
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while registering the user.',
                'data' => null,
                'errors' => $errorMessage,
            ], 500);
        }
    }

    // public function Userregister(Request $request)
    // {
    //     try {
    //         $validator = Validator::make($request->all(), [
    //             'name' => 'required|string|max:255',
    //             'email' => 'required|string|email|max:255|unique:users',
    //             'password' => 'required|string|min:8',
    //             'phone' => 'required|string|size:11|unique:users',
    //         ]);

    //         if ($validator->fails()) {
    //             $errorMessage = $validator->errors()->first();

    //             return response()->json([
    //                 'success' => false,
    //                 'status' => 422,
    //                 'message' => 'Validation failed.',
    //                 'data' => null,
    //                 'errors' => $errorMessage,
    //             ], 422);
    //         }

    //         $user = User::create([
    //             'name' => $request->name,
    //             'email' => $request->email,
    //             'password' => Hash::make($request->password),
    //             'phone' => $request->phone,
    //             'type' => 'user',
    //             'status' => 1,
    //         ]);

    //         $token = $user->createToken('auth_token')->plainTextToken;

    //         return response()->json([
    //             'success' => true,
    //             'status' => 201,
    //             'message' => 'User registered successfully.',
    //             'data' => [
    //                 'id' => $user->id,
    //                 'name' => $user->name,
    //                 'email' => $user->email,
    //                 'phone' => $user->phone,
    //                 'type' => $user->type,
    //                 'status' => $user->status,
    //                 'token' => $token,
    //             ],
    //             'errors' => null,
    //         ])->cookie('token', $token, 60 * 24 * 7); // Cookie valid for 7 days
    //     } catch (\Exception $e) {
    //         $errorMessage = $e->getMessage();

    //         return response()->json([
    //             'success' => false,
    //             'status' => 500,
    //             'message' => 'An error occurred while registering the user.',
    //             'data' => null,
    //             'errors' => $errorMessage,
    //         ], 500);
    //     }
    // }


    // user login
    public function Userlogin(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (! $token = auth('api')->attempt($credentials)) {
            return response()->json([
                'success' => false,
                'status' => 401,
                'message' => 'Invalid email or password.',
                'data' => null,
                'errors' => 'Invalid email or password.',
            ], 401);
        }

        $user = auth('api')->user();

        if ($user->type !== 'user') {
            auth('api')->logout();
            return response()->json([
                'success' => false,
                'status' => 403,
                'message' => 'Access denied. Only users can log in.',
                'data' => null,
                'errors' => 'Access denied.',
            ], 403);
        }

        if ($user->status != 1) {
            auth('api')->logout();
            return response()->json([
                'success' => false,
                'status' => 403,
                'message' => 'Account inactive. Contact admin.',
                'data' => null,
                'errors' => 'Account inactive.',
            ], 403);
        }

        // Method 1: Using Cookie facade (Recommended)
        $cookie = Cookie::make(
            'token',        // name
            $token,         // value
            1440,           // 1 day (in minutes)
            '/',            // path
            null,           // domain (null = current domain)
            true,           // secure = true (only HTTPS)
            true,           // httpOnly
            false,          // raw
            'None'          // SameSite = None
        );

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'User logged in successfully.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'type' => $user->type,
                'status' => $user->status,
                'token' => $token,
            ],
            'errors' => null,
        ])->withCookie($cookie);
    }

    // Change password
    public function changePassword(Request $request)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:8|confirmed',
            ]);

            // If validation fails, return error response
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'status' => 400,
                    'message' => 'Validation failed.',
                    'data' => null,
                    'errors' => $validator->errors(),
                ], 400);
            }

            // Get the authenticated user
            // Get the authenticated user
            $user = auth('api')->user();

            // Check if the current password matches
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'status' => 400,
                    'message' => 'Current password is incorrect.',
                    'data' => null,
                    'errors' => 'Current password is incorrect.',
                ], 400);
            }

            // Update the password
            $user->password = Hash::make($request->new_password);
            $user->save();

            // Return the response in the specified format
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Password changed successfully.',
                'data' => null,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Extract only the main error message
            $errorMessage = $e->getMessage();

            // Check if it's a SQL Integrity Constraint Violation
            if (str_contains($errorMessage, 'Integrity constraint violation')) {
                preg_match("/Duplicate entry '(.+?)' for key '(.+?)'/", $errorMessage, $matches);
                if (!empty($matches)) {
                    $errorMessage = "Duplicate entry '{$matches[1]}' for key '{$matches[2]}'";
                }
            }
            // Handle errors and return a consistent error response
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to change password.',
                'data' => null,
                'errors' => $errorMessage,
            ], 500);
        }
    }

    // update user information
    public function updateUserInfo(Request $request)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|string|email|max:255|unique:users,email,' . auth('api')->id(),
                'phone' => 'sometimes|string|max:20',
                'address' => 'sometimes|string|max:255',
            ]);

            // If validation fails, return error response
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'status' => 400,
                    'message' => 'Validation failed.',
                    'data' => null,
                    'errors' => $validator->errors(),
                ], 400);
            }

            // Get the authenticated user
            // Get the authenticated user
            $user = auth('api')->user();

            // Update the user info
            $user->update($request->only(['name', 'email', 'phone', 'address']));

            // Return the response in the specified format
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'User info updated successfully.',
                'data' => $user,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Extract only the main error message
            $errorMessage = $e->getMessage();

            // Check if it's a SQL Integrity Constraint Violation
            if (str_contains($errorMessage, 'Integrity constraint violation')) {
                preg_match("/Duplicate entry '(.+?)' for key '(.+?)'/", $errorMessage, $matches);
                if (!empty($matches)) {
                    $errorMessage = "Duplicate entry '{$matches[1]}' for key '{$matches[2]}'";
                }
            }
            // Handle errors and return a consistent error response
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to update user info.',
                'data' => null,
                'errors' => $errorMessage,
            ], 500);
        }
    }

    // email varifications
    public function verifyEmail(EmailVerificationRequest $request)
    {
        $request->fulfill();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Email verified successfully.',
            'errors' => null,
        ], 200);
    }
}

<?php

namespace App\Http\Controllers\Shipping;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ShippingAddress;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ShippingController extends Controller
{
    // Method to store a shipping address
    public function store(Request $request)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'f_name' => 'required|string|max:255',
                'l_name' => 'required|string|max:255',
                'phone' => 'required|string|max:20',
                'address' => 'required|string|max:255',
                'city' => 'required|string|max:255',
                'zip' => 'required|string|max:10',
                'User_id' => 'required|integer'
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

            // Create the shipping address
            $shippingAddress = ShippingAddress::create([
                'User_id' => $request->User_id,
                'f_name' => $request->f_name,
                'l_name' => $request->l_name,
                'phone' => $request->phone,
                'address' => $request->address,
                'city' => $request->city,
                'zip' => $request->zip,
            ]);

            // Return the response in the specified format
            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Shipping address created successfully.',
                'data' => $shippingAddress,
                'errors' => null,
            ], 201);
        } catch (\Exception $e) {
            // Handle errors and return a consistent error response
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to create shipping address.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // Method to show a shipping address
    public function show($id)
    {
        try {
            // Find the shipping address by ID
            $shippingAddress = ShippingAddress::find($id);

            // If the shipping address doesn't exist, return a 404 response
            if (!$shippingAddress) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Shipping address not found.',
                    'data' => null,
                    'errors' => 'Shipping address not found.',
                ], 404);
            }

            // Return the response in the specified format
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Shipping address retrieved successfully.',
                'data' => $shippingAddress,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Handle errors and return a consistent error response
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to retrieve shipping address.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // Method to update a shipping address
    public function update(Request $request, $id)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'f_name' => 'sometimes|string|max:255',
                'l_name' => 'sometimes|string|max:255',
                'phone' => 'sometimes|string|max:20',
                'address' => 'sometimes|string|max:255',
                'city' => 'sometimes|string|max:255',
                'zip' => 'sometimes|string|max:10',
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

            // Find the shipping address by ID
            $shippingAddress = ShippingAddress::find($id);

            // If the shipping address doesn't exist, return a 404 response
            if (!$shippingAddress) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Shipping address not found.',
                    'data' => null,
                    'errors' => 'Shipping address not found.',
                ], 404);
            }

            // Update the shipping address
            $shippingAddress->update($request->all());

            // Return the response in the specified format
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Shipping address updated successfully.',
                'data' => $shippingAddress,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Handle errors and return a consistent error response
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to update shipping address.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // Method to delete a shipping address
    public function destroy($id)
    {
        try {
            // Find the shipping address by ID
            $shippingAddress = ShippingAddress::find($id);

            // If the shipping address doesn't exist, return a 404 response
            if (!$shippingAddress) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'Shipping address not found.',
                    'data' => null,
                    'errors' => 'Shipping address not found.',
                ], 404);
            }

            // Delete the shipping address
            $shippingAddress->delete();

            // Return the response in the specified format
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Shipping address deleted successfully.',
                'data' => null,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Handle errors and return a consistent error response
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to delete shipping address.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // Method to fetch all shipping addresses created by the logged-in user
    public function index($user_id)
    {
        try {
            // Fetch all shipping addresses created by the provided user_id in descending order
            $shippingAddresses = ShippingAddress::where('User_id', $user_id)
                ->orderBy('created_at', 'desc')
                ->get();

            // Return the response in JSON format
            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Shipping addresses retrieved successfully.',
                'data' => $shippingAddresses,
                'errors' => null,
            ], 200);
        } catch (\Exception $e) {
            // Handle errors and return a consistent error response
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Failed to retrieve shipping addresses.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }


    // user shipping address
    public function userindex()
    {
        try {
            $user_id = auth()->id();

            $shippingAddresses = ShippingAddress::where('user_id', $user_id)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($item) {
                    return [
                        'id'         => $item->id,
                        'user_id'    => $item->User_id, 
                        'f_name'     => $item->f_name,
                        'l_name'     => $item->l_name,
                        'phone'      => $item->phone,
                        'address'    => $item->address,
                        'city'       => $item->city,
                        'zip'        => $item->zip,
                        'created_at' => $item->created_at,
                        'updated_at' => $item->updated_at,
                    ];
                });

            return response()->json([
                'success' => true,
                'status'  => 200,
                'message' => 'Shipping addresses retrieved successfully.',
                'data'    => $shippingAddresses,
                'errors'  => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'Failed to retrieve shipping addresses.',
                'data'    => null,
                'errors'  => $e->getMessage(),
            ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\ShippingAddress;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Activity;
use App\Models\Wishlist;
use Illuminate\Support\Facades\Auth;
use App\Traits\ApiResponser;

class UserController extends Controller
{
    //shwo user info only id, name and email
    public function index(Request $request)
    {
        $query = User::select('id', 'name', 'email', 'phone', 'address', 'type')
            ->where('type', 'user')
            ->withCount('wishlists'); 

        // Search by name, email, or phone
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'No users found.',
                'data' => [],
                'errors' => 'No users found.',
            ], 404);
        }

        $allUsersData = [];

        foreach ($users as $user) {
            $orders = Order::where('user_id', $user->id)->get();
            $totalOrders = $orders->count();
            $totalAmount = $orders->where('status', 1)->sum('total_amount');

            $payments = Payment::whereHas('order', function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->where('status', 1);
            })->get();

            $totalDueAmount = $payments->sum(function ($payment) {
                $amount = (float) ($payment->amount ?? 0);
                $paid   = (float) ($payment->paid_amount ?? 0);
                return max(0, $amount - $paid);
            });

            $allUsersData[] = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'address' => $user->address,

                // wishlist count
                'wishlist_summary' => [
                    'total_wishlist' => (int) ($user->wishlists_count ?? 0),
                ],

                'order_summary' => [
                    'total_orders' => $totalOrders,
                    'total_spend' => (float) $totalAmount,
                ],
                'payment_summary' => [
                    'due_amount' => (float) $totalDueAmount,
                ],
            ];
        }

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Users retrieved successfully.',
            'data' => $allUsersData,
            'errors' => null,
        ], 200);
    }




    // shwo user all information
    public function shwoAllInfo(Request $request, $userId = null)
    {
        try {
            $perPage = (int) $request->input('limit', 0);

            // Base query with eager-load wishlist products + primary image
            $query = User::select('id', 'name', 'email', 'phone', 'created_at', 'address')
                ->with([
                    'wishlists.product:id,name',
                    'wishlists.product.primaryImage:id,product_id,image_url,image_path,is_primary',
                ]);

            if (!empty($userId)) {
                $query->where('id', $userId);
            }

            // paginate or get
            if ($perPage > 0) {
                $users = $query->paginate($perPage);
                $userCollection = $users->getCollection();
            } else {
                $users = $query->get();
                $userCollection = $users;
            }

            if ($userCollection->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'status' => 404,
                    'message' => 'No users found.',
                    'data' => null,
                    'errors' => 'No users found.',
                ], 404);
            }

            $allUsersData = [];

            foreach ($userCollection as $user) {
                // Shipping
                $shippingAddresses = ShippingAddress::where('User_id', $user->id)->get();

                // Orders
                $orders = Order::where('user_id', $user->id)->get();
                $totalOrders = $orders->count();

                // if status=1 means completed/paid order
                $totalAmount = $orders->where('status', 1)->sum('total_amount');

                // Payments for user orders (status=1 orders)
                $payments = Payment::whereHas('order', function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                        ->where('status', 1);
                })->get();

                $totalDueAmount = $payments->sum(function ($payment) {
                    $amount = (float) ($payment->amount ?? 0);
                    $paid   = (float) ($payment->paid_amount ?? 0); // FIXED
                    return max(0, $amount - $paid);
                });

                // Wishlist products (name + image)
                $wishlistProducts = $user->wishlists->map(function ($w) {
                    $product = $w->product;

                    // image preference: image_url if exists, else image_path
                    $primaryImage = $product?->primaryImage;
                    $image = null;

                    if ($primaryImage) {
                        $image = $primaryImage->image_url ?: $primaryImage->image_path;
                    }

                    return [
                        'wishlist_id' => $w->id,
                        'product_id' => $product?->id,
                        'product_name' => $product?->name,
                        'product_image' => $image,
                    ];
                })->values();

                $allUsersData[] = [
                    'user' => $user,
                    'shipping_addresses' => $shippingAddresses,

                    'order_summary' => [
                        'total_orders' => $totalOrders,
                        'total_spend' => (float) $totalAmount,
                    ],

                    'payment_summary' => [
                        'due_amount' => (float) $totalDueAmount,
                    ],

                    'wishlist' => [
                        'count' => $wishlistProducts->count(),
                        'products' => $wishlistProducts,
                    ],
                ];
            }

            $pagination = null;
            if ($perPage > 0) {
                $pagination = [
                    'total_rows' => $users->total(),
                    'current_page' => $users->currentPage(),
                    'per_page' => $users->perPage(),
                    'total_pages' => $users->lastPage(),
                    'has_more_pages' => $users->hasMorePages(),
                ];
            }

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'User information retrieved successfully.',
                'data' => $allUsersData,
                'pagination' => $pagination,
                'error' => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Something went wrong.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }

    // Shwo all active
    public function getActivities(Request $request)
    {
        try {
            $perPage = $request->input('limit');
            $currentPage = $request->input('page');

            // Base query to fetch activities with eager loading of user
            $query = Activity::with('user')->orderBy('created_at', 'desc');

            if ($perPage && $currentPage) {
                // Validate pagination parameters
                if (!is_numeric($perPage) || !is_numeric($currentPage) || $perPage <= 0 || $currentPage <= 0) {
                    return response()->json([
                        'success' => false,
                        'status' => 400,
                        'message' => 'Invalid pagination parameters.',
                        'data' => null,
                        'errors' => 'Invalid pagination parameters.',
                    ], 400);
                }

                // Apply pagination
                $activities = $query->paginate($perPage, ['*'], 'page', $currentPage);

                // Return response with pagination data
                return response()->json([
                    'success' => true,
                    'status' => 200,
                    'message' => 'Activities retrieved successfully.',
                    'data' => $activities->items(),
                    'pagination' => [
                        'total_rows' => $activities->total(),
                        'current_page' => $activities->currentPage(),
                        'per_page' => $activities->perPage(),
                        'total_pages' => $activities->lastPage(),
                        'has_more_pages' => $activities->hasMorePages(),
                    ]
                ], 200);
            }

            // If no pagination parameters, fetch all records without pagination
            $activities = $query->get();

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Activities retrieved successfully.',
                'data' => $activities
            ], 200);
        } catch (\Exception $e) {
            // Handle any exceptions that occur
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while retrieving activities.',
                'data' => null,
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    // login user info
    public function loninUserInfo(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'status'  => 404,
                    'message' => 'No users found.',
                    'data'    => [],
                    'errors'  => 'User not authenticated.',
                ], 404);
            }

            // Calculate counts
            $totalOrders          = $user->orders()->count();
            $totalWishlist        = Wishlist::where('user_id', $user->id)->count(); // row count
            $totalShippingAddress = ShippingAddress::where('user_id', $user->id)->count();

            return response()->json([
                'success' => true,
                'status'  => 200,
                'message' => 'User info retrieved.',
                'data'    => [
                    'id'                  => $user->id,
                    'name'                => $user->name,
                    'email'               => $user->email,
                    'phone'               => $user->phone,
                    'address'             => $user->address,
                    'total_orders'        => $totalOrders,
                    'total_wishlist'      => $totalWishlist,
                    'total_shipping_addr' => $totalShippingAddress,
                ],
                'errors'  => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'Something went wrong.',
                'data'    => [],
                'errors'  => $e->getMessage(),
            ], 500);
        }
    }


    // login user dashkboard
    public function userDashboard(Request $request)
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'status'  => 401,
                    'message' => 'Unauthorized',
                    'data'    => (object)[],
                    'error'   => 'User not logged in'
                ], 401);
            }

            // Calculate stats
            $totalOrders    = $user->orders()->count();
            $totalWishlists = Wishlist::where('user_id', $user->id)->count();
            $totalAddresses = ShippingAddress::where('user_id', $user->id)->count();
            $totalSpent     = Payment::whereHas('order', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })->sum('padi_amount');

            return response()->json([
                'success' => true,
                'status'  => 200,
                'message' => 'User dashboard retrieved successfully.',
                'data'    => [
                    'total_orders'    => $totalOrders,
                    'total_wishlists' => $totalWishlists,
                    'total_addresses' => $totalAddresses,
                    'total_spent'     => $totalSpent,
                ],
                'error'   => null
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'Something went wrong.',
                'data'    => (object)[],
                'error'   => $e->getMessage()
            ], 500);
        }
    }
}

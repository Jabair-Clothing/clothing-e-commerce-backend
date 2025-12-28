<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Transition;
use Illuminate\Support\Facades\Log;

class AdminDashboardController extends Controller
{
    //Admin dashboard overview
    public function adminDashboard()
    {
        try {
            // Get the total order count
            $totalOrderCount = Order::count();

            // Get the count of new orders (status = 0)
            $newOrderCount = Order::where('status', 0)->count();

            // Get today's total sales count from orders table (current date)
            $todayOrderCount = Order::whereDate('created_at', now()->toDateString())->count();

            // Get the total revenue amount from the transitions table
            $totalRevenue = Transition::sum('amount');

            // Get today's total sales amount from transitions table (current date)
            $todayRevenue = Transition::whereDate('created_at', now()->toDateString())->sum('amount');

            return response()->json([
                'success' => true,
                'status' => 200,
                'message' => 'Dashboard data retrieved successfully.',
                'data' => [
                    'total_order_count' => $totalOrderCount,
                    'new_order_count' => $newOrderCount,
                    'today_order_count' => $todayOrderCount,
                    'total_revenue' => $totalRevenue,
                    'today_revenue' => $todayRevenue,
                ],
            ]);
        } catch (\Exception $e) {
            // Log the error for debugging purposes
            Log::error('Error in adminDashboard: ' . $e->getMessage());

            // Return a generic error response
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'An error occurred while retrieving dashboard data.',
                'data' => null,
                'errors' => $e->getMessage(),
            ], 500);
        }
    }
}

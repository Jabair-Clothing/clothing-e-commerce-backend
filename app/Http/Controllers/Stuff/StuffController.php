<?php

namespace App\Http\Controllers\Stuff;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\ShippingAddress;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Activity;
use App\Models\Expense;
use App\Models\Coupon;

class StuffController extends Controller
{
    //get admin stuff and  member data
    public function index(Request $request)
    {
        $query = User::select('id', 'name', 'email', 'phone', 'address', 'type','status')
            ->whereIn('type', ['admin', 'stuff', 'member']);

        // Search by name, email, or phone
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $stuffs = $query->get();

        if ($stuffs->isEmpty()) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'No stuffs found.',
                'data' => [],
                'errors' => 'No stuffs found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Stuffs retrieved successfully.',
            'data' => $stuffs,
            'errors' => null,
        ], 200);
    }
}

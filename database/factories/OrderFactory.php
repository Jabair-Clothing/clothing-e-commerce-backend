<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Order;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_code' => 'INV-' . Str::upper(Str::random(10)),
            'user_id' => fake()->randomDigit(), // Or link to User factory if needed
            'status' => '1',
            'item_subtotal' => '100.00',
            'total_amount' => '100.00',
            'status_chnange_desc' => 'Order Placed',
            'coupons_id' => null,
            // Add other fields as needed
        ];
    }
}

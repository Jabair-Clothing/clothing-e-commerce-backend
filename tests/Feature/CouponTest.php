<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\Order;
use Carbon\Carbon;

class CouponTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->user = User::factory()->create(['role' => 'member']);
    }

    public function test_create_global_flat_coupon()
    {
        $response = $this->actingAs($this->admin, 'api')->postJson('/api/coupons', [
            'code' => 'GLOBAL10',
            'amount' => 10,
            'type' => 'flat',
            'is_global' => true,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(10)->toDateString(),
            'status' => 1
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('coupons', ['code' => 'GLOBAL10']);
    }

    public function test_create_product_specific_percent_coupon()
    {
        $product = Product::factory()->create();

        $response = $this->actingAs($this->admin, 'api')->postJson('/api/coupons', [
            'code' => 'PROD50',
            'amount' => 50,
            'type' => 'percent',
            'is_global' => false,
            'item_ids' => [$product->id],
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(10)->toDateString(),
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('coupons', ['code' => 'PROD50']);
        $this->assertDatabaseHas('coupon__products', ['item_id' => $product->id]);
    }

    public function test_check_coupon_validity_global()
    {
        $coupon = Coupon::create([
            'code' => 'TEST10',
            'amount' => 10,
            'type' => 'flat',
            'is_global' => true,
            'status' => 1,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
        ]);

        $response = $this->postJson('/api/check-coupon', [
            'coupon_code' => 'TEST10',
            'total_amount' => 100
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.discount', 10);
    }

    public function test_check_coupon_expired()
    {
        $coupon = Coupon::create([
            'code' => 'EXPIRED',
            'amount' => 10,
            'type' => 'flat',
            'is_global' => true,
            'status' => 1,
            'start_date' => now()->subDays(10),
            'end_date' => now()->subDays(5),
        ]);

        $response = $this->postJson('/api/check-coupon', [
            'coupon_code' => 'EXPIRED',
            'total_amount' => 100
        ]);

        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'This coupon has expired.']);
    }

    public function test_check_coupon_min_purchase()
    {
        $coupon = Coupon::create([
            'code' => 'MIN100',
            'amount' => 10,
            'type' => 'flat',
            'is_global' => true,
            'status' => 1,
            'min_pur' => 100,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
        ]);

        $response = $this->postJson('/api/check-coupon', [
            'coupon_code' => 'MIN100',
            'total_amount' => 50
        ]);

        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'Minimum purchase of 100 is required for this coupon.']);
    }

    public function test_check_coupon_product_specific()
    {
        $product = Product::factory()->create(['base_price' => 100]);
        $otherProduct = Product::factory()->create(['base_price' => 50]);

        $coupon = Coupon::create([
            'code' => 'PROD_SPECIFIC',
            'amount' => 10, // 10%
            'type' => 'percent',
            'is_global' => false,
            'status' => 1,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
        ]);
        $coupon->items()->attach($product->id);

        // Cart has target product (100) and other product (50). Total 150.
        // Discount should apply only to target product: 10% of 100 = 10.
        $response = $this->postJson('/api/check-coupon', [
            'coupon_code' => 'PROD_SPECIFIC',
            'total_amount' => 150,
            'products' => [
                ['product_id' => $product->id, 'quantity' => 1],
                ['product_id' => $otherProduct->id, 'quantity' => 1]
            ]
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.discount', 10);
    }

    public function test_check_coupon_usage_limit()
    {
        $coupon = Coupon::create([
            'code' => 'LIMIT1',
            'amount' => 10,
            'type' => 'flat',
            'is_global' => true,
            'status' => 1,
            'max_usage' => 1,
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
        ]);

        // Create an order using this coupon to simulate usage
        Order::factory()->create([
            'coupons_id' => $coupon->id,
            'user_id' => $this->user->id,
            'status' => '1',
        ]);

        $response = $this->postJson('/api/check-coupon', [
            'coupon_code' => 'LIMIT1',
            'total_amount' => 100
        ]);

        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'Coupon has reached maximum usage limit.']);
    }
}

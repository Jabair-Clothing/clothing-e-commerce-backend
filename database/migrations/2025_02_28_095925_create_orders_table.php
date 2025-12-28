<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_code');
            $table->string('user_id')->nullable();
            $table->string('shipping_id')->nullable();
            $table->string('status')->default(0);
            $table->string('status_chnange_desc')->default(0);
            $table->string('item_subtotal');
            $table->string('shipping_chaege')->nullable();
            $table->string('total_amount');
            $table->string('coupons_id')->nullable();
            $table->string('discount')->nullable();
            $table->string('user_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

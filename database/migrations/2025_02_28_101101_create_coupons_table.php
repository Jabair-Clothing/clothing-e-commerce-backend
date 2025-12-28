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
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // coupon code
            $table->decimal('amount', 10, 2); // discount amount (flat or %)
            $table->enum('type', ['flat', 'percent'])->default('flat'); // flat discount or percentage
            $table->boolean('is_global')->default(true); // true = applies to all products, false = only selected
            $table->integer('max_usage')->nullable(); // how many times coupon can be used
            $table->integer('min_pur')->default(0);
            $table->integer('max_usage_per_user')->nullable(); // limit per user
            $table->date('start_date')->nullable(); // optional, when coupon starts
            $table->date('end_date')->nullable();   // optional, when coupon expires
            $table->integer('status')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};

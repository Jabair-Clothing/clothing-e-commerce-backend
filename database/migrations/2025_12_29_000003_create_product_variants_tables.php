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
        // 4. product_skus table
        Schema::create('product_skus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');

            $table->string('sku')->unique(); // e.g., "TSHIRT-RED-XL"
            $table->integer('quantity')->default(0);

            // If null, use product base_price. If set, this overrides it.
            $table->decimal('price', 10, 2)->nullable();

            // If null, use product discount.
            $table->decimal('discount_price', 10, 2)->nullable();

            $table->timestamps();
        });

        // 5. product_sku_attributes
        Schema::create('product_sku_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_sku_id')->constrained('product_skus')->onDelete('cascade');
            $table->foreignId('attribute_id')->constrained('attributes')->onDelete('cascade');
            $table->foreignId('attribute_value_id')->constrained('attribute_values')->onDelete('cascade');
            $table->integer('product_image_id')->nullable();
            $table->timestamps();

            // Helps duplicate checks (e.g. Ensure you don't have two variants for Red-XL)
            $table->unique(['product_sku_id', 'attribute_id', 'attribute_value_id'], 'sku_attr_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_sku_attributes');
        Schema::dropIfExists('product_skus');
    }
};

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
        // 4. product_variants table
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');

            $table->string('sku')->unique(); // e.g., "TSHIRT-RED-XL"

            // If null, use product base_price. If set, this overrides it.
            $table->decimal('price', 10, 2)->nullable();

            // If null, use product discount.
            $table->decimal('discount_price', 10, 2)->nullable();

            $table->integer('stock_quantity')->default(0);

            $table->timestamps();
        });

        // 5. variant_attribute_values
        Schema::create('variant_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->onDelete('cascade');
            $table->foreignId('attribute_value_id')->constrained('attribute_values')->onDelete('cascade');
            // Helps duplicate checks (e.g. Ensure you don't have two variants for Red-XL)
            $table->unique(['product_variant_id', 'attribute_value_id'], 'variant_val_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('variant_attribute_values');
        Schema::dropIfExists('product_variants');
    }
};

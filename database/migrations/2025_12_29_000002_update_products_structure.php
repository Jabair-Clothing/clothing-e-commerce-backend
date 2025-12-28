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
        Schema::rename('items', 'products');

        Schema::table('products', function (Blueprint $table) {
            // Rename columns or add new ones
            $table->renameColumn('status', 'is_active');

            // Add new columns
            $table->decimal('base_price', 10, 2)->after('is_active');
            // Assuming cetagories table exists, we use category_id
            $table->foreignId('category_id')->nullable()->constrained('cetagories')->onDelete('cascade');

            // Drop columns moved to variants
            $table->dropColumn(['quantity', 'price', 'discount', 'is_bundle']);

            // Adjust nullable/types if needed (Assuming existing columns 'slug', 'name', 'meta_title' match requirements)
        });

        // Update is_active to boolean (it was string status default 1)
        // Since we cannot easily change type with data without strict mode often, we might leave it or try change()
        // But status string '1' might not cast to boolean true in DB easily depending on driver.
        // For now, let's keep it as is or change it if possible. 
        // User requested: $table->boolean('is_active')->default(true);
        // We will try to modify it.
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->renameColumn('is_active', 'status');
            $table->dropForeign(['category_id']);
            $table->dropColumn(['base_price', 'category_id']);
            $table->integer('quantity');
            $table->integer('price');
            $table->integer('discount')->nullable();
            $table->integer('is_bundle')->nullable();
            $table->string('status')->default(1)->change();
        });

        Schema::rename('products', 'items');
    }
};

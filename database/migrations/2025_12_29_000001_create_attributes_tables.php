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
        // 1. attributes table
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "Color", "Size"
            $table->string('code')->unique(); // e.g., "color", "size"
            $table->timestamps();
        });

        // 2. attribute_values table
        Schema::create('attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->onDelete('cascade');
            $table->string('value'); // e.g., "Red", "XL"
            $table->string('color_code')->nullable(); // e.g., "#FF0000"
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attribute_values');
        Schema::dropIfExists('attributes');
    }
};

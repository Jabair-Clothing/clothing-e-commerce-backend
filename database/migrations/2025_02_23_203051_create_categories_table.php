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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_category_id')->nullable()->constrained('parent_categories')->onDelete('cascade');
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->string('image')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->integer('count_view')->default(0);
            $table->integer('count_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};

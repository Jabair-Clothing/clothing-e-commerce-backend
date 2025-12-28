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
        Schema::create('challan_items', function (Blueprint $table) {
            $table->id();
            $table->integer('challan_id');
            $table->integer('item_id');
            $table->integer('quantity');
            $table->integer('buying_price');
            $table->string('item_name');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('challan_items');
    }
};

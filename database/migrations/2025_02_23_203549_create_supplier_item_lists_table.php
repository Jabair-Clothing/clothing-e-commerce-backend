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
        Schema::create('supplier_item_lists', function (Blueprint $table) {
            $table->id();
            $table->integer('supplier_id');
            $table->integer('item_id');
            $table->string('price');
            $table->string('quantity');
            $table->integer('challan_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_item_lists');
    }
};

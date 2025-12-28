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
        Schema::create('ourambassadors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('campus');
            $table->string('image')->nullable();
            $table->enum('status', ['0', '1'])->default('0');
            $table->string('bio');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ourambassadors');
    }
};

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
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');                   
            $table->string('slug')->unique();            
            $table->longText('content');                 
            $table->string('thumbnail')->nullable();    
            $table->string('category')->nullable();     
            $table->json('tags')->nullable();            
            $table->unsignedBigInteger('author_id');    
            $table->string('meta_title')->nullable();  
            $table->string('meta_description', 300)->nullable();
            $table->integer('views')->default(0);     
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'content',
        'thumbnail',
        'category',
        'tags',
        'author_id',
        'meta_title',
        'meta_description',
        'views',
        'status',
    ];

    // Cast JSON field
    protected $casts = [
        'tags' => 'array',
    ];
}

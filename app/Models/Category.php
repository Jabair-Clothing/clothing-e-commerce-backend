<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $table = 'categories';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'image',
        'parent_id',
        'status',
    ];

    // Subcategories (Create hierarchy)
    public function subCategories()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    // Parent Category
    public function parentCategory()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    // Products in this category
    public function products()
    {
        return $this->hasMany(Product::class);
    }
}

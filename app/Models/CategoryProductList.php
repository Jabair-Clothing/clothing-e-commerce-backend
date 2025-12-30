<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategoryProductList extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'item_id',
    ];

    // Relationship with Category
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
}

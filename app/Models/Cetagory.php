<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cetagory extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'description',
        'status',
        'slug',
    ];

    // Relationship with Cetagory_Product_list
    public function productLists()
    {
        return $this->hasMany(Cetagory_Product_list::class, 'category_id');
    }
}

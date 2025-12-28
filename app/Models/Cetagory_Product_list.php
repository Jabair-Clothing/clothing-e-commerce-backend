<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cetagory_Product_list extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'item_id',
    ];

    // Relationship with Cetagory
    public function category()
    {
        return $this->belongsTo(Cetagory::class, 'category_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order_list extends Model
{
    use HasFactory;
    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'price',
        'is_bundle',
    ];

    // Relationship with Item
    public function item()
    {
        return $this->belongsTo(Item::class, 'product_id');
    }
}

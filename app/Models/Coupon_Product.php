<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon_Product extends Model
{
    use HasFactory;

    // Explicitly specify the table name
    protected $table = 'coupon__products';

    protected $fillable = [
        'coupon_id',
        'item_id',
    ];

    // Disable timestamps if your pivot table doesn't have created_at/updated_at
    public $timestamps = true; 

    public function coupon()
    {
        return $this->belongsTo(Coupon::class, 'coupon_id', 'id');
    }

    // Changed from item() to product()
    public function product()
    {
        return $this->belongsTo(Product::class, 'item_id', 'id');
    }
}

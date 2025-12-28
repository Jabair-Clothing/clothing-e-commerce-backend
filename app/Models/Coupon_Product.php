<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon_Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'coupon_id',
        'item_id',
    ];

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}

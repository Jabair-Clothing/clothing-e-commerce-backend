<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'amount',
        'type',
        'is_global',
        'max_usage',
        'max_usage_per_user',
        'start_date',
        'end_date',
        'status',
        'min_pur',
    ];

    public function activities()
    {
        return $this->hasMany(Activity::class, 'relatable_id');
    }

    // Updated relationship name from items() to products()
    public function products()
    {
        return $this->belongsToMany(
            Product::class,
            'coupon__products',
            'coupon_id',
            'item_id',
            'id',
            'id'
        );
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'coupons_id');
    }
}

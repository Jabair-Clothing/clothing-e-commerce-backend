<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;
    protected $fillable = [
        'order_id',
        'status',
        'amount',
        'padi_amount',
        'payment_type',
        'trxed',
        'phone'
    ];

    // Relationship with Order
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    // Relationship with Activity model
    public function activities()
    {
        return $this->hasMany(Activity::class, 'relatable_id');
    }
}

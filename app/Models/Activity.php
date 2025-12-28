<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    use HasFactory;

    protected $fillable = [
        'relatable_id',
        'type',
        'user_id',
        'description',
    ];

    // Relationship with User
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Dynamic relation for different types (Expense, Coupon, Order, Payment)
    public function relatable()
    {
        return $this->morphTo();
    }
}

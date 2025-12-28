<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reating extends Model
{
    use HasFactory;
    protected $fillable = [
        'User_id',
        'status',
        'star',
        'rating',
        'product_id',
    ];

    // Relationship with Item (product)
    public function product()
    {
        return $this->belongsTo(Item::class, 'product_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'User_id');
    }
}

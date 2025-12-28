<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transition extends Model
{
    use HasFactory;
    protected $fillable = [
        'payment_id',
        'amount',
    ];

    // Relationship with Payment
    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }
}

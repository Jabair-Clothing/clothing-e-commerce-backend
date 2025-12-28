<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Referrals extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'status',
        'commission_percentage',
    ];
}

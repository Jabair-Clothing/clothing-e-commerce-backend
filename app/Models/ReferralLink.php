<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferralLink extends Model
{
    use HasFactory;
    protected $fillable = [
        'referral_id',
        'item_id',
        'referrallink',
    ];
}

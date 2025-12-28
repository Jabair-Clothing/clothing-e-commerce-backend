<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferralOrder extends Model
{
    use HasFactory;
    protected $fillable = [
        'referral_links_id',
        'order_id',
        'commission_earned',
    ];
}

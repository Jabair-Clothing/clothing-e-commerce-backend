<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingAddress extends Model
{
    use HasFactory;

    protected $fillable = [
        'User_id',
        'f_name',
        'l_name',
        'phone',
        'address',
        'city',
        'zip'
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AmbassadorApplication extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'email',
        'campus',
        'phone',
        'status',
        'message',
        'image',
    ];
}

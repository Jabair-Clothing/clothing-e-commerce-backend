<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ourambassador extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'campus',
        'image',
        'status',
        'bio'
    ];
}

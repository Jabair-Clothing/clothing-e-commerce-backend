<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CareerForms extends Model
{
    use HasFactory;

    protected $fillable = [
        'career_id',
        'name',
        'email',
        'phone',
        'cover_later',
        'cv',
    ];

    public function career()
    {
        return $this->belongsTo(Career::class);
    }
}

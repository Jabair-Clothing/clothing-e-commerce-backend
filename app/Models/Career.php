<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Career extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_title',
        'description',
        'vacancy',
        'job_type',
        'salary',
        'deadline',
        'department',
        'responsibilities',
        'status'
    ];

    // Relationships
    public function forms()
    {
        return $this->hasMany(CareerForms::class);
    }
}

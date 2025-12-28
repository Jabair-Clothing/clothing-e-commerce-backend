<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SocialLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'platform',
        'url'
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}

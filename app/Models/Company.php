<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'hero_title',
        'hero_subtitle',
        'hero_description',
        'about_title',
        'about_description1',
        'about_description2',
        'email',
        'phone',
        'location',
        'footer_text'
    ];

    public function socialLinks()
    {
        return $this->hasMany(SocialLink::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'user_id',
        'title',
        'amount',
        'description'
    ];

    // Relationship with File model
    public function proveFiles()
    {
        return $this->hasMany(File::class, 'relatable_id')->where('type', 'expense');
    }


    // Relationship with Activity model
    public function activities()
    {
        return $this->hasMany(Activity::class, 'relatable_id');
    }
}

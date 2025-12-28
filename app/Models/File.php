<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class File extends Model
{
    use HasFactory;
    protected $fillable = [
        'relatable_id',
        'type',
        'path'
    ];

    // Relationship with Item (product)
    public function product()
    {
        return $this->belongsTo(Item::class, 'relatable_id');
    }

    // Relationship with Challan
    public function challan()
    {
        return $this->belongsTo(Challan::class, 'relatable_id');
    }
}

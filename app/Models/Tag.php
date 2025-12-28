<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use HasFactory;
    protected $fillable = [
        'item_id',
        'tag',
        'slug'
    ];

    // Relationship with Item (product)
    public function product()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}

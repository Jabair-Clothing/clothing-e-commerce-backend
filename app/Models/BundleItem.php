<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BundleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'bundle_item_id',
        'bundle_quantity',
    ];

    // Relationship to fetch the bundle item
    public function bundleItem()
    {
        return $this->belongsTo(Item::class, 'bundle_item_id');
    }

    // Relationship to fetch the related item
    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}

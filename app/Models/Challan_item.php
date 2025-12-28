<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Challan_item extends Model
{
    use HasFactory;

    protected $fillable = [
        'challan_id',
        'item_id',
        'quantity',
        'buying_price',
        'item_name'
    ];

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function challan()
    {
        return $this->belongsTo(Challan::class, 'challan_id');
    }
}

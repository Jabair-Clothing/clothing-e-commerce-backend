<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'phone2',
        'address',
        'paid_amount'
    ];

    // Relationship with Challan
    public function challans()
    {
        return $this->hasMany(Challan::class, 'supplier_id');
    }
}

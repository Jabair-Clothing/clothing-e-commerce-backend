<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Challan extends Model
{
    use HasFactory;

    protected $fillable = [
        'Date',
        'user_id',
        'total',
        'delivery_price',
        'supplier_id'
    ];

    // Relationship with Supplier
    public function supplier()
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    // Relationship with User
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Relationship with Supplier_item_list
    public function supplierItems()
    {
        return $this->hasMany(Supplier_item_list::class, 'challan_id');
    }

    public function challanItems()
    {
        return $this->hasMany(Challan_item::class);
    }


    // Relationship with File (invoice)
    public function invoices()
    {
        return $this->hasMany(File::class, 'relatable_id')->where('type', 'challan');
    }
}

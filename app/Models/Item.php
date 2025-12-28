<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'short_description',
        'status',
        'quantity',
        'price',
        'discount',
        'is_bundle',
        'meta_title',
        'meta_description',
        'meta_keywords',
    ];

    // Relationship with Cetagory_Product_list
    public function categories()
    {
        return $this->hasMany(Cetagory_Product_list::class, 'item_id');
    }

    // Relationship with Tag
    public function tags()
    {
        return $this->hasMany(Tag::class, 'item_id');
    }


    // Relationship with BundleItem (to fetch related bundle items)
    public function bundleItems()
    {
        return $this->hasMany(BundleItem::class, 'bundle_item_id');
        return $this->hasMany(BundleItem::class, 'item_id');
    }

    // Relationship to fetch the actual items in the bundle
    public function relatedBundleItems()
    {
        return $this->hasManyThrough(
            Item::class,
            BundleItem::class,
            'bundle_item_id',
            'id',
            'id',
            'item_id',
        );
    }

    public function coupons()
    {
        return $this->belongsToMany(Coupon::class, 'coupon__products');
    }

    public function ratings()
    {
        return $this->hasMany(Reating::class, 'product_id');
    }

    public function images()
    {
        return $this->hasMany(File::class, 'relatable_id')
            ->where('type', 'product');
    }

    public function firstImage()
    {
        return $this->hasOne(File::class, 'relatable_id')
            ->where('type', 'product')
            ->oldest(); // or latest() depending on which one you want
    }
}

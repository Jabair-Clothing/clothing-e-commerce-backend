<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'short_description',
        'is_active',
        'base_price',
        'category_id',
        'meta_title',
        'meta_description',
        'meta_keywords',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'base_price' => 'decimal:2',
    ];

    // Relationship with Category
    public function category()
    {
        return $this->belongsTo(Cetagory::class, 'category_id');
    }

    // Variants
    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    // Images
    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    // Primary Image
    public function primaryImage()
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    // Relationship with Tag (Keep existing if compatible)
    public function tags()
    {
        return $this->hasMany(Tag::class, 'item_id'); // If tags table still uses item_id, we might need to update migration for tags too. 
    }


    public function coupons()
    {
        return $this->belongsToMany(Coupon::class, 'coupon__products', 'product_id', 'coupon_id');
    }

    public function ratings()
    {
        return $this->hasMany(Reating::class, 'product_id');
    }
}

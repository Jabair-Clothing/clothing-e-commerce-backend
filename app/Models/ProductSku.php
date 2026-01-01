<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductSku extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'sku',
        'quantity',
        'price',
        'discount_price',
        'product_image_id',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function productImage()
    {
        return $this->belongsTo(ProductImage::class, 'product_image_id');
    }

    public function attributes()
    {
        return $this->hasMany(ProductSkuAttribute::class);
    }
}

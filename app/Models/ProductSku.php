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
        'is_deleted',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function productImage()
    {
        return $this->belongsTo(ProductImage::class, 'product_image_id');
    }

    public function skuAttributes()
    {
        return $this->hasMany(ProductSkuAttribute::class, 'product_sku_id');
    }

    public function attributeValues()
    {
        return $this->belongsToMany(
            AttributeValue::class,
            'product_sku_attributes',
            'product_sku_id',
            'attribute_value_id'
        )->withPivot(['attribute_id', 'product_image_id']);
    }
}

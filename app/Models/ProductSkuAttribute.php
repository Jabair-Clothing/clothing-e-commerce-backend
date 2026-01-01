<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductSkuAttribute extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_sku_id',
        'attribute_id',
        'attribute_value_id',
        'product_image_id'
    ];

    public function productSku()
    {
        return $this->belongsTo(ProductSku::class);
    }

    public function attribute()
    {
        return $this->belongsTo(Attribute::class);
    }

    public function attributeValue()
    {
        return $this->belongsTo(AttributeValue::class);
    }

    public function productImage()
    {
        return $this->belongsTo(ProductImage::class);
    }
}

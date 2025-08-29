<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PickedUpItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function getProductNameAttribute()
    {
        return $this->orderItem->product_name ?? 'Product Name';
    }

    public function getProductImageAttribute()
    {
        // Check if we have the images column from order_items
        if (!empty($this->attributes['images'])) {
            return $this->attributes['images'];
        }
        // Fallback to default image
        return asset('uploads/profiles/no_image.jpeg');
    }
}

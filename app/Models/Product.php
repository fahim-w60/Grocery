<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Cart;    

class Product extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function carts()
    {
        return $this->hasMany(Cart::class);
    }
    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
    
    public function location()
    {
        return $this->belongsTo(Location::class, 'locationId', 'locationId');
    }
}

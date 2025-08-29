<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'order_number',
        'status',
        'delivery_date',
        'delivery_time',
        'delivery_notes',
        'shopper_id',
        'tax',
        'delivery_charges',
        'total',
        'confirmed_at',
        'picked_up_at',
        'out_for_delivery_at',
        'delivered_at',
        'cancelled_at'
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
        'confirmed_at',
        'picked_up_at',
        'out_for_delivery_at',
        'delivered_at',
        'cancelled_at'
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function shopper()
    {
        return $this->belongsTo(User::class, 'shopper_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

}



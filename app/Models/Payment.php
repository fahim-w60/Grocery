<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'order_id',
        'payment_method',
        'amount',
        'transaction_id',
        'payment_status',
        'payment_date',
        'stripe_payment_intent_id',
        'currency'
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}

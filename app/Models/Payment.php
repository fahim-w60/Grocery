<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_id',
        'payment_method',
        'amount',
        'shopper_amount',
        'transaction_id',
        'payment_status',
        'payment_date',
        'stripe_payment_intent_id',
        'currency',
        'crypto_currency',
        'wallet_address',
        'transaction_hash',
        'crypto_amount',
        'exchange_rate',
        'confirmations',
        'metadata',
        'paid_at'
    ];

    protected $casts = [
        'metadata' => 'array',
        'payment_date' => 'datetime',
        'paid_at' => 'datetime',
        'amount' => 'decimal:2',
        'crypto_amount' => 'decimal:8',
        'exchange_rate' => 'decimal:8',
        'payment_method' => 'string',
        'payment_status' => 'string'
    ];

    /**
     * The possible payment methods.
     *
     * @var array
     */
    public const PAYMENT_METHODS = [
        'card' => 'Credit/Debit Card',
        'crypto' => 'Cryptocurrency',
        'bank_transfer' => 'Bank Transfer',
        'other' => 'Other',
    ];

    /**
     * The possible payment statuses.
     *
     * @var array
     */
    public const PAYMENT_STATUSES = [
        'pending' => 'Pending',
        'processing' => 'Processing',
        'completed' => 'Completed',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
        'refunded' => 'Refunded',
    ];

    protected $dates = [
        'payment_date',
        'paid_at',
        'deleted_at',
        'created_at',
        'updated_at'
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}

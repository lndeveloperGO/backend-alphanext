<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


class Order extends Model
{
    protected $fillable = [
        'user_id','product_id','merchant_order_id','amount','status',
        'expires_at',
        'duitku_reference','payment_url','payment_method',
        'promo_code','promo_code_id','discount','paid_at','raw_callback'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'raw_callback' => 'array',
    ];

    public function isExpired(): bool
    {
        return $this->status === 'expired'
            || ($this->expires_at && now()->greaterThan($this->expires_at));
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}

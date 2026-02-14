<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromoRedemption extends Model
{
    protected $fillable = [
        'promo_code_id',
        'user_id',
        'order_id',
        'status',
    ];

    public function promoCode()
    {
        return $this->belongsTo(PromoCode::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PromoCode extends Model
{
    protected $fillable = [
        'code',
        'type',
        'value',
        'min_purchase',
        'max_uses',
        'used_count',
        'starts_at',
        'ends_at',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    // bikin field virtual ikut ke JSON
    protected $appends = ['status'];

    public function getStatusAttribute(): string
    {
        // 1) kalau dimatikan manual
        if (!$this->is_active) {
            return 'disabled';
        }

        // 2) kalau kuota habis
        if (!is_null($this->max_uses) && $this->used_count >= $this->max_uses) {
            return 'quota_exhausted';
        }

        $now = now();

        // 3) belum mulai
        if ($this->starts_at && $now->lt($this->starts_at)) {
            return 'upcoming';
        }

        // 4) sudah lewat
        if ($this->ends_at && $now->gt($this->ends_at)) {
            return 'expired';
        }

        // 5) selain itu aktif
        return 'active';
    }

    public function packages()
    {
        return $this->belongsToMany(\App\Models\Package::class, 'promo_code_packages')
            ->withTimestamps();
    }

    public function products()
    {
        return $this->belongsToMany(
            \App\Models\Product::class,
            'promo_code_products'
        );
    }

    public function redemptions()
    {
        return $this->hasMany(\App\Models\PromoRedemption::class);
    }
}

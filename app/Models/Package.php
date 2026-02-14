<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    protected $fillable = [
        'name',
        'type',
        'category_id',
        'duration_seconds',
        'is_active',
        'is_free',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_free' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function questions()
    {
        return $this->belongsToMany(
            Question::class,
            'package_questions'
        )->withPivot('order_no');
    }

    public function attempts()
    {
        return $this->hasMany(Attempt::class);
    }

    public function materials()
    {
        return $this->belongsToMany(Material::class, 'package_materials')
            ->withPivot(['sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function promoCodes()
    {
        return $this->belongsToMany(\App\Models\PromoCode::class, 'promo_code_packages')
            ->withTimestamps();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['name', 'type', 'package_id', 'price', 'access_days', 'is_active', 'grants_answer_key'];

    protected $casts = [
        'is_active' => 'boolean',
        'access_days' => 'integer',
        'grants_answer_key' => 'boolean',
    ];

    // SINGLE product (legacy)
    public function package()
    {
        return $this->belongsTo(\App\Models\Package::class);
        // atau ->belongsTo(Package::class, 'package_id')
    }

    // BUNDLE product
    public function packages()
    {
        return $this->belongsToMany(\App\Models\Package::class, 'product_packages')
            ->withPivot(['qty', 'sort_order'])
            ->withTimestamps()
            ->orderBy('product_packages.sort_order');
    }
}

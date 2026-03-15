<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SelectionEvent extends Model
{
    protected $fillable = [
        'title',
        'description',
        'type',
        'start_date',
        'end_date',
        'color',
    ];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'selection_event_product');
    }
}

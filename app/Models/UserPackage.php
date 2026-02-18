<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPackage extends Model
{
    protected $fillable = ['user_id','package_id','order_id','starts_at','ends_at', 'has_answer_key'];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'has_answer_key' => 'boolean',
    ];
}

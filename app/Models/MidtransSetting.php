<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MidtransSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_key',
        'client_key',
        'is_production',
        'merchant_name',
        'expiry_duration',
        'expiry_unit',
    ];

    protected $casts = [
        'is_production' => 'boolean',
        'expiry_duration' => 'integer',
    ];
}

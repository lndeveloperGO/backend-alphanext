<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MidtransSetting;
use Illuminate\Http\Request;

class MidtransConfigController extends Controller
{
    /**
     * Get Midtrans configuration for frontend.
     * Only returns client_key and is_production.
     */
    public function show()
    {
        $settings = MidtransSetting::first();

        $data = [
            'client_key' => $settings->client_key ?? config('services.midtrans.client_key'),
            'is_production' => $settings 
                ? $settings->is_production 
                : filter_var(config('services.midtrans.is_production', false), FILTER_VALIDATE_BOOLEAN),
        ];

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }
}

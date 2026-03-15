<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\MidtransSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminMidtransSettingController extends Controller
{
    public function index()
    {
        $settings = MidtransSetting::first();
        
        // Fallback to config if DB is empty
        if (!$settings) {
            $settings = new MidtransSetting([
                'server_key' => config('services.midtrans.server_key'),
                'client_key' => config('services.midtrans.client_key'),
                'is_production' => config('services.midtrans.is_production', false),
                'merchant_name' => config('app.name'),
                'expiry_duration' => 15,
                'expiry_unit' => 'minutes',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data' => $settings
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'server_key' => 'required|string',
            'client_key' => 'required|string',
            'is_production' => 'required|boolean',
            'merchant_name' => 'nullable|string',
            'expiry_duration' => 'required|integer|min:1',
            'expiry_unit' => 'required|in:minutes,hours,days',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $settings = MidtransSetting::first() ?: new MidtransSetting();
        $settings->fill($validator->validated());
        $settings->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Midtrans settings updated successfully',
            'data' => $settings
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminSiteSettingController extends Controller
{
    public function index()
    {
        $settings = SiteSetting::all();
        return response()->json([
            'success' => true,
            'data' => $settings
        ]);
    }

    public function update(Request $request, $id)
    {
        $setting = SiteSetting::findOrFail($id);

        $data = $request->validate([
            'value' => $setting->type === 'image' ? 'nullable' : 'required',
            'image' => $setting->type === 'image' ? 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048' : 'nullable',
        ]);

        if ($setting->type === 'image' && $request->hasFile('image')) {
            // Delete old internal image if exists
            if ($setting->value && !filter_var($setting->value, FILTER_VALIDATE_URL)) {
                Storage::disk('public')->delete($setting->value);
            }
            
            $path = $request->file('image')->store('site-settings', 'public');
            $setting->value = $path;
        } else if ($request->has('value')) {
            $setting->value = $request->value;
        }

        $setting->save();

        return response()->json([
            'success' => true,
            'data' => $setting
        ]);
    }
}

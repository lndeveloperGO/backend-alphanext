<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\PromoCode;
use Illuminate\Http\Request;

class UserDashboardController extends Controller
{
    public function index(Request $request)
    {
        // PRODUCTS
        $products = Product::query()
            ->where('is_active', true)
            ->with([
                'category:id,name,type',
                'package:id,name,type,category_id',
                'packages:id,name,type,category_id',
            ])
            ->orderBy('id', 'desc')
            ->get();

        $bundles = $products->where('type', 'bundle')->values();
        $regular = $products->where('type', 'single')->values();

        // PROMO CODES (yang benar-benar bisa dipakai saat ini)
        $now = now();
        $promos = PromoCode::query()
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->where(function ($q) {
                $q->whereNull('max_uses')->orWhereColumn('used_count', '<', 'max_uses');
            })
            ->orderBy('id', 'desc')
            ->get([
                'id','code','type','value','min_purchase','max_uses','used_count',
                'starts_at','ends_at','is_active'
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'bundles' => $bundles,
                'regular' => $regular,
                'promos' => $promos,
            ],
        ]);
    }
}

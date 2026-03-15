<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class PublicProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::query()
            ->where('is_active', true)
            ->with([
                'category:id,name,type',
                'package:id,name,type,category_id',
                'packages:id,name,type,category_id',
            ])
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'bundles' => $products->where('type', 'bundle')->values(),
                'regular' => $products->where('type', 'single')->values(),
            ],
        ]);
    }

    public function show(\App\Models\Product $product)
    {
        abort_unless($product->is_active, 404);

        $product->load([
            'category:id,name,type',
            'package:id,name,type,category_id,duration_seconds,is_active,is_free',
            'packages:id,name,type,category_id,duration_seconds,is_active,is_free',
        ]);

        // kalau bundle, pastikan packages terurut pivot sort_order (model kamu sudah orderBy pivot_sort_order)
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $product->id,
                'name' => $product->name,
                'type' => $product->type, // single|bundle
                'price' => (int) $product->price,
                'is_active' => (bool) $product->is_active,
                'category' => $product->category ? [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                    'type' => $product->category->type,
                ] : null,

                // untuk single
                'package' => $product->type === 'single'
                    ? ($product->package ? [
                        'id' => $product->package->id,
                        'name' => $product->package->name,
                        'type' => $product->package->type,
                        'category_id' => $product->package->category_id,
                        'duration_seconds' => (int) $product->package->duration_seconds,
                        'is_active' => (bool) $product->package->is_active,
                        'is_free' => (bool) ($product->package->is_free ?? false),
                    ] : null)
                    : null,

                // untuk bundle
                'packages' => $product->type === 'bundle'
                    ? $product->packages->map(fn($p) => [
                        'id' => $p->id,
                        'name' => $p->name,
                        'type' => $p->type,
                        'category_id' => $p->category_id,
                        'duration_seconds' => (int) $p->duration_seconds,
                        'is_active' => (bool) $p->is_active,
                        'is_free' => (bool) ($p->is_free ?? false),
                        'qty' => (int) ($p->pivot->qty ?? 1),
                        'sort_order' => (int) ($p->pivot->sort_order ?? 1),
                    ])->values()
                    : [],
            ],
        ]);
    }
}

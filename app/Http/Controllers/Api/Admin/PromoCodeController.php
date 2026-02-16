<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Product;
use App\Models\PromoCode;
use Illuminate\Http\Request;

class PromoCodeController extends Controller
{
    public function index(Request $request)
    {
        $q = PromoCode::query()
            ->when($request->search, function ($qq) use ($request) {
                $s = strtoupper(trim($request->search));
                $qq->where('code', 'like', "%{$s}%");
            })
            ->when(
                !is_null($request->is_active),
                fn($qq) =>
                $qq->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN))
            )
            ->orderBy('id', 'desc')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $q]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:promo_codes,code'],
            'type' => ['required', 'in:percent,fixed'],
            'value' => ['required', 'integer', 'min:1'],
            'min_purchase' => ['nullable', 'integer', 'min:0'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['required', 'boolean'],
        ]);

        $data['code'] = strtoupper(trim($data['code']));
        $data['min_purchase'] = $data['min_purchase'] ?? 0;

        if ($data['type'] === 'percent' && $data['value'] > 100) {
            return response()->json([
                'success' => false,
                'message' => 'Untuk type percent, value maksimal 100.',
            ], 422);
        }

        $promo = PromoCode::create($data);

        return response()->json(['success' => true, 'data' => $promo], 201);
    }

    public function show(PromoCode $promo_code)
    {
        $promo_code->load([
            'packages:id,name,type',
            'products:id,name,type,price',
        ]);

        return response()->json(['success' => true, 'data' => $promo_code]);
    }

    public function syncPackages(Request $request, PromoCode $promo)
    {
        $data = $request->validate([
            'packages' => ['required', 'array'],
            'packages.*.package_id' => ['required', 'integer', 'exists:packages,id'],
        ]);

        $sync = collect($data['packages'])
            ->pluck('package_id')
            ->unique()
            ->values()
            ->all();

        $promo->packages()->sync($sync);

        return response()->json([
            'success' => true,
            'message' => 'Packages synced'
        ]);
    }

    public function syncProducts(Request $request, PromoCode $promo)
    {
        $data = $request->validate([
            'products' => ['required', 'array'],
            'products.*.product_id' => ['required', 'integer', 'exists:products,id'],
        ]);

        $sync = collect($data['products'])
            ->pluck('product_id')
            ->unique()
            ->values()
            ->all();

        $promo->products()->sync($sync);

        return response()->json([
            'success' => true,
            'message' => 'Products synced'
        ]);
    }

    public function assignments(PromoCode $promo_code)
    {
        $promo_code->load([
            'packages:id,name',
            'products:id,name',
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'promo_code_id' => $promo_code->id,
                'packages' => $promo_code->packages,
                'products' => $promo_code->products,
            ],
        ]);
    }

    public function update(Request $request, PromoCode $promo_code)
    {
        $data = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:50', 'unique:promo_codes,code,' . $promo_code->id],
            'type' => ['sometimes', 'required', 'in:percent,fixed'],
            'value' => ['sometimes', 'required', 'integer', 'min:1'],
            'min_purchase' => ['nullable', 'integer', 'min:0'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ]);

        if (isset($data['code'])) {
            $data['code'] = strtoupper(trim($data['code']));
        }

        $type = $data['type'] ?? $promo_code->type;
        $value = $data['value'] ?? $promo_code->value;
        if ($type === 'percent' && $value > 100) {
            return response()->json([
                'success' => false,
                'message' => 'Untuk type percent, value maksimal 100.',
            ], 422);
        }

        if (array_key_exists('min_purchase', $data) && $data['min_purchase'] === null) {
            $data['min_purchase'] = 0;
        }

        $promo_code->update($data);

        return response()->json(['success' => true, 'data' => $promo_code->fresh()]);
    }

    public function detachPackage(PromoCode $promo, Package $package)
    {
        // detach itu idempotent: kalau relasi ga ada, ga error
        $promo->packages()->detach($package->id);

        return response()->json([
            'success' => true,
            'message' => 'Package detached',
        ]);
    }

    public function detachProduct(PromoCode $promo, Product $product)
    {
        $promo->products()->detach($product->id);

        return response()->json([
            'success' => true,
            'message' => 'Product detached',
        ]);
    }

    public function destroy(PromoCode $promo_code)
    {
        $promo_code->delete();

        return response()->json(['success' => true, 'message' => 'Deleted']);
    }
}

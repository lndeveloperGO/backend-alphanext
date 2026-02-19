<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $q = Product::query()
            ->select(['id','type','name','package_id','price','access_days','is_active','grants_answer_key','created_at'])
            ->with([
                'package:id,name,type,category_id',
                'packages:id,name,type,category_id',
            ])
            ->when($request->search, function ($qq) use ($request) {
                $s = $request->search;
                $qq->where('name', 'like', "%{$s}%");
            })
            ->when(!is_null($request->is_active), fn ($qq) =>
                $qq->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN))
            )
            ->orderBy('id', 'desc')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $q]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'in:single,bundle'],
            'name' => ['required', 'string', 'max:150'],
            'price' => ['required', 'integer', 'min:0'],
            'access_days' => ['sometimes', 'integer', 'min:0', 'max:3650'], // default 30 kalau gak dikirim
            'is_active' => ['required', 'boolean'],
            'grants_answer_key' => ['sometimes', 'boolean'],

            // SINGLE
            'package_id' => ['required_if:type,single', 'nullable', 'integer', 'exists:packages,id'],

            // BUNDLE
            'packages' => ['required_if:type,bundle', 'array', 'min:1'],
            'packages.*.package_id' => ['required', 'integer', 'exists:packages,id'],
            'packages.*.qty' => ['sometimes', 'integer', 'min:1'],
            'packages.*.sort_order' => ['sometimes', 'integer', 'min:1'],
        ]);

        $type = $data['type'];

        // enforce unique untuk SINGLE (1 package + 1 grants_answer_key combination)
        if ($type === 'single') {
            $exists = Product::where('package_id', $data['package_id'])
                ->where('grants_answer_key', $data['grants_answer_key'] ?? false)
                ->exists();
            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Package dengan kombinasi grants_answer_key ini sudah punya product.',
                ], 422);
            }
        }

        $product = Product::create([
            'type' => $type,
            'name' => $data['name'],
            'package_id' => $type === 'single' ? $data['package_id'] : null,
            'price' => $data['price'],
            'access_days' => $data['access_days'] ?? 30, // ✅ default 30 hari
            'is_active' => $data['is_active'],
            'grants_answer_key' => $data['grants_answer_key'] ?? false,
        ]);

        if ($type === 'bundle') {
            $sync = collect($data['packages'])->mapWithKeys(fn ($p) => [
                $p['package_id'] => [
                    'qty' => $p['qty'] ?? 1,
                    'sort_order' => $p['sort_order'] ?? 1,
                ]
            ])->toArray();

            $product->packages()->sync($sync);
        }

        return response()->json([
            'success' => true,
            'data' => $product->load([
                'package:id,name,type,category_id',
                'packages:id,name,type,category_id',
            ]),
        ], 201);
    }

    public function show(Product $product)
    {
        return response()->json([
            'success' => true,
            'data' => $product->load([
                'package:id,name,type,category_id',
                'packages:id,name,type,category_id',
            ]),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'type' => ['sometimes', 'required', 'in:single,bundle'],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'price' => ['sometimes', 'required', 'integer', 'min:0'],
            'access_days' => ['sometimes', 'integer', 'min:0', 'max:3650'], // ✅ default tetap existing kalau gak dikirim
            'is_active' => ['sometimes', 'required', 'boolean'],
            'grants_answer_key' => ['sometimes', 'boolean'],

            // SINGLE
            'package_id' => ['required_if:type,single', 'nullable', 'integer', 'exists:packages,id'],

            // BUNDLE
            'packages' => ['required_if:type,bundle', 'array', 'min:1'],
            'packages.*.package_id' => ['required', 'integer', 'exists:packages,id'],
            'packages.*.qty' => ['sometimes', 'integer', 'min:1'],
            'packages.*.sort_order' => ['sometimes', 'integer', 'min:1'],
        ]);

        $type = $data['type'] ?? $product->type;

        // enforce unique untuk SINGLE jika package_id atau grants_answer_key diubah
        if ($type === 'single') {
            $pid = $data['package_id'] ?? $product->package_id;
            $gak = array_key_exists('grants_answer_key', $data) 
                ? $data['grants_answer_key'] 
                : $product->grants_answer_key;

            $exists = Product::where('id', '!=', $product->id)
                ->where('package_id', $pid)
                ->where('grants_answer_key', $gak)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kombinasi Package dan grants_answer_key sudah dipakai product lain.',
                ], 422);
            }
        }

        $product->update([
            'type' => $type,
            'name' => $data['name'] ?? $product->name,
            'package_id' => $type === 'single'
                ? ($data['package_id'] ?? $product->package_id)
                : null,
            'price' => $data['price'] ?? $product->price,
            'access_days' => $data['access_days'] ?? $product->access_days,
            'is_active' => $data['is_active'] ?? $product->is_active,
            'grants_answer_key' => $data['grants_answer_key'] ?? $product->grants_answer_key,
        ]);

        if ($type === 'bundle') {
            if (array_key_exists('packages', $data)) {
                $sync = collect($data['packages'])->mapWithKeys(fn ($p) => [
                    $p['package_id'] => [
                        'qty' => $p['qty'] ?? 1,
                        'sort_order' => $p['sort_order'] ?? 1,
                    ]
                ])->toArray();

                $product->packages()->sync($sync);
            }
        } else {
            $product->packages()->detach();
        }

        return response()->json([
            'success' => true,
            'data' => $product->fresh()->load([
                'package:id,name,type,category_id',
                'packages:id,name,type,category_id',
            ]),
        ]);
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return response()->json(['success' => true, 'message' => 'Deleted']);
    }
}

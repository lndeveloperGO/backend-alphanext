<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PromoController extends Controller
{
    public function __construct(private OrderService $orders) {}

    public function validateCode(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required','integer','exists:products,id'],
            'promo_code' => ['required','string','max:50'],
        ]);

        try {
            $res = $this->orders->previewPromo(
                userId: $request->user()->id,
                productId: (int) $data['product_id'],
                promoCode: (string) $data['promo_code']
            );

            return response()->json([
                'success' => true,
                'data' => $res,
            ]);
        } catch (ValidationException $e) {
            // biar FE dapet message yang sama dengan create order
            $msg = $e->errors()['promo_code'][0] ?? 'Promo tidak valid.';

            return response()->json([
                'success' => false,
                'message' => $msg,
                'errors' => $e->errors(),
            ], 422);
        }
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\MidtransService;
use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderPaymentController extends Controller
{
    public function __construct(
        private OrderService $orders,
        private MidtransService $midtrans
    ) {}

    public function pay(Request $request, Order $order)
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        if ($order->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Order sudah diproses.'], 422);
        }

        if ($this->orders->shouldExpire($order)) {
            $this->orders->markExpired($order);

            return response()->json([
                'success' => false,
                'message' => 'Order expired. Silakan buat order baru.',
                'meta' => ['server_now' => now()->toIso8601String()],
            ], 422);
        }

        $snap = $this->midtrans->createSnap($order, $request->user());

        if (!$snap['redirect_url']) {
            return response()->json(['success' => false, 'message' => 'Gagal membuat payment link.'], 500);
        }

        $order->update([
            'payment_method' => 'midtrans',
            'payment_url' => $snap['redirect_url'],
            // 'midtrans_token' => $snap['token'], // kalau kamu sudah tambah kolom
        ]);

        return response()->json([
            'success' => true,
            'data' => $order->fresh()->load(['product:id,name,type,price','items']),
            'meta' => ['server_now' => now()->toIso8601String()],
        ]);
    }
}

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

        // kalau sudah paid/expired/failed, jangan lanjut create snap
        if ($order->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Order sudah diproses.',
                'data' => [
                    'id' => $order->id,
                    'status' => $order->status,
                    'payment_url' => $order->payment_url,
                ],
                'meta' => ['server_now' => now()->toIso8601String()],
            ], 422);
        }

        // expire guard (based on expires_at)
        if ($this->orders->shouldExpire($order)) {
            $this->orders->markExpired($order);
            $order = $order->fresh();

            return response()->json([
                'success' => false,
                'message' => 'Order expired. Silakan buat order baru.',
                'data' => [
                    'id' => $order->id,
                    'status' => $order->status,
                    'expires_at' => optional($order->expires_at)->toIso8601String(),
                    'expires_in_seconds' => 0,
                ],
                'meta' => ['server_now' => now()->toIso8601String()],
            ], 422);
        }

        // idempotent: kalau link sudah ada, balikin yang ada (biar gak dobel create transaction)
        if (!empty($order->payment_url)) {
            $expiresIn = $order->expires_at
                ? max(0, now()->diffInSeconds($order->expires_at, false))
                : null;

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $order->id,
                    'merchant_order_id' => $order->merchant_order_id,
                    'status' => $order->status,
                    'amount' => (int) $order->amount,
                    'discount' => (int) $order->discount,
                    'promo_code' => $order->promo_code,
                    'payment_method' => $order->payment_method,
                    'payment_url' => $order->payment_url,
                    'midtrans_token' => $order->midtrans_token,
                    'expires_at' => optional($order->expires_at)->toIso8601String(),
                    'expires_in_seconds' => $expiresIn,
                ],
                'meta' => ['server_now' => now()->toIso8601String()],
            ]);
        }

        // create snap
        $snap = $this->midtrans->createSnap($order, $request->user());

        if (empty($snap['redirect_url'])) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat payment link.',
                'meta' => ['server_now' => now()->toIso8601String()],
            ], 500);
        }

        $order->update([
            'payment_method' => 'midtrans',
            'payment_url' => $snap['redirect_url'],
            'midtrans_token' => $snap['token'] ?? null,
        ]);

        $order = $order->fresh();

        $expiresIn = $order->expires_at
            ? max(0, now()->diffInSeconds($order->expires_at, false))
            : null;

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $order->id,
                'merchant_order_id' => $order->merchant_order_id,
                'status' => $order->status,
                'amount' => (int) $order->amount,
                'discount' => (int) $order->discount,
                'promo_code' => $order->promo_code,
                'payment_method' => $order->payment_method,
                'payment_url' => $order->payment_url,
                'midtrans_token' => $order->midtrans_token,
                'expires_at' => optional($order->expires_at)->toIso8601String(),
                'expires_in_seconds' => $expiresIn,
            ],
            'meta' => ['server_now' => now()->toIso8601String()],
        ]);
    }
}

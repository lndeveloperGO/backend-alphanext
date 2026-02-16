<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Midtrans\Config;
use Midtrans\Snap;

class MidtransService
{
    public function __construct()
    {
        Config::$serverKey = (string) config('services.midtrans.server_key');

        Config::$isProduction = filter_var(
            config('services.midtrans.is_production', false),
            FILTER_VALIDATE_BOOLEAN
        );
        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    public function createSnap(Order $order, User $user): array
    {
        $startTime = now()->format('Y-m-d H:i:s O');

        // kalau expires_at null (harusnya tidak untuk pending), fallback 15 menit
        $durationMinutes = $order->expires_at
            ? max(1, (int) now()->diffInMinutes($order->expires_at, false))
            : 15;

        $order->loadMissing(['product']);

        $params = [
            'transaction_details' => [
                'order_id' => $order->merchant_order_id,
                'gross_amount' => (int) $order->amount,
            ],
            'customer_details' => [
                'first_name' => $user->name ?? 'User',
                'email' => $user->email ?? null,
                'phone' => $user->phone ?? null,
            ],
            'item_details' => [
                [
                    'id' => (string) $order->product_id,
                    'price' => (int) $order->amount,
                    'quantity' => 1,
                    'name' => $order->product?->name ?? ('Order #' . $order->id),
                ],
            ],
            'expiry' => [
                'start_time' => $startTime,
                'unit' => 'minutes',
                'duration' => $durationMinutes,
            ],
        ];

        $resp = Snap::createTransaction($params);

        return [
            'token' => $resp->token ?? null,
            'redirect_url' => $resp->redirect_url ?? null,
            'raw' => $resp,
        ];
    }

    public function verifySignature(array $payload): bool
    {
        $serverKey = (string) env('MIDTRANS_SERVER_KEY');
        $orderId = (string) ($payload['order_id'] ?? '');
        $statusCode = (string) ($payload['status_code'] ?? '');
        $grossAmount = (string) ($payload['gross_amount'] ?? '');
        $signature = (string) ($payload['signature_key'] ?? '');

        $computed = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);
        return hash_equals($computed, $signature);
    }
}

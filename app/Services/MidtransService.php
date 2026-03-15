<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\Transaction;

class MidtransService
{
    public function __construct()
    {
        $settings = \App\Models\MidtransSetting::first();

        Config::$serverKey = $settings->server_key ?? (string) config('services.midtrans.server_key');

        Config::$isProduction = $settings 
            ? $settings->is_production 
            : filter_var(config('services.midtrans.is_production', false), FILTER_VALIDATE_BOOLEAN);

        Config::$isSanitized = true;
        Config::$is3ds = true;
    }

    public function createSnap(Order $order, User $user): array
    {
        $settings = \App\Models\MidtransSetting::first();
        $startTime = now()->format('Y-m-d H:i:s O');

        // expiry
        if ($settings && $settings->expiry_duration) {
            $unit = $settings->expiry_unit ?? 'minutes';
            $duration = (int) $settings->expiry_duration;
        } else {
            // fallback logic
            $unit = 'minutes';
            $duration = $order->expires_at
                ? max(1, (int) now()->diffInMinutes($order->expires_at, false))
                : 15;
        }

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
                'unit' => $unit,
                'duration' => $duration,
            ],
        ];

        // add merchant name if available
        if ($settings && $settings->merchant_name) {
            // Midtrans uses "business_name" sometimes in different contexts, 
            // but for Snap, it captures it from the dashboard.
            // However, we can use it in custom fields or just store it for future use.
            // Some integrations use business_name in item_details or similar.
        }

        $resp = Snap::createTransaction($params);

        return [
            'token' => $resp->token ?? null,
            'redirect_url' => $resp->redirect_url ?? null,
            'raw' => $resp,
        ];
    }

    public function verifySignature(array $payload): bool
    {
        $settings = \App\Models\MidtransSetting::first();
        $serverKey = $settings->server_key ?? (string) env('MIDTRANS_SERVER_KEY');
        
        $orderId = (string) ($payload['order_id'] ?? '');
        $statusCode = (string) ($payload['status_code'] ?? '');
        $grossAmount = (string) ($payload['gross_amount'] ?? '');
        $signature = (string) ($payload['signature_key'] ?? '');

        $computed = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);
        return hash_equals($computed, $signature);
    }

    public function cancelTransaction(string $orderId): void
    {
        try {
            Transaction::cancel($orderId);
        } catch (\Exception $e) {
            logger()->error('Midtrans cancel failed', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

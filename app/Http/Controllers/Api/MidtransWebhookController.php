<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\MidtransService;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MidtransWebhookController extends Controller
{
    public function __construct(
        private MidtransService $midtrans,
        private OrderService $orders
    ) {}

    public function notify(Request $request)
    {
        $payload = $request->all();

        if (!$this->midtrans->verifySignature($payload)) {
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 403);
        }

        $midtransOrderId = $payload['order_id'] ?? null;
        if (!$midtransOrderId) {
            return response()->json(['success' => false, 'message' => 'Missing order_id'], 422);
        }

        $order = Order::where('merchant_order_id', $midtransOrderId)->first();
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }

        // simpan payload untuk audit/debug
        $order->update(['raw_callback' => $payload]);

        $tx = $payload['transaction_status'] ?? null;
        $fraud = $payload['fraud_status'] ?? null;

        if ($tx === 'capture') {
            // credit card capture
            if ($fraud === 'accept') $this->orders->markPaid($order);
        } elseif ($tx === 'settlement') {
            $this->orders->markPaid($order);
        } elseif ($tx === 'expire') {
            $this->orders->markExpired($order, $payload);
        } elseif (in_array($tx, ['cancel', 'deny'], true)) {
            $this->orders->markExpired($order, $payload);
        }

        return response()->json(['success' => true]);
    }

    public function handle(Request $request)
    {
        Log::info('MIDTRANS WEBHOOK:', $request->all());

        return response()->json(['success' => true]);
    }
}

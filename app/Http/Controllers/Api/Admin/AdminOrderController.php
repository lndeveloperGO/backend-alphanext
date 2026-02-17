<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\MidtransService;
use Illuminate\Http\Request;

class AdminOrderController extends Controller
{
    public function __construct(
        private OrderService $orders,
        private MidtransService $midtrans
    ) {}

    public function index(Request $request)
    {
        $q = Order::query()
            ->with(['product:id,name,type,price', 'items'])
            ->when($request->status, fn($qq) => $qq->where('status', $request->status))
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $q]);
    }

    public function markPaid(Order $order)
    {
        // Kalau pakai midtrans, cancel tagihan di midtrans biar gak double payment
        if ($order->payment_method === 'midtrans' && $order->merchant_order_id) {
            try {
                $this->midtrans->cancelTransaction($order->merchant_order_id);
            } catch (\Throwable $e) {
                logger()->error('Midtrans cancel error on markPaid', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $order = $this->orders->markPaid($order);

        return response()->json(['success' => true, 'data' => $order]);
    }

    /**
     * Cancel order by admin
     */
    public function cancel(Order $order)
    {
        // hanya bisa cancel pending
        if ($order->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya order pending yang bisa dibatalkan.'
            ], 422);
        }

        // kalau pakai midtrans → cancel juga di midtrans
        if ($order->payment_method === 'midtrans' && $order->merchant_order_id) {
            try {
                $this->midtrans->cancelTransaction($order->merchant_order_id);
            } catch (\Throwable $e) {
                logger()->error('Midtrans cancel error', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $order->update([
            'status' => 'cancelled'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order berhasil dibatalkan.',
            'data' => [
                'id' => $order->id,
                'status' => $order->status,
            ]
        ]);
    }
}

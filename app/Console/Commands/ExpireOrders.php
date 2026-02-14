<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Support\Facades\DB;

class ExpireOrders extends Command
{
    protected $signature = 'orders:expire';
    protected $description = 'Expire pending orders that passed expires_at';

    public function handle(OrderService $service)
    {
        $now = now();

        $orders = Order::where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->get();

        foreach ($orders as $order) {

            DB::transaction(function () use ($order, $service) {

                $order->refresh();

                if ($order->status !== 'pending') return;

                $order->update([
                    'status' => 'expired'
                ]);

                // 🔥 release promo slot
                $service->voidPromoIfAny($order);
            });
        }

        $this->info("Expired {$orders->count()} orders.");
    }
}

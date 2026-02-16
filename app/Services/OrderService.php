<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\PromoCode;
use App\Models\PromoRedemption;
use App\Models\UserPackage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function create(int $userId, int $productId, ?string $promoCode): Order
    {
        return DB::transaction(function () use ($userId, $productId, $promoCode) {

            $product = Product::where('is_active', true)
                ->with(['package', 'packages'])
                ->findOrFail($productId);

            $gross = (int) $product->price;
            $packageIds = $this->extractPackageIds($product);

            [$final, $discount, $promo] = $this->applyPromoIfAny(
                userId: $userId,
                code: $promoCode,
                gross: $gross,
                productId: (int) $product->id,
                packageIds: $packageIds
            );

            $status = $final === 0 ? 'paid' : 'pending';
            $expireMinutes = (int) config('orders.expire_minutes', 15);

            $order = Order::create([
                'user_id' => $userId,
                'product_id' => (int) $product->id,
                'merchant_order_id' => $this->genMerchantOrderId(),
                'amount' => $final,
                'discount' => $discount,
                'promo_code' => $promo?->code,
                'promo_code_id' => $promo?->id, // ✅ masukin biar konsisten dgn schema kamu
                'status' => $status,
                'paid_at' => $status === 'paid' ? now() : null,
                'expires_at' => $status === 'pending' ? now()->addMinutes($expireMinutes) : null,
            ]);

            // order_items
            if ($product->type === 'single') {
                abort_if(!$product->package_id, 422, 'Product single belum punya package_id.');
                OrderItem::create([
                    'order_id' => $order->id,
                    'package_id' => (int) $product->package_id,
                    'qty' => 1,
                ]);
            } else {
                $pkgs = $product->packages;
                abort_if($pkgs->count() === 0, 422, 'Product bundle belum punya paket.');
                foreach ($pkgs as $p) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'package_id' => (int) $p->id,
                        'qty' => (int) ($p->pivot->qty ?? 1),
                    ]);
                }
            }

            // reserve promo
            if ($promo) {
                PromoRedemption::create([
                    'promo_code_id' => (int) $promo->id,
                    'user_id' => $userId,
                    'order_id' => $order->id,
                    'status' => 'pending',
                ]);
            }

            if ($order->status === 'paid') {
                $this->grantUserPackages($order);
                if ($promo) $this->consumePromoOnPaid($order, $promo);
            }

            return $order->load(['product:id,name,type,price', 'items']);
        });
    }


    public function previewPromo(int $userId, int $productId, string $promoCode): array
    {
        $product = Product::where('is_active', true)
            ->with(['package', 'packages'])
            ->findOrFail($productId);

        $gross = (int) $product->price;

        // packageIds dari product (single/bundle)
        $packageIds = $this->extractPackageIds($product);

        // pakai rules yang sama persis dengan create order (eligible + once/user + kuota pending+used)
        [$final, $discount, $promo] = $this->applyPromoIfAny(
            userId: $userId,
            code: $promoCode,
            gross: $gross,
            productId: (int) $product->id,
            packageIds: $packageIds
        );

        return [
            'promo' => [
                'id' => (int) $promo->id,
                'code' => $promo->code,
                'type' => $promo->type,
                'value' => (int) $promo->value,
            ],
            'product' => [
                'id' => (int) $product->id,
                'name' => $product->name,
                'type' => $product->type,
                'price' => $gross,
            ],
            'gross' => $gross,
            'discount' => $discount,
            'final_amount' => $final,
        ];
    }

    public function markPaid(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order = Order::lockForUpdate()->findOrFail($order->id);
            if ($order->status === 'paid') return $order->load(['product', 'items']);

            $order->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            $this->grantUserPackages($order);

            if ($order->promo_code) {
                $promo = PromoCode::where('code', strtoupper(trim($order->promo_code)))->first();
                if ($promo) {
                    $this->consumePromoOnPaid($order, $promo);
                }
            }

            return $order->fresh()->load(['product:id,name,type,price', 'items']);
        });
    }

    public function markExpired(Order $order, ?array $reasonPayload = null): Order
    {
        return DB::transaction(function () use ($order, $reasonPayload) {
            $order = Order::lockForUpdate()->findOrFail($order->id);

            if ($order->status !== 'pending') {
                return $order->load(['product:id,name,type,price', 'items']);
            }

            $order->update([
                'status' => 'expired',
                'raw_callback' => $reasonPayload ?: $order->raw_callback,
            ]);

            $this->voidPromoIfAny($order);

            return $order->fresh()->load(['product:id,name,type,price', 'items']);
        });
    }

    public function shouldExpire(Order $order): bool
    {
        return $order->status === 'pending'
            && $order->expires_at
            && now()->greaterThan($order->expires_at);
    }

    /**
     * OPTIONAL: panggil ini kalau kamu punya flow mark expired/failed,
     * supaya slot promo pending dibalikin (void).
     */
    public function voidPromoIfAny(Order $order): void
    {
        if (!$order->promo_code) return;

        $red = PromoRedemption::where('order_id', $order->id)->lockForUpdate()->first();
        if ($red && $red->status === 'pending') {
            $red->status = 'void';
            $red->save();
        }
    }

    private function grantUserPackages(Order $order): void
    {
        $now = now();

        $order->loadMissing(['product', 'items']);

        $accessDays = (int) ($order->product?->access_days ?? 0);

        foreach ($order->items as $it) {
            $packageId = (int) $it->package_id;

            $endsAt = $accessDays
                ? $now->copy()->addDays((int) $accessDays)
                : null;

            UserPackage::updateOrCreate(
                [
                    'user_id' => $order->user_id,
                    'package_id' => $packageId,
                    'order_id' => $order->id,
                ],
                [
                    'starts_at' => $now,
                    'ends_at' => $endsAt,
                ]
            );
        }
    }

    /**
     * Apply promo:
     * - validasi aktif & tanggal & min_purchase
     * - PRIORITAS: kalau promo punya mapping products => wajib match product
     * - kalau tidak punya mapping products => fallback mapping packages
     * - validasi sekali per user
     * - reserve kuota: used_count + pending < max_uses
     *
     * Return: [finalAmount, discount, promo|null]
     */
    private function applyPromoIfAny(
        int $userId,
        ?string $code,
        int $gross,
        int $productId,
        array $packageIds
    ): array {
        if (!$code) return [$gross, 0, null];

        $code = strtoupper(trim($code));

        /** @var \App\Models\PromoCode|null $promo */
        $promo = PromoCode::where('code', $code)->first();

        // promo wajib valid
        if (!$promo || !$promo->is_active) {
            throw ValidationException::withMessages([
                'promo_code' => ['Kode promo tidak valid.'],
            ]);
        }

        // window waktu + min purchase
        $now = now();

        if ($promo->starts_at && $now->lt($promo->starts_at)) {
            throw ValidationException::withMessages([
                'promo_code' => ['Promo belum mulai.'],
            ]);
        }

        if ($promo->ends_at && $now->gt($promo->ends_at)) {
            throw ValidationException::withMessages([
                'promo_code' => ['Promo sudah berakhir.'],
            ]);
        }

        if (($promo->min_purchase ?? 0) > 0 && $gross < (int) $promo->min_purchase) {
            throw ValidationException::withMessages([
                'promo_code' => ['Minimal pembelian belum terpenuhi.'],
            ]);
        }

        // harus ada assignment minimal ke product/package
        $productRuleExists = DB::table('promo_code_products')
            ->where('promo_code_id', $promo->id)
            ->exists();

        $packageRuleExists = DB::table('promo_code_packages')
            ->where('promo_code_id', $promo->id)
            ->exists();

        if (!$productRuleExists && !$packageRuleExists) {
            throw ValidationException::withMessages([
                'promo_code' => ['Promo belum di-assign ke product atau package manapun.'],
            ]);
        }

        // eligible kalau match salah satu:
        // 1) match product_id
        // 2) match salah satu package_id dari product (single/bundle)
        $eligible = false;

        if ($productRuleExists) {
            $eligible = DB::table('promo_code_products')
                ->where('promo_code_id', $promo->id)
                ->where('product_id', $productId)
                ->exists();
        }

        if (!$eligible && $packageRuleExists) {
            $eligible = DB::table('promo_code_packages')
                ->where('promo_code_id', $promo->id)
                ->whereIn('package_id', $packageIds)
                ->exists();
        }

        if (!$eligible) {
            throw ValidationException::withMessages([
                'promo_code' => ['Promo tidak berlaku untuk produk/paket ini.'],
            ]);
        }

        // sekali per user (pending/used = sudah pernah pakai / sedang proses)
        $already = DB::table('promo_redemptions')
            ->where('promo_code_id', $promo->id)
            ->where('user_id', $userId)
            ->whereIn('status', ['pending', 'used'])
            ->exists();

        if ($already) {
            throw ValidationException::withMessages([
                'promo_code' => ['Promo ini sudah pernah kamu gunakan.'],
            ]);
        }

        // reserve kuota: used_count + pending < max_uses
        if (!is_null($promo->max_uses)) {
            $pending = DB::table('promo_redemptions')
                ->where('promo_code_id', $promo->id)
                ->where('status', 'pending')
                ->count();

            if (((int) $promo->used_count + (int) $pending) >= (int) $promo->max_uses) {
                throw ValidationException::withMessages([
                    'promo_code' => ['Kuota promo sudah habis.'],
                ]);
            }
        }

        // hitung diskon
        $discount = $promo->type === 'percent'
            ? (int) floor($gross * ((int) $promo->value / 100))
            : (int) $promo->value;

        $discount = min($discount, $gross);

        return [max(0, $gross - $discount), $discount, $promo];
    }


    /**
     * Finalize promo when order becomes PAID:
     * - lock promo row
     * - lock redemption row by order_id
     * - set redemption used
     * - increment used_count
     */
    private function consumePromoOnPaid(Order $order, PromoCode $promo): void
    {
        $promo = PromoCode::where('id', $promo->id)->lockForUpdate()->first();

        $red = PromoRedemption::where('order_id', $order->id)->lockForUpdate()->first();
        if (!$red) return;

        if ($red->status !== 'pending') return;

        if (!is_null($promo->max_uses) && $promo->used_count >= $promo->max_uses) {
            // safety
            $red->status = 'void';
            $red->save();
            return;
        }

        $red->status = 'used';
        $red->save();

        $promo->increment('used_count');
    }

    private function genMerchantOrderId(): string
    {
        return 'ORD-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(6));
    }

    private function extractPackageIds(Product $product): array
    {
        if ($product->type === 'single') {
            abort_if(!$product->package_id, 422, 'Product single belum punya package_id.');
            return [(int) $product->package_id];
        }

        $pkgs = $product->packages;
        abort_if($pkgs->count() === 0, 422, 'Product bundle belum punya paket.');

        return $pkgs->pluck('id')->map(fn($v) => (int) $v)->all();
    }
}

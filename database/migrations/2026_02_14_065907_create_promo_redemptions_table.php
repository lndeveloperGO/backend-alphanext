<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('promo_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promo_code_id')->constrained('promo_codes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();

            // pending = dipakai di order tapi belum paid
            // used    = order sudah paid, kuota kepakai
            // void    = order gagal/expired/cancel, tidak dihitung
            $table->enum('status', ['pending','used','void'])->default('pending');

            $table->timestamps();

            // sekali per user untuk tiap promo
            $table->unique(['promo_code_id', 'user_id']);

            // satu order cuma boleh punya 1 redemption
            $table->unique('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_redemptions');
    }
};

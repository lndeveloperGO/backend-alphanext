<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('midtrans_settings', function (Blueprint $table) {
            $table->id();
            $table->string('server_key')->nullable();
            $table->string('client_key')->nullable();
            $table->boolean('is_production')->default(false);
            $table->string('merchant_name')->nullable();
            $table->integer('expiry_duration')->default(15);
            $table->enum('expiry_unit', ['minutes', 'hours', 'days'])->default('minutes');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('midtrans_settings');
    }
};

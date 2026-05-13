<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->foreignId('cashier_id')->nullable()->constrained('users');
            $table->string('order_type', 10);                           // preorder | walkin
            $table->string('status', 15)->default('pending');           // pending | processing | ready | completed | cancelled
            $table->string('payment_method', 20);                       // cash | qris | virtual_account
            $table->string('payment_status', 10)->default('unpaid');    // unpaid | paid | refunded
            $table->string('payment_channel', 10)->nullable();          // bca | bni | bri | mandiri
            $table->integer('queue_number')->nullable();
            $table->timestamp('pickup_time')->nullable();
            $table->string('promo_code', 50)->nullable();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->string('midtrans_order_id', 100)->nullable()->unique();
            $table->string('qr_code_url', 500)->nullable();
            $table->string('va_number', 50)->nullable();
            $table->timestamp('payment_expired_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index('payment_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

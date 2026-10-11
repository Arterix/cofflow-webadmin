<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\StockService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Expire unpaid QRIS / Virtual Account orders that have passed payment_expired_at.
 *
 * Restores deducted stock so ingredients become available again.
 * Safe to run frequently (every minute) — idempotent via status check.
 *
 * Schedule in bootstrap/app.php:
 *   ->withSchedule(fn ($s) => $s->command('orders:expire-unpaid')->everyMinute())
 */
class ExpireUnpaidOrders extends Command
{
    protected $signature   = 'orders:expire-unpaid';
    protected $description = 'Cancel QRIS/VA orders that have passed their payment deadline and restore stock.';

    public function handle(StockService $stockService): int
    {
        $orders = Order::with('items')
            ->whereIn('payment_method', ['qris', 'virtual_account'])
            ->where('payment_status', 'unpaid')
            ->where('status', 'pending')
            ->where('payment_expired_at', '<', now())
            ->get();

        if ($orders->isEmpty()) {
            $this->line('No expired orders found.');
            return self::SUCCESS;
        }

        $cancelled = 0;
        $failed    = 0;

        foreach ($orders as $order) {
            try {
                DB::transaction(function () use ($order, $stockService) {
                    // Guard: skip if already processed by another worker
                    $fresh = Order::where('id', $order->id)
                        ->where('status', 'pending')
                        ->where('payment_status', 'unpaid')
                        ->lockForUpdate()
                        ->first();

                    if (! $fresh) {
                        return; // Already cancelled by webhook or another process
                    }

                    $stockService->restoreForOrder($fresh->load('items.condiments'));

                    $fresh->update([
                        'status'         => 'cancelled',
                        'payment_status' => 'unpaid',
                    ]);
                });

                $this->line("Cancelled order #{$order->id} (expired at {$order->payment_expired_at})");
                $cancelled++;
            } catch (\Throwable $e) {
                Log::error("ExpireUnpaidOrders: failed to cancel order #{$order->id}", [
                    'error' => $e->getMessage(),
                ]);
                $this->error("Failed order #{$order->id}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info("Done. Cancelled: {$cancelled}, Failed: {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}

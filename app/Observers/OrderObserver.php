<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\FcmService;
use App\Services\StockService;
use Illuminate\Support\Facades\Log;
use Throwable;

class OrderObserver
{
    public function __construct(
        protected StockService $stock,
        protected FcmService $fcm,
    ) {}

    public function created(Order $order): void
    {
        // Stock deduction is performed explicitly by OrderController after the
        // order_items rows are inserted (the items relation is empty here).
        try {
            $this->fcm->sendNewOrderAlert($order);
        } catch (Throwable $e) {
            Log::warning('OrderObserver::created failed', ['error' => $e->getMessage()]);
        }
    }

    public function updated(Order $order): void
    {
        try {
            if ($order->wasChanged('status')) {
                if ($order->status === 'cancelled') {
                    $order->loadMissing('items');
                    $this->stock->restoreForOrder($order);
                }

                $this->fcm->sendOrderStatusUpdate($order);
            }

            if ($order->wasChanged('payment_status') && $order->payment_status === 'paid') {
                $this->fcm->sendPaymentConfirmed($order);
            }
        } catch (Throwable $e) {
            Log::warning('OrderObserver::updated failed', ['error' => $e->getMessage()]);
        }
    }
}

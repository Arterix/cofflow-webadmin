<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\FcmService;
use App\Services\LoyaltyService;
use App\Services\StockService;
use Illuminate\Support\Facades\Log;
use Throwable;

class OrderObserver
{
    public function __construct(
        protected StockService $stock,
        protected FcmService $fcm,
        protected LoyaltyService $loyalty,
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
        if ($order->wasChanged('status') && $order->status === 'cancelled') {
            $order->loadMissing('items.condiments.condimentOption.bomItems');
            $this->stock->restoreForOrder($order);
            $this->loyalty->releaseVoucherForOrder($order);
        }

        if ($order->wasChanged('status') && $order->status === 'completed') {
            try {
                $this->loyalty->awardForOrder($order);
            } catch (Throwable $e) {
                Log::error('Awarding loyalty points failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }
        }

        try {
            if ($order->wasChanged('status')) {
                $this->fcm->sendOrderStatusUpdate($order);
            }

            if ($order->wasChanged('payment_status') && $order->payment_status === 'paid') {
                $this->fcm->sendPaymentConfirmed($order);
            }
        } catch (Throwable $e) {
            Log::warning('OrderObserver notification failed', ['error' => $e->getMessage()]);
        }
    }
}

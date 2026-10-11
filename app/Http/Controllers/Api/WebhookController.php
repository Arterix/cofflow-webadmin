<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\MidtransService;
use App\Services\StockService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WebhookController extends Controller
{
    use ApiResponse;

    public function midtrans(Request $request, MidtransService $midtrans, StockService $stockService): JsonResponse
    {
        $payload = $request->all();

        if (! $midtrans->verifyWebhookSignature($payload)) {
            return $this->error('Invalid signature', 403);
        }

        $midtransOrderId = $payload['order_id'] ?? null;
        $transactionStatus = $payload['transaction_status'] ?? null;

        if (! $midtransOrderId) {
            return $this->success(null, 'No order_id');
        }

        $order = Order::where('midtrans_order_id', $midtransOrderId)->first();
        if (! $order) {
            return $this->success(null, 'Order not found');
        }

        // Idempotency: skip if already in a terminal state
        if (in_array($order->payment_status, ['paid', 'refunded'], true)) {
            return $this->success(null, 'Already in terminal state');
        }

        if (in_array($transactionStatus, ['settlement', 'capture'], true)) {
            // Payment confirmed — order remains as-is (stock already deducted at creation)
            $order->update(['payment_status' => 'paid']);

        } elseif (in_array($transactionStatus, ['cancel', 'expire', 'deny'], true)) {
            // Payment failed/expired — cancel the order and restore stock atomically
            DB::transaction(function () use ($order, $stockService) {
                // Only restore stock if the order hasn't been cancelled yet
                // (avoid double-restore if webhook fires twice)
                if ($order->status !== 'cancelled') {
                    $stockService->restoreForOrder($order->load('items.condiments'));
                    $order->update([
                        'status' => 'cancelled',
                        'payment_status' => 'unpaid',
                    ]);
                }
            });

        } elseif ($transactionStatus === 'refund') {
            $order->update(['payment_status' => 'refunded']);
        }

        return $this->success(null, 'Webhook processed');
    }
}

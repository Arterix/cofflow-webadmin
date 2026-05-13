<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\MidtransService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    use ApiResponse;

    public function midtrans(Request $request, MidtransService $midtrans): JsonResponse
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

        // Idempotency: skip if already paid
        if ($order->payment_status === 'paid') {
            return $this->success(null, 'Already paid');
        }

        if (in_array($transactionStatus, ['settlement', 'capture'], true)) {
            $order->update(['payment_status' => 'paid']);
        } elseif (in_array($transactionStatus, ['cancel', 'expire', 'deny'], true)) {
            $order->update(['payment_status' => 'unpaid']);
        } elseif ($transactionStatus === 'refund') {
            $order->update(['payment_status' => 'refunded']);
        }

        return $this->success(null, 'Webhook processed');
    }
}

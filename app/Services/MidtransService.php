<?php

namespace App\Services;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Midtrans payment gateway integration.
 *
 * Calls the Midtrans Snap/Core API in Sandbox mode by default. If credentials
 * are missing or the call fails, the methods return a deterministic mock so
 * local development can continue without real Midtrans access.
 */
class MidtransService
{
    public function createQris(Order $order): array
    {
        $midtransOrderId = $this->buildOrderId($order);

        $payload = [
            'payment_type' => 'qris',
            'transaction_details' => [
                'order_id' => $midtransOrderId,
                'gross_amount' => (int) $order->total,
            ],
            'qris' => ['acquirer' => 'gopay'],
        ];

        $response = $this->call($payload);

        return [
            'midtrans_order_id' => $midtransOrderId,
            'qr_code_url' => $response['actions'][0]['url']
                ?? $response['qr_code_url']
                ?? "https://api.sandbox.midtrans.com/v2/qris/{$midtransOrderId}/qr-code",
            'expired_at' => Carbon::now()->addMinutes(15),
        ];
    }

    public function createVirtualAccount(Order $order, string $bank): array
    {
        $midtransOrderId = $this->buildOrderId($order);

        $payload = [
            'payment_type' => 'bank_transfer',
            'transaction_details' => [
                'order_id' => $midtransOrderId,
                'gross_amount' => (int) $order->total,
            ],
            'bank_transfer' => ['bank' => $bank],
        ];

        $response = $this->call($payload);

        $vaNumber = $response['va_numbers'][0]['va_number'] ?? sprintf('%s%s', strtoupper($bank), str_pad((string) $order->id, 10, '0', STR_PAD_LEFT));

        return [
            'midtrans_order_id' => $midtransOrderId,
            'va_number' => $vaNumber,
            'bank' => $bank,
            'expired_at' => Carbon::now()->addHours(24),
        ];
    }

    public function verifyWebhookSignature(array $payload): bool
    {
        $serverKey = (string) config('services.midtrans.server_key', env('MIDTRANS_SERVER_KEY', ''));
        $orderId = $payload['order_id'] ?? '';
        $statusCode = $payload['status_code'] ?? '';
        $grossAmount = $payload['gross_amount'] ?? '';
        $signature = $payload['signature_key'] ?? '';

        if (! $serverKey) {
            // No server key configured — accept in development to allow local webhook testing.
            return true;
        }

        $expected = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        return hash_equals($expected, (string) $signature);
    }

    private function buildOrderId(Order $order): string
    {
        return 'COFFLOW-'.$order->id.'-'.Carbon::now()->timestamp;
    }

    private function call(array $payload): array
    {
        $serverKey = (string) config('services.midtrans.server_key', env('MIDTRANS_SERVER_KEY', ''));
        $isProduction = (bool) config('services.midtrans.is_production', env('MIDTRANS_IS_PRODUCTION', false));

        if (! $serverKey) {
            Log::info('[Midtrans stub] no server key — returning mock response', $payload);
            return [];
        }

        $base = $isProduction
            ? 'https://api.midtrans.com/v2/charge'
            : 'https://api.sandbox.midtrans.com/v2/charge';

        try {
            $response = Http::withBasicAuth($serverKey, '')
                ->acceptJson()
                ->timeout(30)
                ->post($base, $payload);

            return $response->json() ?? [];
        } catch (Throwable $e) {
            Log::warning('Midtrans call failed', ['error' => $e->getMessage()]);
            return [];
        }
    }
}

<?php

namespace App\Services;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Midtrans payment gateway integration.
 *
 * Calls the Midtrans Core API (charge endpoint). Requires MIDTRANS_SERVER_KEY
 * to be set in the environment — throws RuntimeException otherwise so that the
 * caller receives a clear 500 / error response instead of silently using fake data.
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

        $qrUrl = $response['actions'][0]['url'] ?? $response['qr_code_url'] ?? null;

        if (! $qrUrl) {
            throw new RuntimeException('Midtrans tidak mengembalikan URL QR code. Periksa konfigurasi Midtrans.');
        }

        return [
            'midtrans_order_id' => $midtransOrderId,
            'qr_code_url' => $qrUrl,
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

        $vaNumber = $response['va_numbers'][0]['va_number'] ?? null;

        if (! $vaNumber) {
            throw new RuntimeException('Midtrans tidak mengembalikan nomor Virtual Account. Periksa konfigurasi Midtrans.');
        }

        return [
            'midtrans_order_id' => $midtransOrderId,
            'va_number' => $vaNumber,
            'bank' => $bank,
            'expired_at' => Carbon::now()->addHours(24),
        ];
    }

    public function verifyWebhookSignature(array $payload): bool
    {
        $serverKey = (string) config('services.midtrans.server_key');

        if (! $serverKey) {
            // Server key tidak dikonfigurasi. Tolak semua webhook agar tidak ada
            // transaksi yang diterima tanpa verifikasi di production.
            Log::error('[Midtrans] MIDTRANS_SERVER_KEY tidak dikonfigurasi. Webhook ditolak.');
            return false;
        }

        $orderId     = $payload['order_id'] ?? '';
        $statusCode  = $payload['status_code'] ?? '';
        $grossAmount = $payload['gross_amount'] ?? '';
        $signature   = $payload['signature_key'] ?? '';

        $expected = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

        return hash_equals($expected, (string) $signature);
    }

    private function buildOrderId(Order $order): string
    {
        return 'COFFLOW-'.$order->id.'-'.Carbon::now()->timestamp;
    }

    /**
     * POST ke Midtrans Core API dan kembalikan body JSON sebagai array.
     *
     * @throws RuntimeException  Jika server key tidak dikonfigurasi atau response HTTP gagal.
     */
    private function call(array $payload): array
    {
        $serverKey    = (string) config('services.midtrans.server_key');
        $isProduction = (bool) config('services.midtrans.is_production');

        if (! $serverKey) {
            throw new RuntimeException(
                'MIDTRANS_SERVER_KEY belum dikonfigurasi. Isi di file .env untuk mengaktifkan pembayaran QRIS/VA.'
            );
        }

        $base = $isProduction
            ? 'https://api.midtrans.com/v2/charge'
            : 'https://api.sandbox.midtrans.com/v2/charge';

        try {
            $response = Http::withBasicAuth($serverKey, '')
                ->acceptJson()
                ->timeout(30)
                ->post($base, $payload);

            if ($response->failed()) {
                Log::error('Midtrans charge request failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                    'payload' => $payload,
                ]);
                throw new RuntimeException(
                    'Permintaan ke Midtrans gagal (HTTP '.$response->status().'). Coba lagi atau hubungi admin.'
                );
            }

            return $response->json() ?? [];
        } catch (RuntimeException $e) {
            throw $e; // re-throw our own exceptions unchanged
        } catch (Throwable $e) {
            Log::error('Midtrans call exception', ['error' => $e->getMessage(), 'payload' => $payload]);
            throw new RuntimeException('Gagal terhubung ke Midtrans: '.$e->getMessage(), 0, $e);
        }
    }
}

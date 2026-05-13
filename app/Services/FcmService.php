<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Notification;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Firebase Cloud Messaging service.
 *
 * Currently a stub: notifications are persisted to the database and logged.
 * Wire up kreait/laravel-firebase here once FIREBASE_CREDENTIALS is configured.
 */
class FcmService
{
    public function sendToUser(?int $userId, string $title, string $body, array $data = [], ?string $type = null): void
    {
        if (! $userId) {
            return;
        }

        $this->persist($userId, $title, $body, $type, $data);

        $user = User::find($userId);
        if (! $user || ! $user->fcm_token) {
            return;
        }

        $this->dispatch($user->fcm_token, $title, $body, $data);
    }

    public function sendToRole(string|array $role, string $title, string $body, array $data = [], ?string $type = null): void
    {
        $roles = is_array($role) ? $role : [$role];

        $users = User::whereIn('role', $roles)
            ->where('is_active', true)
            ->get();

        foreach ($users as $user) {
            $this->persist($user->id, $title, $body, $type, $data);
            if ($user->fcm_token) {
                $this->dispatch($user->fcm_token, $title, $body, $data);
            }
        }
    }

    public function sendOrderStatusUpdate(Order $order): void
    {
        $messages = [
            'pending' => 'Pesanan #'.$order->id.' menunggu konfirmasi',
            'processing' => 'Pesanan #'.$order->id.' sedang diproses',
            'ready' => 'Pesanan #'.$order->id.' siap diambil',
            'completed' => 'Pesanan #'.$order->id.' selesai',
            'cancelled' => 'Pesanan #'.$order->id.' dibatalkan',
        ];

        $this->sendToUser(
            $order->user_id,
            'Status Pesanan Diperbarui',
            $messages[$order->status] ?? 'Status pesanan diperbarui',
            [
                'type' => 'order_status',
                'order_id' => (string) $order->id,
                'status' => $order->status,
            ],
            'order_status'
        );
    }

    public function sendNewOrderAlert(Order $order): void
    {
        $customerName = $order->user?->name ?? 'Walk-in';

        $this->sendToRole(
            ['kasir', 'admin'],
            'Pesanan Baru Masuk',
            "Order #{$order->id} dari {$customerName}",
            [
                'type' => 'new_order',
                'order_id' => (string) $order->id,
            ],
            'new_order'
        );
    }

    public function sendStockCriticalAlert(Ingredient $ingredient): void
    {
        $this->sendToRole(
            'admin',
            'Stok Hampir Habis',
            "{$ingredient->name} tersisa {$ingredient->current_stock} {$ingredient->unit}",
            [
                'type' => 'stock_critical',
                'ingredient_id' => (string) $ingredient->id,
            ],
            'stock_critical'
        );
    }

    public function sendPaymentConfirmed(Order $order): void
    {
        $this->sendToUser(
            $order->user_id,
            'Pembayaran Berhasil',
            "Pembayaran untuk pesanan #{$order->id} telah dikonfirmasi",
            [
                'type' => 'payment_confirmed',
                'order_id' => (string) $order->id,
            ],
            'payment_confirmed'
        );
    }

    private function persist(?int $userId, string $title, string $body, ?string $type, array $data): void
    {
        if (! $userId) {
            return;
        }
        try {
            Notification::create([
                'user_id' => $userId,
                'title' => $title,
                'body' => $body,
                'type' => $type,
                'data' => $data,
            ]);
        } catch (Throwable $e) {
            Log::warning('Failed to persist notification', ['error' => $e->getMessage()]);
        }
    }

    private function dispatch(string $token, string $title, string $body, array $data): void
    {
        try {
            // TODO: integrate kreait/laravel-firebase here.
            Log::info('[FCM stub] would send', [
                'token' => substr($token, 0, 12).'…',
                'title' => $title,
                'body' => $body,
                'data' => $data,
            ]);
        } catch (Throwable $e) {
            Log::warning('FCM dispatch failed', ['error' => $e->getMessage()]);
        }
    }
}

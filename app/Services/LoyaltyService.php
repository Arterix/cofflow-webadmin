<?php

namespace App\Services;

use App\Models\LoyaltySetting;
use App\Models\Order;
use App\Models\PointReward;
use App\Models\PointTransaction;
use App\Models\User;
use App\Models\UserVoucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class LoyaltyService
{
    public function __construct(protected FcmService $fcm) {}

    public function settings(): LoyaltySetting
    {
        return LoyaltySetting::current();
    }

    /**
     * Points for a priced order: each unit earns its menu's points_per_item
     * (admin override, else 1 when the normal price >= min_item_price).
     * Discounts, vouchers and promo codes don't reduce points.
     *
     * @param  array<int, array{menu:\App\Models\Menu, quantity:int}>  $breakdown
     */
    public function pointsFor(array $breakdown): int
    {
        if (! $this->settings()->is_enabled) {
            return 0;
        }

        $points = 0;
        foreach ($breakdown as $line) {
            $points += $line['menu']->points_per_item * $line['quantity'];
        }

        return $points;
    }

    public function isEligibleOrder(Order $order, ?User $user = null): bool
    {
        $user ??= $order->user;

        return $order->order_type === 'preorder' && $user !== null && $user->isCustomer();
    }

    public function summary(User $user): array
    {
        $settings = $this->settings();
        $tier = $settings->tierFor($user->lifetime_points);
        $next = match ($tier) {
            'bronze' => 'silver',
            'silver' => 'gold',
            default => null,
        };

        return [
            'enabled' => $settings->is_enabled,
            'points_balance' => $user->points_balance,
            'lifetime_points' => $user->lifetime_points,
            'tier' => $tier,
            'next_tier' => $next,
            'next_tier_at' => $next ? $settings->thresholdFor($next) : null,
            'points_to_next_tier' => $next ? max(0, $settings->thresholdFor($next) - $user->lifetime_points) : 0,
            'min_item_price' => $settings->min_item_price,
            'tiers' => collect(PointReward::TIERS)->map(fn ($t) => [
                'tier' => $t,
                'threshold' => $settings->thresholdFor($t),
            ])->all(),
        ];
    }

    /**
     * Award points when an order is completed. Idempotent via points_awarded_at.
     */
    public function awardForOrder(Order $order): void
    {
        if (! $this->settings()->is_enabled) {
            return;
        }

        $result = DB::transaction(function () use ($order) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();
            if (! $locked || $locked->points_awarded_at !== null || $locked->status !== 'completed') {
                return null;
            }

            $user = User::whereKey($locked->user_id)->lockForUpdate()->first();
            $points = (int) $locked->points_base;
            if (! $this->isEligibleOrder($locked, $user) || $points <= 0) {
                $locked->forceFill(['points_awarded_at' => now()])->saveQuietly();

                return null;
            }

            $tier = $this->settings()->tierFor($user->lifetime_points);

            $locked->forceFill(['points_earned' => $points, 'points_awarded_at' => now()])->saveQuietly();
            if ($points <= 0) {
                return null;
            }

            $user->points_balance += $points;
            $user->lifetime_points += $points;
            $user->save();

            PointTransaction::create([
                'user_id' => $user->id,
                'type' => 'earn',
                'points' => $points,
                'balance_after' => $user->points_balance,
                'description' => "Pesanan #{$locked->id} selesai",
                'order_id' => $locked->id,
            ]);

            return [$user, $points, $tier];
        });

        if (! $result) {
            return;
        }

        [$user, $points, $oldTier] = $result;
        $order->points_earned = $points;
        $newTier = $this->settings()->tierFor($user->lifetime_points);

        try {
            $this->fcm->sendToUser(
                $user->id,
                'Poin Bertambah',
                "Kamu mendapat +{$points} poin dari pesanan #{$order->id}. Saldo: {$user->points_balance} poin",
                ['type' => 'points_earned', 'order_id' => (string) $order->id, 'points' => (string) $points],
                'points_earned'
            );
            if ($newTier !== $oldTier) {
                $this->fcm->sendToUser(
                    $user->id,
                    'Naik Level Membership',
                    'Selamat! Kamu sekarang member '.ucfirst($newTier),
                    ['type' => 'tier_up', 'tier' => $newTier],
                    'tier_up'
                );
            }
        } catch (Throwable $e) {
            Log::warning('Loyalty notification failed', ['error' => $e->getMessage()]);
        }
    }

    public function redeem(User $user, PointReward $reward): UserVoucher
    {
        return DB::transaction(function () use ($user, $reward) {
            $settings = $this->settings();
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $reward = PointReward::whereKey($reward->id)->lockForUpdate()->firstOrFail();

            if (! $settings->is_enabled) {
                $this->fail('Program poin sedang tidak aktif');
            }
            if (! $user->isCustomer()) {
                $this->fail('Hanya customer yang dapat menukar poin');
            }
            if (! $reward->is_active) {
                $this->fail('Reward tidak tersedia');
            }
            if ($reward->isSoldOut()) {
                $this->fail('Kuota reward sudah habis');
            }
            $tier = $settings->tierFor($user->lifetime_points);
            if (PointReward::tierRank($tier) < PointReward::tierRank($reward->min_tier)) {
                $this->fail('Reward ini khusus member '.ucfirst($reward->min_tier).' ke atas');
            }
            if ($user->points_balance < $reward->points_cost) {
                $this->fail('Poin kamu tidak cukup');
            }

            $user->points_balance -= $reward->points_cost;
            $user->save();
            $reward->increment('redeemed_count');

            $voucher = UserVoucher::create([
                'user_id' => $user->id,
                'point_reward_id' => $reward->id,
                'code' => $this->generateCode(),
                'name' => $reward->name,
                'type' => $reward->type,
                'value' => $reward->value,
                'max_discount' => $reward->max_discount,
                'min_order' => $reward->min_order,
                'points_spent' => $reward->points_cost,
                'status' => 'available',
                'expires_at' => $settings->voucher_valid_days > 0
                    ? now()->addDays($settings->voucher_valid_days)->endOfDay()
                    : null,
            ]);

            PointTransaction::create([
                'user_id' => $user->id,
                'type' => 'redeem',
                'points' => -$reward->points_cost,
                'balance_after' => $user->points_balance,
                'description' => "Tukar reward: {$reward->name}",
                'user_voucher_id' => $voucher->id,
            ]);

            return $voucher;
        });
    }

    public function adjust(User $user, int $points, string $reason, ?User $admin = null): PointTransaction
    {
        return DB::transaction(function () use ($user, $points, $reason, $admin) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($points === 0) {
                $this->fail('Jumlah poin tidak boleh 0', 'points');
            }
            if ($user->points_balance + $points < 0) {
                $this->fail('Saldo poin tidak boleh minus (saldo: '.$user->points_balance.')', 'points');
            }

            $user->points_balance += $points;
            $user->save();

            return PointTransaction::create([
                'user_id' => $user->id,
                'type' => 'adjust',
                'points' => $points,
                'balance_after' => $user->points_balance,
                'description' => $reason,
                'created_by' => $admin?->id,
            ]);
        });
    }

    /**
     * Lock and validate a voucher for checkout. Must run inside a transaction.
     */
    public function lockVoucherForOrder(int $voucherId, User $user): UserVoucher
    {
        $voucher = UserVoucher::whereKey($voucherId)->lockForUpdate()->first();
        if (! $voucher || $voucher->user_id !== $user->id) {
            $this->fail('Voucher tidak ditemukan', 'voucher_id');
        }
        if (! $voucher->isUsable()) {
            $this->fail('Voucher sudah dipakai atau kedaluwarsa', 'voucher_id');
        }

        return $voucher;
    }

    public function findUsableVoucher(int $voucherId, User $user): UserVoucher
    {
        $voucher = UserVoucher::whereKey($voucherId)->where('user_id', $user->id)->first();
        if (! $voucher) {
            $this->fail('Voucher tidak ditemukan', 'voucher_id');
        }
        if (! $voucher->isUsable()) {
            $this->fail('Voucher sudah dipakai atau kedaluwarsa', 'voucher_id');
        }

        return $voucher;
    }

    public function markVoucherUsed(UserVoucher $voucher, Order $order): void
    {
        $voucher->update(['status' => 'used', 'used_at' => now(), 'order_id' => $order->id]);
    }

    /**
     * Give the voucher back when its order is cancelled (no-op if none / already released).
     */
    public function releaseVoucherForOrder(Order $order): void
    {
        if (! $order->user_voucher_id) {
            return;
        }

        UserVoucher::whereKey($order->user_voucher_id)
            ->where('status', 'used')
            ->where('order_id', $order->id)
            ->update([
                'status' => 'available',
                'used_at' => null,
                'order_id' => null,
            ]);
    }

    public function expireStaleVouchers(?int $userId = null): int
    {
        return UserVoucher::where('status', 'available')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->update(['status' => 'expired']);
    }

    private function generateCode(): string
    {
        do {
            $code = 'PTS-'.Str::upper(Str::random(8));
        } while (UserVoucher::where('code', $code)->exists());

        return $code;
    }

    private function fail(string $message, string $field = 'reward'): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}

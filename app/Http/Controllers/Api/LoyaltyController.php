<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PointReward;
use App\Models\UserVoucher;
use App\Services\LoyaltyService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoyaltyController extends Controller
{
    use ApiResponse;

    public function __construct(protected LoyaltyService $loyalty) {}

    public function summary(Request $request): JsonResponse
    {
        return $this->success($this->loyalty->summary($request->user()));
    }

    public function history(Request $request): JsonResponse
    {
        $items = $request->user()->pointTransactions()
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'type', 'points', 'balance_after', 'description', 'order_id', 'created_at']);

        return $this->success($items);
    }

    public function rewards(Request $request): JsonResponse
    {
        $user = $request->user();
        $tier = $user->tier ?? 'bronze';

        $rewards = PointReward::where('is_active', true)
            ->orderBy('points_cost')
            ->get()
            ->map(function (PointReward $r) use ($user, $tier) {
                $tierOk = PointReward::tierRank($tier) >= PointReward::tierRank($r->min_tier);

                return $r->only(['id', 'name', 'description', 'type', 'value', 'max_discount', 'min_order', 'points_cost', 'min_tier']) + [
                    'sold_out' => $r->isSoldOut(),
                    'tier_locked' => ! $tierOk,
                    'can_redeem' => $tierOk && ! $r->isSoldOut() && $user->points_balance >= $r->points_cost,
                ];
            });

        return $this->success($rewards);
    }

    public function redeem(Request $request, int $id): JsonResponse
    {
        $reward = PointReward::findOrFail($id);
        $voucher = $this->loyalty->redeem($request->user(), $reward);

        return $this->success($voucher, 'Reward berhasil ditukar', 201);
    }

    public function vouchers(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->loyalty->expireStaleVouchers($user->id);

        $vouchers = UserVoucher::where('user_id', $user->id)
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByRaw("case status when 'available' then 0 when 'used' then 1 else 2 end")
            ->orderByDesc('id')
            ->get();

        return $this->success($vouchers);
    }
}

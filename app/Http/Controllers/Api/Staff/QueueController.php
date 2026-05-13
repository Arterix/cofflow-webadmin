<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class QueueController extends Controller
{
    use ApiResponse;

    public function active(): JsonResponse
    {
        $orders = Order::with(['user', 'items.menu'])
            ->whereDate('created_at', Carbon::today())
            ->whereIn('status', ['pending', 'processing'])
            ->orderBy('queue_number')
            ->get();

        return $this->success($orders);
    }

    public function estimate(): JsonResponse
    {
        $activeCount = Order::whereDate('created_at', Carbon::today())
            ->whereIn('status', ['pending', 'processing'])
            ->count();

        return $this->success([
            'active_count' => $activeCount,
            'estimated_minutes' => $activeCount * 5,
        ]);
    }
}

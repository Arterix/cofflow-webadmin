<?php

namespace App\Services;

use App\Models\Order;
use Carbon\Carbon;

class QueueService
{
    /**
     * Generate the next queue number for today.
     *
     * Must be called inside a DB::transaction() — uses lockForUpdate()
     * to prevent concurrent requests from generating duplicate numbers.
     */
    public function generateQueueNumber(): int
    {
        $today = Carbon::today();

        $last = Order::whereDate('created_at', $today)
            ->lockForUpdate()
            ->max('queue_number');

        return (int) ($last ?? 0) + 1;
    }
}

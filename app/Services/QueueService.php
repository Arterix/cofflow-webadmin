<?php

namespace App\Services;

use App\Models\Order;
use Carbon\Carbon;

class QueueService
{
    public function generateQueueNumber(): int
    {
        $today = Carbon::today();

        $last = Order::whereDate('created_at', $today)->max('queue_number');

        return (int) ($last ?? 0) + 1;
    }
}

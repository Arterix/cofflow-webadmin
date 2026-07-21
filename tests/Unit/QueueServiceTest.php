<?php

namespace Tests\Unit;

use App\Services\QueueService;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueueServiceTest extends TestCase
{
    use RefreshDatabase;

    protected QueueService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new QueueService();
    }

    public function test_first_order_of_day_gets_queue_number_one(): void
    {
        $number = $this->service->generateQueueNumber();
        $this->assertEquals(1, $number);
    }

    public function test_queue_number_increments(): void
    {
        Order::factory()->create([
            'queue_number' => 5,
            'created_at' => Carbon::today(),
        ]);

        $number = $this->service->generateQueueNumber();
        $this->assertEquals(6, $number);
    }

    public function test_queue_number_resets_daily(): void
    {
        // Yesterday's order should not affect today's count
        Order::factory()->create([
            'queue_number' => 99,
            'created_at' => Carbon::yesterday(),
        ]);

        $number = $this->service->generateQueueNumber();
        $this->assertEquals(1, $number);
    }
}

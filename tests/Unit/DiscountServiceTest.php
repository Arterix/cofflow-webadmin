<?php

namespace Tests\Unit;

use App\Services\DiscountService;
use PHPUnit\Framework\TestCase;

class DiscountServiceTest extends TestCase
{
    protected DiscountService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DiscountService();
    }

    public function test_amount_from_rule_percentage(): void
    {
        $method = new \ReflectionMethod(DiscountService::class, 'amountFromRule');
        $method->setAccessible(true);

        // 20% of 50000 = 10000
        $result = $method->invoke($this->service, 'percentage', 20.0, 50000.0);
        $this->assertEquals(10000.0, $result);
    }

    public function test_amount_from_rule_percentage_full(): void
    {
        $method = new \ReflectionMethod(DiscountService::class, 'amountFromRule');
        $method->setAccessible(true);

        // 100% of 25000 = 25000
        $result = $method->invoke($this->service, 'percentage', 100.0, 25000.0);
        $this->assertEquals(25000.0, $result);
    }

    public function test_amount_from_rule_fixed_less_than_base(): void
    {
        $method = new \ReflectionMethod(DiscountService::class, 'amountFromRule');
        $method->setAccessible(true);

        // Fixed 5000 on 25000 = 5000
        $result = $method->invoke($this->service, 'fixed', 5000.0, 25000.0);
        $this->assertEquals(5000.0, $result);
    }

    public function test_amount_from_rule_fixed_exceeds_base_capped(): void
    {
        $method = new \ReflectionMethod(DiscountService::class, 'amountFromRule');
        $method->setAccessible(true);

        // Fixed 30000 on 25000 = min(30000, 25000) = 25000
        $result = $method->invoke($this->service, 'fixed', 30000.0, 25000.0);
        $this->assertEquals(25000.0, $result);
    }

    public function test_amount_from_rule_zero_percentage(): void
    {
        $method = new \ReflectionMethod(DiscountService::class, 'amountFromRule');
        $method->setAccessible(true);

        // 0% of anything = 0
        $result = $method->invoke($this->service, 'percentage', 0.0, 50000.0);
        $this->assertEquals(0.0, $result);
    }

    public function test_amount_from_rule_zero_fixed(): void
    {
        $method = new \ReflectionMethod(DiscountService::class, 'amountFromRule');
        $method->setAccessible(true);

        // Fixed 0 = 0
        $result = $method->invoke($this->service, 'fixed', 0.0, 50000.0);
        $this->assertEquals(0.0, $result);
    }
}

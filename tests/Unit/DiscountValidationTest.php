<?php

namespace Tests\Unit;

use App\Services\DiscountService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class DiscountValidationTest extends TestCase
{
    private DiscountService $service;
    private ReflectionMethod $amountFromRule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DiscountService();
        $this->amountFromRule = new ReflectionMethod(DiscountService::class, 'amountFromRule');
        $this->amountFromRule->setAccessible(true);
    }

    public function test_percentage_discount_cannot_exceed_100_percent(): void
    {
        // 150% discount on 50,000 base should be hard-capped at 100% (50,000)
        $discount = $this->amountFromRule->invoke($this->service, 'percentage', 150.0, 50000.0);
        $this->assertEquals(50000.0, $discount);
    }

    public function test_nominal_discount_cannot_exceed_base_price(): void
    {
        // 75,000 discount on 50,000 base should be capped at base price (50,000)
        $discount = $this->amountFromRule->invoke($this->service, 'nominal', 75000.0, 50000.0);
        $this->assertEquals(50000.0, $discount);
    }

    public function test_valid_discounts_calculate_correctly(): void
    {
        $pctDiscount = $this->amountFromRule->invoke($this->service, 'percentage', 20.0, 50000.0);
        $this->assertEquals(10000.0, $pctDiscount);

        $nomDiscount = $this->amountFromRule->invoke($this->service, 'nominal', 15000.0, 50000.0);
        $this->assertEquals(15000.0, $nomDiscount);
    }
}

<?php

namespace BuygoLineNotify\Tests\Unit\Services;

use BuygoLineNotify\Services\SampleService;
use PHPUnit\Framework\TestCase;

final class SampleServiceTest extends TestCase
{
    private SampleService $service;

    protected function setUp(): void
    {
        $this->service = new SampleService();
    }

    public function testCalculatePriceBasic(): void
    {
        $items = [
            ['price' => 100, 'quantity' => 1],
            ['price' => 50, 'quantity' => 2],
        ];

        $this->assertEquals(200.0, $this->service->calculatePrice($items));
    }

    public function testDiscountValidation(): void
    {
        $this->assertTrue($this->service->isValidDiscount(0.1));
        $this->assertFalse($this->service->isValidDiscount(1.5));
        $this->assertFalse($this->service->isValidDiscount(-0.1));
    }
}


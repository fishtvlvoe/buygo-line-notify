<?php

namespace BuygoLineNotify\Tests\Unit\Facade;

use BuygoLineNotify\BuygoLineNotify;
use BuygoLineNotify\Services\ImageUploader;
use BuygoLineNotify\Services\LineMessagingService;
use BuygoLineNotify\Services\SettingsService;
use BuygoLineNotify\Services\Logger;
use PHPUnit\Framework\TestCase;

final class BuygoLineNotifyTest extends TestCase
{
    public function testImageUploaderReturnsInstance(): void
    {
        $uploader = BuygoLineNotify::image_uploader('test_token');
        $this->assertInstanceOf(ImageUploader::class, $uploader);
    }

    public function testMessagingReturnsInstance(): void
    {
        $messaging = BuygoLineNotify::messaging('test_token');
        $this->assertInstanceOf(LineMessagingService::class, $messaging);
    }

    public function testSettingsReturnsClass(): void
    {
        $settings = BuygoLineNotify::settings();
        $this->assertEquals(SettingsService::class, $settings);
    }

    public function testLoggerReturnsInstance(): void
    {
        $logger = BuygoLineNotify::logger();
        $this->assertInstanceOf(Logger::class, $logger);
    }

    public function testIsActiveReturnsBool(): void
    {
        $isActive = BuygoLineNotify::is_active();
        $this->assertIsBool($isActive);
    }
}

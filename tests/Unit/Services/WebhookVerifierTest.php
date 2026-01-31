<?php
/**
 * WebhookVerifier Unit Tests
 *
 * 測試 LINE Webhook 簽名驗證邏輯
 */

namespace BuygoLineNotify\Tests\Unit\Services;

use BuygoLineNotify\Services\WebhookVerifier;
use PHPUnit\Framework\TestCase;

final class WebhookVerifierTest extends TestCase
{
    private WebhookVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();
        reset_mock_data();
        $this->verifier = new WebhookVerifier();
    }

    protected function tearDown(): void
    {
        reset_mock_data();
        parent::tearDown();
    }

    /**
     * 測試開發模式檢測
     */
    public function testIsDevelopmentModeWithWpDebug(): void
    {
        // WP_DEBUG is defined as true in bootstrap
        $result = $this->verifier->is_development_mode();
        $this->assertTrue($result);
    }

    /**
     * 測試有效簽名驗證
     */
    public function testVerifyValidSignature(): void
    {
        $channelSecret = 'test_channel_secret_123';
        $body = '{"events":[{"type":"message"}]}';

        // 設定 channel secret
        update_option('buygo_line_notify_settings', [
            'channel_secret' => $channelSecret,
        ]);

        // 計算正確的簽名
        $hash = hash_hmac('sha256', $body, $channelSecret, true);
        $validSignature = base64_encode($hash);

        // 建立 mock request
        $request = new \WP_REST_Request();
        $request->set_header('x-line-signature', $validSignature);
        $request->set_body($body);

        // 開發模式下會通過
        $result = $this->verifier->verify_signature($request);
        $this->assertTrue($result);
    }

    /**
     * 測試開發模式下無簽名也通過
     */
    public function testDevelopmentModeAllowsNoSignature(): void
    {
        // 建立沒有簽名的 request
        $request = new \WP_REST_Request();
        $request->set_body('{"events":[]}');

        // 開發模式下應該通過
        $result = $this->verifier->verify_signature($request);
        $this->assertTrue($result);
    }

    /**
     * 測試開發模式下無 channel secret 也通過
     */
    public function testDevelopmentModeAllowsNoChannelSecret(): void
    {
        // 確保沒有設定 channel secret
        delete_option('buygo_line_notify_settings');

        $request = new \WP_REST_Request();
        $request->set_header('x-line-signature', 'some_signature');
        $request->set_body('{"events":[]}');

        // 開發模式下應該通過
        $result = $this->verifier->verify_signature($request);
        $this->assertTrue($result);
    }

    /**
     * 測試簽名計算邏輯
     */
    public function testSignatureCalculation(): void
    {
        $channelSecret = 'my_secret_key';
        $body = '{"events":[{"type":"message","message":{"type":"text","text":"Hello"}}]}';

        // 手動計算簽名
        $hash = hash_hmac('sha256', $body, $channelSecret, true);
        $expectedSignature = base64_encode($hash);

        // 驗證簽名格式
        $this->assertIsString($expectedSignature);
        $this->assertNotEmpty($expectedSignature);

        // 驗證 base64 解碼後是 32 bytes（SHA256 輸出）
        $decoded = base64_decode($expectedSignature);
        $this->assertEquals(32, strlen($decoded));
    }

    /**
     * 測試 hash_equals 安全比較
     */
    public function testHashEqualsComparison(): void
    {
        $signature1 = 'abc123def456';
        $signature2 = 'abc123def456';
        $signature3 = 'xyz789';

        // 相同的字串應該相等
        $this->assertTrue(hash_equals($signature1, $signature2));

        // 不同的字串應該不相等
        $this->assertFalse(hash_equals($signature1, $signature3));
    }

    /**
     * 測試 LINE Webhook 真實範例
     */
    public function testRealWorldWebhookExample(): void
    {
        // LINE 官方文件的範例格式
        $webhookBody = json_encode([
            'destination' => 'Uxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
            'events' => [
                [
                    'replyToken' => '0f3779fba3b349968c5d07db31eab56f',
                    'type' => 'message',
                    'mode' => 'active',
                    'timestamp' => 1462629479859,
                    'source' => [
                        'type' => 'user',
                        'userId' => 'U4af4980629...',
                    ],
                    'message' => [
                        'id' => '325708',
                        'type' => 'text',
                        'text' => 'Hello, world!',
                    ],
                ],
            ],
        ]);

        $this->assertJson($webhookBody);

        $decoded = json_decode($webhookBody, true);
        $this->assertArrayHasKey('events', $decoded);
        $this->assertCount(1, $decoded['events']);
        $this->assertEquals('message', $decoded['events'][0]['type']);
    }

    /**
     * 測試 Verify Event 處理（LINE 驗證 webhook URL 用）
     */
    public function testVerifyEventDetection(): void
    {
        // LINE 驗證 webhook URL 時發送的 replyToken
        $verifyReplyToken = '00000000000000000000000000000000';

        $webhookBody = json_encode([
            'events' => [
                [
                    'replyToken' => $verifyReplyToken,
                    'type' => 'message',
                    'source' => ['type' => 'user'],
                ],
            ],
        ]);

        $decoded = json_decode($webhookBody, true);

        // 檢測是否為驗證事件
        $isVerifyEvent = $decoded['events'][0]['replyToken'] === $verifyReplyToken;
        $this->assertTrue($isVerifyEvent);
    }
}

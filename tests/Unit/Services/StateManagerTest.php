<?php
/**
 * StateManager Unit Tests
 *
 * 測試 OAuth state 參數的生成、儲存、驗證和消費
 */

namespace BuygoLineNotify\Tests\Unit\Services;

use BuygoLineNotify\Services\StateManager;
use PHPUnit\Framework\TestCase;

final class StateManagerTest extends TestCase
{
    private StateManager $stateManager;

    protected function setUp(): void
    {
        parent::setUp();
        reset_mock_data();
        $this->stateManager = new StateManager();
    }

    protected function tearDown(): void
    {
        reset_mock_data();
        parent::tearDown();
    }

    /**
     * 測試 generate_state 產生 32 字元的十六進位字串
     */
    public function testGenerateStateReturns32CharHexString(): void
    {
        $state = $this->stateManager->generate_state();

        $this->assertIsString($state);
        $this->assertEquals(32, strlen($state));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $state);
    }

    /**
     * 測試每次生成的 state 都是唯一的
     */
    public function testGenerateStateReturnsUniqueValues(): void
    {
        $states = [];
        for ($i = 0; $i < 100; $i++) {
            $states[] = $this->stateManager->generate_state();
        }

        $uniqueStates = array_unique($states);
        $this->assertCount(100, $uniqueStates, 'All generated states should be unique');
    }

    /**
     * 測試 store_state 成功儲存資料
     */
    public function testStoreStateSuccessfully(): void
    {
        $state = 'test_state_12345678901234567890';
        $data = [
            'redirect_url' => 'https://example.com/callback',
            'user_id' => 123,
        ];

        $result = $this->stateManager->store_state($state, $data);

        $this->assertTrue($result);
    }

    /**
     * 測試 store_state 自動加入時間戳記
     */
    public function testStoreStateAddsCreatedAtTimestamp(): void
    {
        $state = $this->stateManager->generate_state();
        $data = ['redirect_url' => 'https://example.com'];

        $beforeTime = time();
        $this->stateManager->store_state($state, $data);
        $afterTime = time();

        $storedData = $this->stateManager->verify_state($state);

        $this->assertIsArray($storedData);
        $this->assertArrayHasKey('created_at', $storedData);
        $this->assertGreaterThanOrEqual($beforeTime, $storedData['created_at']);
        $this->assertLessThanOrEqual($afterTime, $storedData['created_at']);
    }

    /**
     * 測試 verify_state 成功驗證有效的 state
     */
    public function testVerifyStateSuccessfully(): void
    {
        $state = $this->stateManager->generate_state();
        $data = [
            'redirect_url' => 'https://example.com/callback',
            'user_id' => 456,
        ];

        $this->stateManager->store_state($state, $data);
        $result = $this->stateManager->verify_state($state);

        $this->assertIsArray($result);
        $this->assertEquals('https://example.com/callback', $result['redirect_url']);
        $this->assertEquals(456, $result['user_id']);
    }

    /**
     * 測試 verify_state 對不存在的 state 返回 false
     */
    public function testVerifyStateReturnsFalseForNonexistentState(): void
    {
        $result = $this->stateManager->verify_state('nonexistent_state_123');

        $this->assertFalse($result);
    }

    /**
     * 測試 consume_state 刪除 state
     */
    public function testConsumeStateRemovesState(): void
    {
        $state = $this->stateManager->generate_state();
        $data = ['redirect_url' => 'https://example.com'];

        $this->stateManager->store_state($state, $data);

        // 確認 state 存在
        $this->assertIsArray($this->stateManager->verify_state($state));

        // 消費 state
        $this->stateManager->consume_state($state);

        // 確認 state 已被刪除
        $this->assertFalse($this->stateManager->verify_state($state));
    }

    /**
     * 測試 state 只能使用一次（防重放攻擊）
     */
    public function testStateCanOnlyBeUsedOnce(): void
    {
        $state = $this->stateManager->generate_state();
        $data = ['redirect_url' => 'https://example.com'];

        $this->stateManager->store_state($state, $data);

        // 第一次驗證成功
        $firstResult = $this->stateManager->verify_state($state);
        $this->assertIsArray($firstResult);

        // 消費 state
        $this->stateManager->consume_state($state);

        // 第二次驗證失敗
        $secondResult = $this->stateManager->verify_state($state);
        $this->assertFalse($secondResult);
    }

    /**
     * 測試 TRANSIENT_PREFIX 常數
     */
    public function testTransientPrefixConstant(): void
    {
        $this->assertEquals('buygo_line_state_', StateManager::TRANSIENT_PREFIX);
    }

    /**
     * 測試 STATE_EXPIRY 常數（10 分鐘）
     */
    public function testStateExpiryConstant(): void
    {
        $this->assertEquals(600, StateManager::STATE_EXPIRY);
    }

    /**
     * 測試完整的 OAuth 流程模擬
     */
    public function testCompleteOAuthFlowSimulation(): void
    {
        // Step 1: 產生 state
        $state = $this->stateManager->generate_state();
        $this->assertNotEmpty($state);

        // Step 2: 儲存 state 和相關資料
        $storeData = [
            'redirect_url' => 'https://mysite.com/my-account',
            'action' => 'login',
        ];
        $storeResult = $this->stateManager->store_state($state, $storeData);
        $this->assertTrue($storeResult);

        // Step 3: 模擬 OAuth callback，驗證 state
        $verifyResult = $this->stateManager->verify_state($state);
        $this->assertIsArray($verifyResult);
        $this->assertEquals('https://mysite.com/my-account', $verifyResult['redirect_url']);
        $this->assertEquals('login', $verifyResult['action']);

        // Step 4: 消費 state（防止重放）
        $this->stateManager->consume_state($state);

        // Step 5: 確認無法再次使用
        $replayResult = $this->stateManager->verify_state($state);
        $this->assertFalse($replayResult);
    }

    /**
     * 測試儲存複雜資料結構
     */
    public function testStoreComplexDataStructure(): void
    {
        $state = $this->stateManager->generate_state();
        $complexData = [
            'redirect_url' => 'https://example.com/callback',
            'user_id' => null,
            'action' => 'bind',
            'line_profile' => [
                'userId' => 'U1234567890abcdef',
                'displayName' => '測試用戶',
                'pictureUrl' => 'https://profile.line-scdn.net/xxx',
                'email' => 'test@example.com',
            ],
            'metadata' => [
                'ip' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0',
            ],
        ];

        $this->stateManager->store_state($state, $complexData);
        $result = $this->stateManager->verify_state($state);

        $this->assertIsArray($result);
        $this->assertEquals('https://example.com/callback', $result['redirect_url']);
        $this->assertNull($result['user_id']);
        $this->assertEquals('bind', $result['action']);
        $this->assertIsArray($result['line_profile']);
        $this->assertEquals('U1234567890abcdef', $result['line_profile']['userId']);
        $this->assertEquals('測試用戶', $result['line_profile']['displayName']);
    }
}

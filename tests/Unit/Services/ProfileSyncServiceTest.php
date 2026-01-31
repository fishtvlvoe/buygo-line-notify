<?php
/**
 * ProfileSyncService Unit Tests
 *
 * 測試 LINE profile 同步邏輯和衝突處理策略
 */

namespace BuygoLineNotify\Tests\Unit\Services;

use BuygoLineNotify\Services\ProfileSyncService;
use PHPUnit\Framework\TestCase;

final class ProfileSyncServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        reset_mock_data();
    }

    protected function tearDown(): void
    {
        reset_mock_data();
        parent::tearDown();
    }

    // ========================================================================
    // syncProfile Basic Tests
    // ========================================================================

    /**
     * 測試用戶不存在時返回錯誤
     */
    public function testSyncProfileReturnsErrorWhenUserNotFound(): void
    {
        $result = ProfileSyncService::syncProfile(999, [
            'displayName' => 'Test User',
        ], 'login');

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertEquals('user_not_found', $result->get_error_code());
    }

    /**
     * 測試成功同步 display_name（register 動作）
     */
    public function testSyncProfileUpdatesDisplayNameOnRegister(): void
    {
        global $mock_users;
        add_mock_user(100, 'testuser', 'test@example.com', 'Old Name');

        // 設定 LINE 優先策略
        update_option('buygo_line_conflict_strategy', 'line_priority');

        $result = ProfileSyncService::syncProfile(100, [
            'displayName' => 'New LINE Name',
        ], 'register');

        $this->assertTrue($result);
        $this->assertEquals('New LINE Name', $mock_users[100]->display_name);
    }

    /**
     * 測試 register 動作強制同步
     */
    public function testRegisterActionForcesSyncRegardlessOfStrategy(): void
    {
        global $mock_users;
        add_mock_user(101, 'testuser2', 'test2@example.com', 'Existing Name');

        // 即使設定 WordPress 優先，register 仍然強制同步
        update_option('buygo_line_conflict_strategy', 'wordpress_priority');

        $result = ProfileSyncService::syncProfile(101, [
            'displayName' => 'LINE Name Wins',
        ], 'register');

        $this->assertTrue($result);
        $this->assertEquals('LINE Name Wins', $mock_users[101]->display_name);
    }

    // ========================================================================
    // Conflict Strategy Tests
    // ========================================================================

    /**
     * 測試 LINE 優先策略覆蓋現有值
     */
    public function testLinePriorityStrategyOverwritesExistingValue(): void
    {
        global $mock_users;
        add_mock_user(200, 'linkuser', 'link@example.com', 'WordPress Name');

        update_option('buygo_line_conflict_strategy', 'line_priority');

        $result = ProfileSyncService::syncProfile(200, [
            'displayName' => 'LINE Name',
        ], 'link');

        $this->assertTrue($result);
        $this->assertEquals('LINE Name', $mock_users[200]->display_name);
    }

    /**
     * 測試 WordPress 優先策略保留現有值
     */
    public function testWordPressPriorityStrategyKeepsExistingValue(): void
    {
        global $mock_users;
        add_mock_user(201, 'wpuser', 'wp@example.com', 'WordPress Name');

        // SettingsService 使用 buygo_line_{key} 格式
        update_option('buygo_line_conflict_strategy', 'wordpress_priority');

        $result = ProfileSyncService::syncProfile(201, [
            'displayName' => 'LINE Name Should Not Replace',
        ], 'link');

        $this->assertTrue($result);
        $this->assertEquals('WordPress Name', $mock_users[201]->display_name);
    }

    /**
     * 測試空值時無論策略都會更新
     */
    public function testEmptyValueAlwaysGetsUpdated(): void
    {
        global $mock_users;
        add_mock_user(202, 'emptyuser', 'empty@example.com', '');

        update_option('buygo_line_conflict_strategy', 'wordpress_priority');

        $result = ProfileSyncService::syncProfile(202, [
            'displayName' => 'Fill Empty Name',
        ], 'link');

        $this->assertTrue($result);
        $this->assertEquals('Fill Empty Name', $mock_users[202]->display_name);
    }

    /**
     * 測試相同值不會觸發更新
     */
    public function testSameValueDoesNotTriggerUpdate(): void
    {
        global $mock_users;
        add_mock_user(203, 'sameuser', 'same@example.com', 'Same Name');

        update_option('buygo_line_conflict_strategy', 'line_priority');

        // 傳入相同的 displayName
        $result = ProfileSyncService::syncProfile(203, [
            'displayName' => 'Same Name',
        ], 'link');

        $this->assertTrue($result);
        // 沒有同步日誌（因為值沒變）
        $logs = ProfileSyncService::getSyncLog(203);
        $this->assertEmpty($logs);
    }

    // ========================================================================
    // Login Action Tests
    // ========================================================================

    /**
     * 測試登入時同步被停用時不更新
     */
    public function testLoginActionRespectsDisabledSyncOnLogin(): void
    {
        global $mock_users;
        add_mock_user(300, 'loginuser', 'login@example.com', 'Original Name');

        // 停用登入時同步
        update_option('buygo_line_conflict_strategy', 'line_priority');
        update_option('buygo_line_sync_on_login', false);

        $result = ProfileSyncService::syncProfile(300, [
            'displayName' => 'Should Not Update',
        ], 'login');

        $this->assertTrue($result);
        $this->assertEquals('Original Name', $mock_users[300]->display_name);
    }

    /**
     * 測試登入時同步啟用時會更新
     */
    public function testLoginActionUpdatesWhenSyncOnLoginEnabled(): void
    {
        global $mock_users;
        add_mock_user(301, 'loginuser2', 'login2@example.com', '');

        // 啟用登入時同步
        update_option('buygo_line_conflict_strategy', 'line_priority');
        update_option('buygo_line_sync_on_login', true);

        $result = ProfileSyncService::syncProfile(301, [
            'displayName' => 'Login Update Name',
        ], 'login');

        $this->assertTrue($result);
        $this->assertEquals('Login Update Name', $mock_users[301]->display_name);
    }

    // ========================================================================
    // Avatar Tests
    // ========================================================================

    /**
     * 測試頭像 URL 同步
     */
    public function testAvatarUrlSync(): void
    {
        global $mock_user_meta;
        add_mock_user(400, 'avataruser', 'avatar@example.com', 'Avatar User');

        update_option('buygo_line_conflict_strategy', 'line_priority');

        $result = ProfileSyncService::syncProfile(400, [
            'pictureUrl' => 'https://profile.line-scdn.net/avatar123.jpg',
        ], 'register');

        $this->assertTrue($result);
        $this->assertEquals(
            'https://profile.line-scdn.net/avatar123.jpg',
            $mock_user_meta[400]['buygo_line_avatar_url'] ?? ''
        );
    }

    // ========================================================================
    // Email Tests
    // ========================================================================

    /**
     * 測試 email 同步（register 動作）
     */
    public function testEmailSyncOnRegister(): void
    {
        global $mock_users;
        add_mock_user(500, 'emailuser', 'old@example.com', 'Email User');

        update_option('buygo_line_conflict_strategy', 'line_priority');

        $result = ProfileSyncService::syncProfile(500, [
            'email' => 'new@line.me',
        ], 'register');

        $this->assertTrue($result);
        $this->assertEquals('new@line.me', $mock_users[500]->user_email);
    }

    /**
     * 測試 email 已被其他用戶使用時跳過
     */
    public function testEmailSkippedWhenAlreadyUsedByOther(): void
    {
        global $mock_users;
        add_mock_user(501, 'user1', 'existing@example.com', 'User 1');
        add_mock_user(502, 'user2', 'original@example.com', 'User 2');

        update_option('buygo_line_conflict_strategy', 'line_priority');

        // 嘗試將 user2 的 email 改成 user1 已使用的 email
        $result = ProfileSyncService::syncProfile(502, [
            'email' => 'existing@example.com',
        ], 'register');

        $this->assertTrue($result);
        // Email 應該保持原值
        $this->assertEquals('original@example.com', $mock_users[502]->user_email);
    }

    // ========================================================================
    // Sync Log Tests
    // ========================================================================

    /**
     * 測試同步日誌記錄
     */
    public function testSyncLogIsRecorded(): void
    {
        add_mock_user(600, 'loguser', 'log@example.com', 'Old Name');

        update_option('buygo_line_conflict_strategy', 'line_priority');

        ProfileSyncService::syncProfile(600, [
            'displayName' => 'New Name',
        ], 'link');

        $logs = ProfileSyncService::getSyncLog(600);

        $this->assertNotEmpty($logs);
        $this->assertCount(1, $logs);
        $this->assertEquals('link', $logs[0]['action']);
        $this->assertContains('display_name', $logs[0]['changed_fields']);
    }

    /**
     * 測試清除同步日誌
     */
    public function testClearSyncLog(): void
    {
        add_mock_user(601, 'clearloguser', 'clearlog@example.com', 'Name');

        // 寫入日誌
        update_option('buygo_line_sync_log_601', [['test' => 'log']]);

        // 清除日誌
        $result = ProfileSyncService::clearSyncLog(601);

        $this->assertTrue($result);
        $this->assertEmpty(ProfileSyncService::getSyncLog(601));
    }

    /**
     * 測試同步日誌最多保留 10 筆
     */
    public function testSyncLogKeepsOnly10Entries(): void
    {
        add_mock_user(602, 'manyloguser', 'manylog@example.com', '');

        update_option('buygo_line_conflict_strategy', 'line_priority');

        // 執行 15 次同步
        for ($i = 1; $i <= 15; $i++) {
            ProfileSyncService::syncProfile(602, [
                'displayName' => "Name {$i}",
            ], 'link');
        }

        $logs = ProfileSyncService::getSyncLog(602);

        // 應該只保留最近 10 筆
        $this->assertCount(10, $logs);
    }

    // ========================================================================
    // Conflict Log Tests
    // ========================================================================

    /**
     * 測試衝突日誌取得和清除
     */
    public function testConflictLogGetAndClear(): void
    {
        // 寫入模擬衝突日誌
        update_option('buygo_line_conflict_log_700', [
            ['field' => 'display_name', 'current_value' => 'Old', 'new_value' => 'New'],
        ]);

        $logs = ProfileSyncService::getConflictLog(700);
        $this->assertCount(1, $logs);

        $result = ProfileSyncService::clearConflictLog(700);
        $this->assertTrue($result);

        $logs = ProfileSyncService::getConflictLog(700);
        $this->assertEmpty($logs);
    }
}

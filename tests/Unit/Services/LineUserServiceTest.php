<?php
/**
 * LineUserService Unit Tests
 *
 * 測試 LINE 用戶綁定服務的核心功能
 */

namespace BuygoLineNotify\Tests\Unit\Services;

use BuygoLineNotify\Services\LineUserService;
use PHPUnit\Framework\TestCase;

final class LineUserServiceTest extends TestCase
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
    // getUserByLineUid Tests
    // ========================================================================

    /**
     * 測試根據 LINE UID 找到用戶
     */
    public function testGetUserByLineUidReturnsUserIdWhenBound(): void
    {
        // 建立綁定資料
        add_mock_line_binding(123, 'U1234567890abcdef');

        $result = LineUserService::getUserByLineUid('U1234567890abcdef');

        $this->assertEquals(123, $result);
    }

    /**
     * 測試 LINE UID 未綁定時返回 null
     */
    public function testGetUserByLineUidReturnsNullWhenNotBound(): void
    {
        $result = LineUserService::getUserByLineUid('U_nonexistent');

        $this->assertNull($result);
    }

    // ========================================================================
    // getLineUidByUserId Tests
    // ========================================================================

    /**
     * 測試根據 User ID 找到 LINE UID
     */
    public function testGetLineUidByUserIdReturnsLineUidWhenBound(): void
    {
        add_mock_line_binding(456, 'Uabcdef1234567890');

        $result = LineUserService::getLineUidByUserId(456);

        $this->assertEquals('Uabcdef1234567890', $result);
    }

    /**
     * 測試用戶未綁定 LINE 時返回 null
     */
    public function testGetLineUidByUserIdReturnsNullWhenNotBound(): void
    {
        $result = LineUserService::getLineUidByUserId(999);

        $this->assertNull($result);
    }

    // ========================================================================
    // isUserLinked Tests
    // ========================================================================

    /**
     * 測試已綁定用戶返回 true
     */
    public function testIsUserLinkedReturnsTrueWhenBound(): void
    {
        add_mock_line_binding(100, 'Ulinked_user_123');

        $result = LineUserService::isUserLinked(100);

        $this->assertTrue($result);
    }

    /**
     * 測試未綁定用戶返回 false
     */
    public function testIsUserLinkedReturnsFalseWhenNotBound(): void
    {
        $result = LineUserService::isUserLinked(999);

        $this->assertFalse($result);
    }

    // ========================================================================
    // linkUser Tests
    // ========================================================================

    /**
     * 測試成功建立新綁定
     */
    public function testLinkUserCreatesNewBinding(): void
    {
        $result = LineUserService::linkUser(200, 'U_new_binding_123', false);

        $this->assertTrue($result);
        $this->assertEquals(200, LineUserService::getUserByLineUid('U_new_binding_123'));
    }

    /**
     * 測試 LINE UID 已綁定其他用戶時拒絕
     */
    public function testLinkUserRejectsWhenLineUidAlreadyBoundToOther(): void
    {
        // 先建立綁定
        add_mock_line_binding(100, 'U_already_bound');

        // 嘗試將同一個 LINE UID 綁定到其他用戶
        $result = LineUserService::linkUser(200, 'U_already_bound', false);

        $this->assertFalse($result);
    }

    /**
     * 測試用戶已綁定其他 LINE 時拒絕
     */
    public function testLinkUserRejectsWhenUserAlreadyBoundToOtherLine(): void
    {
        // 先建立綁定
        add_mock_line_binding(100, 'U_original_line');

        // 嘗試將同一用戶綁定到其他 LINE
        $result = LineUserService::linkUser(100, 'U_different_line', false);

        $this->assertFalse($result);
    }

    /**
     * 測試註冊流程設定 register_date
     */
    public function testLinkUserWithRegistrationSetsRegisterDate(): void
    {
        $result = LineUserService::linkUser(300, 'U_registration_test', true);

        $this->assertTrue($result);

        $binding = LineUserService::getBinding(300);
        $this->assertNotNull($binding);
        $this->assertNotNull($binding->register_date);
    }

    // ========================================================================
    // unlinkUser Tests
    // ========================================================================

    /**
     * 測試成功解除綁定
     */
    public function testUnlinkUserRemovesBinding(): void
    {
        add_mock_line_binding(400, 'U_to_unlink');

        // 確認綁定存在
        $this->assertTrue(LineUserService::isUserLinked(400));

        // 解除綁定
        $result = LineUserService::unlinkUser(400);

        $this->assertTrue($result);
        $this->assertFalse(LineUserService::isUserLinked(400));
    }

    /**
     * 測試解除不存在的綁定返回 false
     */
    public function testUnlinkUserReturnsFalseWhenNoBinding(): void
    {
        $result = LineUserService::unlinkUser(999);

        $this->assertFalse($result);
    }

    // ========================================================================
    // getBinding Tests
    // ========================================================================

    /**
     * 測試取得完整綁定資料
     */
    public function testGetBindingReturnsCompleteData(): void
    {
        $link_date = '2026-01-15 10:30:00';
        add_mock_line_binding(500, 'U_binding_data_test', null, $link_date);

        $binding = LineUserService::getBinding(500);

        $this->assertNotNull($binding);
        $this->assertEquals(500, $binding->user_id);
        $this->assertEquals('U_binding_data_test', $binding->identifier);
        $this->assertEquals('line', $binding->type);
        $this->assertEquals($link_date, $binding->link_date);
    }

    /**
     * 測試未綁定時返回 null
     */
    public function testGetBindingReturnsNullWhenNotBound(): void
    {
        $binding = LineUserService::getBinding(999);

        $this->assertNull($binding);
    }

    // ========================================================================
    // getBindingByLineUid Tests
    // ========================================================================

    /**
     * 測試根據 LINE UID 取得綁定資料
     */
    public function testGetBindingByLineUidReturnsCompleteData(): void
    {
        add_mock_line_binding(600, 'U_line_uid_binding');

        $binding = LineUserService::getBindingByLineUid('U_line_uid_binding');

        $this->assertNotNull($binding);
        $this->assertEquals(600, $binding->user_id);
        $this->assertEquals('U_line_uid_binding', $binding->identifier);
    }

    // ========================================================================
    // Deprecated Methods Tests (向後相容)
    // ========================================================================

    /**
     * 測試 deprecated bind_line_account 仍然有效
     */
    public function testDeprecatedBindLineAccountStillWorks(): void
    {
        add_mock_user(700, 'test_user', 'test@example.com');

        $result = LineUserService::bind_line_account(700, 'U_deprecated_test', [
            'displayName' => 'Test User',
            'pictureUrl' => 'https://example.com/pic.jpg',
        ]);

        $this->assertTrue($result);
        $this->assertTrue(LineUserService::isUserLinked(700));
    }

    /**
     * 測試 deprecated is_user_bound 仍然有效
     */
    public function testDeprecatedIsUserBoundStillWorks(): void
    {
        add_mock_line_binding(800, 'U_deprecated_check');

        $result = LineUserService::is_user_bound(800);

        $this->assertTrue($result);
    }

    /**
     * 測試 deprecated get_user_line_id 仍然有效
     */
    public function testDeprecatedGetUserLineIdStillWorks(): void
    {
        add_mock_line_binding(900, 'U_deprecated_get');

        $result = LineUserService::get_user_line_id(900);

        $this->assertEquals('U_deprecated_get', $result);
    }
}

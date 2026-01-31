<?php
/**
 * AvatarService Unit Tests
 *
 * 測試 LINE 頭像整合到 WordPress get_avatar_url filter hook
 */

namespace BuygoLineNotify\Tests\Unit\Services;

use BuygoLineNotify\Services\AvatarService;
use PHPUnit\Framework\TestCase;

final class AvatarServiceTest extends TestCase
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
    // filterAvatarUrl Basic Tests
    // ========================================================================

    /**
     * 測試未綁定 LINE 用戶返回原始 URL
     */
    public function testFilterAvatarUrlReturnsOriginalForUnboundUser(): void
    {
        add_mock_user(100, 'unbounduser', 'unbound@example.com');

        $original_url = 'https://gravatar.com/avatar/123';
        $result = AvatarService::filterAvatarUrl($original_url, 100, []);

        $this->assertEquals($original_url, $result);
    }

    /**
     * 測試已綁定但無頭像 URL 的用戶返回原始 URL
     */
    public function testFilterAvatarUrlReturnsOriginalWhenNoAvatarCached(): void
    {
        add_mock_user(101, 'bounduser', 'bound@example.com');
        add_mock_line_binding(101, 'U1234567890abcdef');

        $original_url = 'https://gravatar.com/avatar/456';
        $result = AvatarService::filterAvatarUrl($original_url, 101, []);

        $this->assertEquals($original_url, $result);
    }

    /**
     * 測試已綁定且有快取頭像的用戶返回 LINE 頭像
     */
    public function testFilterAvatarUrlReturnsLineAvatarWhenCached(): void
    {
        global $mock_user_meta;

        add_mock_user(102, 'avataruser', 'avatar@example.com');
        add_mock_line_binding(102, 'Uavatar123');

        // 設定快取頭像
        $line_avatar = 'https://profile.line-scdn.net/0hTestAvatar';
        update_user_meta(102, 'buygo_line_avatar_url', $line_avatar);
        update_user_meta(102, 'buygo_line_avatar_updated', date('Y-m-d H:i:s'));

        $original_url = 'https://gravatar.com/avatar/789';
        $result = AvatarService::filterAvatarUrl($original_url, 102, []);

        $this->assertEquals($line_avatar, $result);
    }

    // ========================================================================
    // getUserIdFromMixed Tests
    // ========================================================================

    /**
     * 測試以數字 ID 識別用戶
     */
    public function testFilterAvatarUrlWithNumericId(): void
    {
        add_mock_user(200, 'numericuser', 'numeric@example.com');
        add_mock_line_binding(200, 'Unumeric123');
        update_user_meta(200, 'buygo_line_avatar_url', 'https://line.me/avatar.jpg');
        update_user_meta(200, 'buygo_line_avatar_updated', date('Y-m-d H:i:s'));

        $result = AvatarService::filterAvatarUrl('original', 200, []);

        $this->assertEquals('https://line.me/avatar.jpg', $result);
    }

    /**
     * 測試以 WP_User 物件識別用戶
     */
    public function testFilterAvatarUrlWithWpUserObject(): void
    {
        add_mock_user(201, 'wpuserobj', 'wpuser@example.com');
        add_mock_line_binding(201, 'Uwpuser123');
        update_user_meta(201, 'buygo_line_avatar_url', 'https://line.me/wpuser.jpg');
        update_user_meta(201, 'buygo_line_avatar_updated', date('Y-m-d H:i:s'));

        $wp_user = new \WP_User(201);
        $wp_user->ID = 201;

        $result = AvatarService::filterAvatarUrl('original', $wp_user, []);

        $this->assertEquals('https://line.me/wpuser.jpg', $result);
    }

    /**
     * 測試以 email 識別用戶
     */
    public function testFilterAvatarUrlWithEmail(): void
    {
        add_mock_user(202, 'emailuser', 'lineuser@example.com');
        add_mock_line_binding(202, 'Uemail123');
        update_user_meta(202, 'buygo_line_avatar_url', 'https://line.me/email.jpg');
        update_user_meta(202, 'buygo_line_avatar_updated', date('Y-m-d H:i:s'));

        $result = AvatarService::filterAvatarUrl('original', 'lineuser@example.com', []);

        $this->assertEquals('https://line.me/email.jpg', $result);
    }

    /**
     * 測試以 WP_Comment 物件識別用戶
     */
    public function testFilterAvatarUrlWithWpCommentObject(): void
    {
        add_mock_user(203, 'commentuser', 'comment@example.com');
        add_mock_line_binding(203, 'Ucomment123');
        update_user_meta(203, 'buygo_line_avatar_url', 'https://line.me/comment.jpg');
        update_user_meta(203, 'buygo_line_avatar_updated', date('Y-m-d H:i:s'));

        $comment = new \WP_Comment(203);

        $result = AvatarService::filterAvatarUrl('original', $comment, []);

        $this->assertEquals('https://line.me/comment.jpg', $result);
    }

    /**
     * 測試以 WP_Post 物件識別用戶（使用 post_author）
     */
    public function testFilterAvatarUrlWithWpPostObject(): void
    {
        add_mock_user(204, 'postauthor', 'author@example.com');
        add_mock_line_binding(204, 'Uauthor123');
        update_user_meta(204, 'buygo_line_avatar_url', 'https://line.me/author.jpg');
        update_user_meta(204, 'buygo_line_avatar_updated', date('Y-m-d H:i:s'));

        $post = new \WP_Post(999, 204);

        $result = AvatarService::filterAvatarUrl('original', $post, []);

        $this->assertEquals('https://line.me/author.jpg', $result);
    }

    /**
     * 測試無法識別的參數返回原始 URL
     */
    public function testFilterAvatarUrlReturnsOriginalForUnknownType(): void
    {
        $original = 'https://gravatar.com/unknown';
        $result = AvatarService::filterAvatarUrl($original, new \stdClass(), []);

        $this->assertEquals($original, $result);
    }

    // ========================================================================
    // Cache Expiry Tests
    // ========================================================================

    /**
     * 測試快取過期仍返回舊 URL
     */
    public function testFilterAvatarUrlReturnsExpiredCacheUrl(): void
    {
        add_mock_user(300, 'expireduser', 'expired@example.com');
        add_mock_line_binding(300, 'Uexpired123');

        $line_avatar = 'https://line.me/expired.jpg';
        update_user_meta(300, 'buygo_line_avatar_url', $line_avatar);
        // 設定 8 天前更新（超過 7 天快取期）
        update_user_meta(300, 'buygo_line_avatar_updated', date('Y-m-d H:i:s', time() - 8 * DAY_IN_SECONDS));

        $result = AvatarService::filterAvatarUrl('original', 300, []);

        // 仍返回快取的 LINE 頭像（等下次登入更新）
        $this->assertEquals($line_avatar, $result);
    }

    /**
     * 測試快取未過期返回 LINE 頭像
     */
    public function testFilterAvatarUrlReturnsValidCacheUrl(): void
    {
        add_mock_user(301, 'validuser', 'valid@example.com');
        add_mock_line_binding(301, 'Uvalid123');

        $line_avatar = 'https://line.me/valid.jpg';
        update_user_meta(301, 'buygo_line_avatar_url', $line_avatar);
        // 設定 3 天前更新（在 7 天快取期內）
        update_user_meta(301, 'buygo_line_avatar_updated', date('Y-m-d H:i:s', time() - 3 * DAY_IN_SECONDS));

        $result = AvatarService::filterAvatarUrl('original', 301, []);

        $this->assertEquals($line_avatar, $result);
    }

    // ========================================================================
    // Cache Clear Tests
    // ========================================================================

    /**
     * 測試清除單一用戶頭像快取
     */
    public function testClearAvatarCache(): void
    {
        global $mock_user_meta;

        add_mock_user(400, 'clearuser', 'clear@example.com');
        update_user_meta(400, 'buygo_line_avatar_url', 'https://line.me/clear.jpg');
        update_user_meta(400, 'buygo_line_avatar_updated', date('Y-m-d H:i:s'));

        $result = AvatarService::clearAvatarCache(400);

        $this->assertTrue($result);
        // avatar_url 應該保留
        $this->assertEquals('https://line.me/clear.jpg', $mock_user_meta[400]['buygo_line_avatar_url']);
        // avatar_updated 應該被刪除
        $this->assertArrayNotHasKey('buygo_line_avatar_updated', $mock_user_meta[400] ?? []);
    }

    // ========================================================================
    // Edge Cases
    // ========================================================================

    /**
     * 測試 WP_Comment 無 user_id 時返回原始 URL
     */
    public function testFilterAvatarUrlWithAnonymousComment(): void
    {
        $comment = new \WP_Comment(0);

        $original = 'https://gravatar.com/anon';
        $result = AvatarService::filterAvatarUrl($original, $comment, []);

        $this->assertEquals($original, $result);
    }

    /**
     * 測試無效 email 返回原始 URL
     */
    public function testFilterAvatarUrlWithInvalidEmail(): void
    {
        $original = 'https://gravatar.com/invalid';
        $result = AvatarService::filterAvatarUrl($original, 'not-an-email', []);

        $this->assertEquals($original, $result);
    }

    /**
     * 測試不存在的 email 用戶返回原始 URL
     */
    public function testFilterAvatarUrlWithNonexistentEmail(): void
    {
        $original = 'https://gravatar.com/nouser';
        $result = AvatarService::filterAvatarUrl($original, 'nouser@example.com', []);

        $this->assertEquals($original, $result);
    }
}

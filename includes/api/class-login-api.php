<?php
/**
 * Login API
 *
 * REST API endpoints for LINE Login OAuth 2.0 flow
 * - GET /login/authorize - Generate LINE authorize URL
 * - GET /login/callback - Handle OAuth callback
 * - POST /login/bind - Bind LINE to logged-in user
 *
 * @deprecated 2.0.0 Use standard WordPress URL (wp-login.php?loginSocial=buygo-line) instead.
 *             REST API endpoints are kept for backward compatibility but will be removed in v3.0.
 *
 * @package BuygoLineNotify
 */

namespace BuygoLineNotify\Api;

use BuygoLineNotify\Services\LoginService;
use BuygoLineNotify\Services\UserService;
use BuygoLineNotify\Services\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Login_API
 *
 * REST API endpoints for LINE Login functionality
 */
class Login_API {

    /**
     * Login Service instance
     *
     * @var LoginService
     */
    private $login_service;

    /**
     * User Service instance
     *
     * @var UserService
     */
    private $user_service;

    /**
     * Constructor
     */
    public function __construct() {
        $this->login_service = new LoginService();
        $this->user_service = new UserService();
    }

    /**
     * Register REST API routes
     *
     * @deprecated 2.0.0 Use Login_Handler::register_hooks() instead.
     */
    public function register_routes() {
        // GET /wp-json/buygo-line-notify/v1/login/authorize
        register_rest_route(
            'buygo-line-notify/v1',
            '/login/authorize',
            [
                'methods' => 'GET',
                'callback' => [$this, 'authorize'],
                'permission_callback' => '__return_true',
                'args' => [
                    'redirect_url' => [
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'esc_url_raw',
                    ],
                ],
            ]
        );

        // GET /wp-json/buygo-line-notify/v1/login/callback
        register_rest_route(
            'buygo-line-notify/v1',
            '/login/callback',
            [
                'methods' => 'GET',
                'callback' => [$this, 'callback'],
                'permission_callback' => '__return_true',
                'args' => [
                    'code' => [
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'state' => [
                        'required' => true,
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ]
        );

        // POST /wp-json/buygo-line-notify/v1/login/bind
        register_rest_route(
            'buygo-line-notify/v1',
            '/login/bind',
            [
                'methods' => 'POST',
                'callback' => [$this, 'bind'],
                'permission_callback' => 'is_user_logged_in',
            ]
        );
    }

    /**
     * GET /login/authorize
     *
     * Generate LINE Login authorize URL
     *
     * @deprecated 2.0.0 Use wp-login.php?loginSocial=buygo-line&redirect_to=URL instead.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function authorize(\WP_REST_Request $request) {
        // 發出 deprecated 警告 header
        header('X-BuyGo-Deprecated: Use wp-login.php?loginSocial=buygo-line instead');

        // 記錄 deprecated 呼叫
        Logger::get_instance()->log('warning', [
            'message' => 'Deprecated REST API /login/authorize called',
            'recommendation' => 'Use wp-login.php?loginSocial=buygo-line instead',
        ]);

        $redirect_url = $request->get_param('redirect_url');

        // Get authorize URL from LoginService
        $authorize_url = $this->login_service->get_authorize_url($redirect_url);

        Logger::get_instance()->log('info', [
            'message' => 'Authorize URL requested',
            'redirect_url' => $redirect_url,
        ]);

        return rest_ensure_response([
            'success' => true,
            'authorize_url' => $authorize_url,
        ]);
    }

    /**
     * GET /login/callback
     *
     * Handle LINE Login OAuth callback
     * - Verify state and exchange code for token
     * - Get LINE profile
     * - Login existing user or create new user
     * - Set auth cookie and redirect
     *
     * @deprecated 2.0.0 OAuth callback now handled by Login_Handler via login_init hook.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function callback(\WP_REST_Request $request) {
        // 發出 deprecated 警告 header
        header('X-BuyGo-Deprecated: OAuth callback now uses wp-login.php entry point');

        // 記錄 deprecated 呼叫
        Logger::get_instance()->log('warning', [
            'message' => 'Deprecated REST API /login/callback called',
            'recommendation' => 'Configure LINE Developers Console redirect_uri to wp-login.php?loginSocial=buygo-line',
        ]);

        $code = $request->get_param('code');
        $state = $request->get_param('state');

        // Debug: 記錄 callback 收到的參數
        Logger::get_instance()->log('debug', [
            'message' => 'Callback received',
            'code' => $code ? substr($code, 0, 20) . '...' : 'null',
            'state' => $state,
            'all_params' => $request->get_params(),
        ]);

        // Handle callback (verify state + exchange token + get profile)
        $result = $this->login_service->handle_callback($code, $state);

        if (is_wp_error($result)) {
            // Callback failed, redirect to redirect_url with error
            Logger::get_instance()->log('error', [
                'message' => 'Callback failed',
                'error' => $result->get_error_message(),
            ]);

            // Try to get redirect_url from error data
            $error_data = $result->get_error_data();
            $redirect_url = $error_data['redirect_url'] ?? home_url();

            wp_redirect(add_query_arg([
                'line_login_error' => $result->get_error_code(),
            ], $redirect_url));
            exit;
        }

        $profile = $result['profile'];
        $state_data = $result['state_data'];
        $line_uid = $profile['userId'];
        $redirect_url = $state_data['redirect_url'] ?? home_url();

        // Check if LINE UID is already bound
        $existing_user_id = $this->user_service->get_user_by_line_uid($line_uid);

        if ($existing_user_id) {
            // LINE UID already bound, login that user
            wp_set_auth_cookie($existing_user_id, true);

            Logger::get_instance()->log('info', [
                'message' => 'User logged in via LINE',
                'user_id' => $existing_user_id,
                'line_uid' => $line_uid,
            ]);

            // 支援 WordPress login_redirect filter（與其他登入導向外掛相容）
            $user = get_user_by('id', $existing_user_id);
            $redirect_url = apply_filters('login_redirect', $redirect_url, '', $user);

            $this->handle_redirect($redirect_url);
        }

        // LINE UID not bound yet
        // Check if state has user_id (bind to existing user)
        if (!empty($state_data['user_id'])) {
            $user_id = $state_data['user_id'];

            // Bind LINE to existing user
            $bind_result = $this->user_service->bind_line_to_user($user_id, $profile);

            if (is_wp_error($bind_result)) {
                Logger::get_instance()->log('error', [
                    'message' => 'Failed to bind LINE to user',
                    'user_id' => $user_id,
                    'line_uid' => $line_uid,
                    'error' => $bind_result->get_error_message(),
                ]);

                wp_redirect(add_query_arg([
                    'line_login_error' => $bind_result->get_error_code(),
                ], $redirect_url));
                exit;
            }

            // Bind successful, login user
            wp_set_auth_cookie($user_id, true);

            Logger::get_instance()->log('info', [
                'message' => 'LINE bound to existing user',
                'user_id' => $user_id,
                'line_uid' => $line_uid,
            ]);

            // 支援 WordPress login_redirect filter
            $user = get_user_by('id', $user_id);
            $redirect_url = apply_filters('login_redirect', $redirect_url, '', $user);

            $this->handle_redirect($redirect_url);
        }

        // No user_id in state, create new user from LINE profile
        $new_user_id = $this->user_service->create_user_from_line($profile);

        if (is_wp_error($new_user_id)) {
            Logger::get_instance()->log('error', [
                'message' => 'Failed to create user from LINE',
                'line_uid' => $line_uid,
                'error' => $new_user_id->get_error_message(),
            ]);

            wp_redirect(add_query_arg([
                'line_login_error' => $new_user_id->get_error_code(),
            ], $redirect_url));
            exit;
        }

        // User created successfully, login user
        wp_set_auth_cookie($new_user_id, true);

        Logger::get_instance()->log('info', [
            'message' => 'New user created from LINE',
            'user_id' => $new_user_id,
            'line_uid' => $line_uid,
        ]);

        // 支援 WordPress login_redirect filter
        $user = get_user_by('id', $new_user_id);
        $redirect_url = apply_filters('login_redirect', $redirect_url, '', $user);

        $this->handle_redirect($redirect_url);
    }

    /**
     * POST /login/bind
     *
     * Bind LINE to logged-in user
     * - Requires user to be logged in
     * - Returns authorize URL with user_id in state
     *
     * @deprecated 2.0.0 Bind flow will use standard WordPress URL in future versions.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function bind(\WP_REST_Request $request) {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return new \WP_Error(
                'not_logged_in',
                'User must be logged in to bind LINE account',
                ['status' => 401]
            );
        }

        // Get redirect URL from request (optional)
        $redirect_url = $request->get_param('redirect_url');
        if (empty($redirect_url)) {
            $redirect_url = home_url();
        }

        // Generate authorize URL with user_id in state
        $authorize_url = $this->login_service->get_authorize_url($redirect_url, $user_id);

        Logger::get_instance()->log('info', [
            'message' => 'Bind authorize URL requested',
            'user_id' => $user_id,
            'redirect_url' => $redirect_url,
        ]);

        return rest_ensure_response([
            'success' => true,
            'authorize_url' => $authorize_url,
        ]);
    }

    /**
     * 處理導向（支援新分頁自動關閉）
     *
     * 如果是從新分頁開啟，顯示自動關閉分頁並重新導向原頁面的 HTML
     * 否則正常 redirect
     *
     * @param string $redirect_url 導向 URL
     */
    private function handle_redirect($redirect_url) {
        // 設定正確的 Content-Type
        status_header(200);
        header('Content-Type: text/html; charset=utf-8');

        // 清除任何之前的輸出
        if (ob_get_level()) {
            ob_end_clean();
        }

        // 輸出 HTML
        echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登入成功</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #06C755 0%, #05b34a 100%);
            color: white;
        }
        .container {
            text-align: center;
            padding: 2rem;
        }
        .icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        h1 {
            font-size: 1.5rem;
            margin: 0 0 0.5rem 0;
        }
        p {
            font-size: 1rem;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">✓</div>
        <h1>登入成功</h1>
        <p>正在返回...</p>
    </div>
    <script>
    (function() {
        // 如果有 opener（從新分頁開啟），重新導向原頁面並關閉此分頁
        if (window.opener && !window.opener.closed) {
            window.opener.location.href = ' . wp_json_encode($redirect_url) . ';
            window.close();
        } else {
            // 否則直接導向
            window.location.href = ' . wp_json_encode($redirect_url) . ';
        }
    })();
    </script>
</body>
</html>';
        exit;
    }
}

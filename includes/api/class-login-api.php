<?php
/**
 * Login API
 *
 * REST API endpoints for LINE Login OAuth 2.0 flow
 * - GET /login/authorize - Generate LINE authorize URL
 * - GET /login/callback - Handle OAuth callback
 * - POST /login/bind - Bind LINE to logged-in user
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
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function authorize(\WP_REST_Request $request) {
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
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function callback(\WP_REST_Request $request) {
        $code = $request->get_param('code');
        $state = $request->get_param('state');

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

            wp_redirect($redirect_url);
            exit;
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

            wp_redirect($redirect_url);
            exit;
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

        wp_redirect($redirect_url);
        exit;
    }

    /**
     * POST /login/bind
     *
     * Bind LINE to logged-in user
     * - Requires user to be logged in
     * - Returns authorize URL with user_id in state
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
}

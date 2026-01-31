<?php
/**
 * Bootstrap for unit tests (no WordPress required)
 *
 * 提供 WordPress 函數和類別的 mock，讓純 PHP 測試可以執行
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

if (!defined('WPINC')) {
    define('WPINC', 'wp-includes');
}

if (!defined('BuygoLineNotify_PLUGIN_DIR')) {
    define('BuygoLineNotify_PLUGIN_DIR', dirname(__DIR__) . '/');
}

// WP_DEBUG for testing
if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}

// WordPress time constants
if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

/**
 * Mock WP_REST_Request class
 */
if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request {
        private $headers = [];
        private $body = '';
        private $params = [];

        public function set_header(string $name, string $value): void {
            $this->headers[strtolower($name)] = $value;
        }

        public function get_header(string $name): ?string {
            return $this->headers[strtolower($name)] ?? null;
        }

        public function set_body(string $body): void {
            $this->body = $body;
        }

        public function get_body(): string {
            return $this->body;
        }

        public function set_param(string $key, $value): void {
            $this->params[$key] = $value;
        }

        public function get_param(string $key) {
            return $this->params[$key] ?? null;
        }
    }
}

/**
 * Mock WP_Error class
 */
if (!class_exists('WP_Error')) {
    class WP_Error {
        private $code;
        private $message;
        private $data;

        public function __construct($code = '', $message = '', $data = '') {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
        }

        public function get_error_code() {
            return $this->code;
        }

        public function get_error_message() {
            return $this->message;
        }

        public function get_error_data() {
            return $this->data;
        }
    }
}

/**
 * Mock WP_User class
 */
if (!class_exists('WP_User')) {
    class WP_User {
        public $ID;
        public $user_login;
        public $user_email;
        public $display_name;

        public function __construct($id = 0) {
            $this->ID = $id;
        }
    }
}

/**
 * Mock WP_Comment class
 */
if (!class_exists('WP_Comment')) {
    class WP_Comment {
        public $user_id;
        public $comment_author_email;

        public function __construct($user_id = 0) {
            $this->user_id = $user_id;
        }
    }
}

/**
 * Mock WP_Post class
 */
if (!class_exists('WP_Post')) {
    class WP_Post {
        public $ID;
        public $post_author;

        public function __construct($id = 0, $author = 0) {
            $this->ID = $id;
            $this->post_author = $author;
        }
    }
}

// 載入 Mock Logger（必須在 autoloader 之前）
require_once __DIR__ . '/Mocks/MockLogger.php';

// 載入 Composer autoloader
$composer_autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!file_exists($composer_autoload)) {
    die('Unable to find Composer autoloader: ' . $composer_autoload);
}
require_once $composer_autoload;

/**
 * Mock WordPress functions for unit testing
 */

// In-memory storage for mocked data
global $mock_transients, $mock_user_meta, $mock_options, $mock_users;
$mock_transients = [];
$mock_user_meta = [];
$mock_options = [];
$mock_users = [];

// Transient functions
if (!function_exists('set_transient')) {
    function set_transient($transient, $value, $expiration = 0) {
        global $mock_transients;
        $mock_transients[$transient] = [
            'value' => $value,
            'expiration' => $expiration > 0 ? time() + $expiration : 0,
        ];
        return true;
    }
}

if (!function_exists('get_transient')) {
    function get_transient($transient) {
        global $mock_transients;
        if (!isset($mock_transients[$transient])) {
            return false;
        }
        $data = $mock_transients[$transient];
        if ($data['expiration'] > 0 && $data['expiration'] < time()) {
            unset($mock_transients[$transient]);
            return false;
        }
        return $data['value'];
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($transient) {
        global $mock_transients;
        unset($mock_transients[$transient]);
        return true;
    }
}

// User meta functions
if (!function_exists('get_user_meta')) {
    function get_user_meta($user_id, $key = '', $single = false) {
        global $mock_user_meta;
        if (empty($key)) {
            return $mock_user_meta[$user_id] ?? [];
        }
        $value = $mock_user_meta[$user_id][$key] ?? null;
        if ($single) {
            return $value;
        }
        return $value !== null ? [$value] : [];
    }
}

if (!function_exists('update_user_meta')) {
    function update_user_meta($user_id, $meta_key, $meta_value) {
        global $mock_user_meta;
        if (!isset($mock_user_meta[$user_id])) {
            $mock_user_meta[$user_id] = [];
        }
        $mock_user_meta[$user_id][$meta_key] = $meta_value;
        return true;
    }
}

if (!function_exists('delete_user_meta')) {
    function delete_user_meta($user_id, $meta_key) {
        global $mock_user_meta;
        unset($mock_user_meta[$user_id][$meta_key]);
        return true;
    }
}

// Options functions
if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        global $mock_options;
        return $mock_options[$option] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value, $autoload = null) {
        global $mock_options;
        $mock_options[$option] = $value;
        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option($option) {
        global $mock_options;
        unset($mock_options[$option]);
        return true;
    }
}

// User functions
if (!function_exists('get_user_by')) {
    function get_user_by($field, $value) {
        global $mock_users;
        foreach ($mock_users as $user) {
            // 處理 email 欄位名稱映射
            if ($field === 'email' && isset($user->user_email) && $user->user_email == $value) {
                return $user;
            }
            if (isset($user->$field) && $user->$field == $value) {
                return $user;
            }
            if ($field === 'id' && $user->ID == $value) {
                return $user;
            }
        }
        return false;
    }
}

if (!function_exists('get_users')) {
    function get_users($args = []) {
        global $mock_users, $mock_user_meta;
        $results = [];

        foreach ($mock_users as $user) {
            $match = true;

            if (isset($args['meta_key']) && isset($args['meta_value'])) {
                $user_meta = $mock_user_meta[$user->ID] ?? [];
                if (!isset($user_meta[$args['meta_key']]) ||
                    $user_meta[$args['meta_key']] !== $args['meta_value']) {
                    $match = false;
                }
            }

            if ($match) {
                if (isset($args['fields']) && $args['fields'] === 'ID') {
                    $results[] = $user->ID;
                } else {
                    $results[] = $user;
                }
            }

            if (isset($args['number']) && count($results) >= $args['number']) {
                break;
            }
        }

        return $results;
    }
}

if (!function_exists('username_exists')) {
    function username_exists($username) {
        global $mock_users;
        foreach ($mock_users as $user) {
            if ($user->user_login === $username) {
                return $user->ID;
            }
        }
        return false;
    }
}

if (!function_exists('email_exists')) {
    function email_exists($email) {
        global $mock_users;
        foreach ($mock_users as $user) {
            if ($user->user_email === $email) {
                return $user->ID;
            }
        }
        return false;
    }
}

if (!function_exists('wp_create_user')) {
    function wp_create_user($username, $password, $email = '') {
        global $mock_users;
        $user_id = count($mock_users) + 1;

        $user = new stdClass();
        $user->ID = $user_id;
        $user->user_login = $username;
        $user->user_email = $email;
        $user->display_name = $username;

        $mock_users[$user_id] = $user;
        return $user_id;
    }
}

if (!function_exists('wp_update_user')) {
    function wp_update_user($userdata) {
        global $mock_users;
        $user_id = $userdata['ID'];
        if (isset($mock_users[$user_id])) {
            foreach ($userdata as $key => $value) {
                $mock_users[$user_id]->$key = $value;
            }
            return $user_id;
        }
        return new WP_Error('invalid_user', 'Invalid user ID');
    }
}

if (!function_exists('wp_delete_user')) {
    function wp_delete_user($user_id) {
        global $mock_users, $mock_user_meta;
        unset($mock_users[$user_id]);
        unset($mock_user_meta[$user_id]);
        return true;
    }
}

if (!function_exists('wp_generate_password')) {
    function wp_generate_password($length = 12, $special_chars = true) {
        return bin2hex(random_bytes($length / 2));
    }
}

// Sanitization functions
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(strip_tags($str));
    }
}

if (!function_exists('sanitize_url')) {
    function sanitize_url($url) {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($email) {
        return filter_var($email, FILTER_SANITIZE_EMAIL);
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url) {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
}

if (!function_exists('sanitize_user')) {
    function sanitize_user($username, $strict = false) {
        $username = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $username);
        return $username;
    }
}

if (!function_exists('validate_username')) {
    function validate_username($username) {
        return !empty($username) && strlen($username) <= 60;
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('wp_get_environment_type')) {
    function wp_get_environment_type() {
        return 'development';
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value) {
        return stripslashes($value);
    }
}

if (!function_exists('current_time')) {
    function current_time($type = 'mysql', $gmt = 0) {
        if ($type === 'mysql') {
            return date('Y-m-d H:i:s');
        }
        return time();
    }
}

if (!function_exists('is_email')) {
    function is_email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

/**
 * Mock $wpdb class for unit testing
 *
 * 模擬 WordPress 資料庫類別
 */
class MockWpdb {
    public $prefix = 'wp_';
    public $last_error = '';
    private $tables = [];

    public function prepare($query, ...$args) {
        // 簡單的 prepare 模擬：替換 %s, %d 為實際值
        $i = 0;
        return preg_replace_callback('/%[sd]/', function($matches) use (&$i, $args) {
            $value = $args[$i] ?? '';
            $i++;
            if ($matches[0] === '%d') {
                return (int) $value;
            }
            return "'" . addslashes($value) . "'";
        }, $query);
    }

    public function get_var($query) {
        // 模擬查詢：從 in-memory tables 取值
        global $mock_line_users;
        if (!isset($mock_line_users)) {
            return null;
        }

        // 解析 SELECT user_id FROM wp_buygo_line_users WHERE identifier = '...'
        if (preg_match("/SELECT user_id FROM.*WHERE identifier = '([^']+)'/", $query, $matches)) {
            $line_uid = $matches[1];
            foreach ($mock_line_users as $row) {
                if ($row['identifier'] === $line_uid && $row['type'] === 'line') {
                    return $row['user_id'];
                }
            }
        }

        // 解析 SELECT identifier FROM wp_buygo_line_users WHERE user_id = ...
        if (preg_match("/SELECT identifier FROM.*WHERE user_id = (\d+)/", $query, $matches)) {
            $user_id = (int) $matches[1];
            foreach ($mock_line_users as $row) {
                if ($row['user_id'] === $user_id && $row['type'] === 'line') {
                    return $row['identifier'];
                }
            }
        }

        return null;
    }

    public function get_row($query) {
        global $mock_line_users;
        if (!isset($mock_line_users)) {
            return null;
        }

        // SELECT * FROM wp_buygo_line_users WHERE ...
        if (preg_match("/SELECT \* FROM.*WHERE user_id = (\d+)/", $query, $matches)) {
            $user_id = (int) $matches[1];
            foreach ($mock_line_users as $row) {
                if ($row['user_id'] === $user_id && $row['type'] === 'line') {
                    return (object) $row;
                }
            }
        }

        if (preg_match("/SELECT \* FROM.*WHERE identifier = '([^']+)'/", $query, $matches)) {
            $line_uid = $matches[1];
            foreach ($mock_line_users as $row) {
                if ($row['identifier'] === $line_uid && $row['type'] === 'line') {
                    return (object) $row;
                }
            }
        }

        if (preg_match("/SELECT register_date FROM.*WHERE user_id = (\d+) AND identifier = '([^']+)'/", $query, $matches)) {
            $user_id = (int) $matches[1];
            $line_uid = $matches[2];
            foreach ($mock_line_users as $row) {
                if ($row['user_id'] === $user_id && $row['identifier'] === $line_uid && $row['type'] === 'line') {
                    return (object) ['register_date' => $row['register_date'] ?? null];
                }
            }
        }

        return null;
    }

    public function insert($table, $data, $format = null) {
        global $mock_line_users;
        if (!isset($mock_line_users)) {
            $mock_line_users = [];
        }

        $data['ID'] = count($mock_line_users) + 1;
        $mock_line_users[] = $data;
        return 1;
    }

    public function update($table, $data, $where, $format = null, $where_format = null) {
        global $mock_line_users;
        if (!isset($mock_line_users)) {
            return false;
        }

        foreach ($mock_line_users as &$row) {
            $match = true;
            foreach ($where as $key => $value) {
                if (!isset($row[$key]) || $row[$key] !== $value) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                foreach ($data as $key => $value) {
                    $row[$key] = $value;
                }
                return 1;
            }
        }
        return false;
    }

    public function delete($table, $where, $where_format = null) {
        global $mock_line_users;
        if (!isset($mock_line_users)) {
            return false;
        }

        $initial_count = count($mock_line_users);
        $mock_line_users = array_filter($mock_line_users, function($row) use ($where) {
            foreach ($where as $key => $value) {
                if (!isset($row[$key]) || $row[$key] !== $value) {
                    return true; // keep row
                }
            }
            return false; // delete row
        });
        $mock_line_users = array_values($mock_line_users);

        return $initial_count !== count($mock_line_users) ? 1 : false;
    }
}

// 建立 global $wpdb mock
global $wpdb;
$wpdb = new MockWpdb();

// Helper function to reset all mocks
function reset_mock_data() {
    global $mock_transients, $mock_user_meta, $mock_options, $mock_users, $mock_line_users;
    $mock_transients = [];
    $mock_user_meta = [];
    $mock_options = [];
    $mock_users = [];
    $mock_line_users = [];
    \BuygoLineNotify\Services\Logger::clearLogs();
}

// Helper function to add mock line user binding
function add_mock_line_binding($user_id, $line_uid, $register_date = null, $link_date = null) {
    global $mock_line_users;
    if (!isset($mock_line_users)) {
        $mock_line_users = [];
    }
    $mock_line_users[] = [
        'ID' => count($mock_line_users) + 1,
        'type' => 'line',
        'identifier' => $line_uid,
        'user_id' => $user_id,
        'register_date' => $register_date,
        'link_date' => $link_date ?? date('Y-m-d H:i:s'),
    ];
}

// Helper function to add mock user
function add_mock_user($id, $login, $email, $display_name = null) {
    global $mock_users;
    $user = new stdClass();
    $user->ID = $id;
    $user->user_login = $login;
    $user->user_email = $email;
    $user->display_name = $display_name ?? $login;
    $mock_users[$id] = $user;
    return $user;
}

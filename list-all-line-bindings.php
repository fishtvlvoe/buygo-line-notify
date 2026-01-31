<?php
/**
 * 列出所有 LINE 綁定
 */
require_once '/Users/fishtv/Local Sites/buygo/app/public/wp-load.php';

if (!current_user_can('manage_options')) {
    wp_die('需要管理員權限');
}

echo '<h1>所有 LINE 綁定列表</h1>';
echo '<style>
    body { font-family: system-ui; padding: 20px; max-width: 1400px; margin: 0 auto; }
    table { border-collapse: collapse; width: 100%; margin: 20px 0; }
    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
    th { background: #06C755; color: white; font-weight: 600; }
    tr:nth-child(even) { background: #f9f9f9; }
    .highlight { background: #fff3cd !important; font-weight: bold; }
</style>';

global $wpdb;
$table_name = $wpdb->prefix . 'buygo_line_users';

$results = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY id DESC", ARRAY_A);

if ($results) {
    echo '<p>找到 <strong>' . count($results) . '</strong> 筆 LINE 綁定紀錄:</p>';
    echo '<table>';
    echo '<tr>';
    echo '<th>ID</th>';
    echo '<th>User ID</th>';
    echo '<th>Username</th>';
    echo '<th>Email</th>';
    echo '<th>LINE UID</th>';
    echo '<th>綁定日期</th>';
    echo '</tr>';
    
    $current_user_id = get_current_user_id();
    
    foreach ($results as $row) {
        $user = get_user_by('id', $row['user_id']);
        $is_current = ($row['user_id'] == $current_user_id);
        
        echo '<tr' . ($is_current ? ' class="highlight"' : '') . '>';
        echo '<td>' . $row['id'] . '</td>';
        echo '<td>' . $row['user_id'] . ($is_current ? ' <strong>(當前登入)</strong>' : '') . '</td>';
        echo '<td>' . ($user ? $user->user_login : '<em>用戶不存在</em>') . '</td>';
        echo '<td>' . ($user ? $user->user_email : '') . '</td>';
        echo '<td><code>' . esc_html($row['line_uid']) . '</code></td>';
        echo '<td>' . $row['link_date'] . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
} else {
    echo '<p style="color: #dc3545;"><strong>找不到任何 LINE 綁定紀錄!</strong></p>';
}

echo '<hr>';
echo '<p><a href="' . admin_url('users.php') . '">查看所有用戶</a></p>';

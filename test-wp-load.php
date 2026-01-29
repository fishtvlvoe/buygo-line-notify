<?php
// 簡單測試 WordPress 載入
require_once '/Users/fishtv/Local Sites/buygo/app/public/wp-load.php';

echo "WordPress 載入成功！<br>";
echo "WordPress 版本：" . get_bloginfo('version') . "<br>";
echo "網站名稱：" . get_bloginfo('name') . "<br>";

if ( is_user_logged_in() ) {
    $user = wp_get_current_user();
    echo "已登入用戶：" . $user->user_login . "<br>";
} else {
    echo "未登入<br>";
}

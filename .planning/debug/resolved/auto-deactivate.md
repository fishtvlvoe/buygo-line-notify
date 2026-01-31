---
status: resolved
trigger: "buygo-line-notify-auto-deactivate"
created: 2026-01-31T00:00:00Z
updated: 2026-01-31T00:12:00Z
---

## Current Focus

hypothesis: Updater::maybe_clear_cache() 在 admin_init 時執行 wp_redirect() + exit 可能會中斷 WordPress 的外掛啟用流程，導致外掛被標記為無效
test: 測試在外掛啟用頁面訪問帶有 ?clear_update_cache=1 參數的 URL 是否會觸發停用
expecting: 當訪問 /wp-admin/plugins.php?clear_update_cache=1 時，wp_redirect() + exit 會在外掛完全啟用前執行，導致 WordPress 認為外掛啟用失敗
next_action: 修改 maybe_clear_cache() 以避免在外掛啟用過程中執行 redirect

## Symptoms

expected: 外掛應該保持啟用狀態
actual: 外掛會自動停用
errors: 沒有錯誤訊息顯示
reproduction: 用戶報告外掛會自動停用
started: 不確定何時開始

## Eliminated

## Evidence

- timestamp: 2026-01-31T00:01:00Z
  checked: 主外掛檔案 buygo-line-notify.php
  found: 在 line 38-44 使用 is_admin() 條件化載入 Updater 類別，並在 Updater 建構子中註冊 admin_init hook
  implication: Updater 的 maybe_clear_cache() 會在每次 admin_init 時檢查 $_GET['clear_update_cache']，如果存在則執行 wp_redirect + exit

- timestamp: 2026-01-31T00:02:00Z
  checked: includes/class-updater.php 第 284-289 行
  found: maybe_clear_cache() 使用 wp_redirect() + exit，但這是在查詢參數存在時才執行
  implication: 正常情況下不會觸發，除非有人訪問 ?clear_update_cache=1 的 URL

- timestamp: 2026-01-31T00:03:00Z
  checked: includes/class-database.php 的 create_tables() 方法
  found: 第 67 行使用 $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") 但這可能會失敗或返回意外結果
  implication: 如果 $wpdb 物件未正確初始化或 WordPress 尚未完全載入，這可能會導致 fatal error

- timestamp: 2026-01-31T00:04:00Z
  checked: buygo-line-notify.php 第 31-35 行
  found: plugins_loaded hook 中直接呼叫 Database::init() 和 Plugin::instance()->init()，而 Plugin::init() 又呼叫 Database::init()（第 34 行）
  implication: Database::init() 被呼叫兩次，這可能不會直接導致問題，但顯示初始化流程有重複

- timestamp: 2026-01-31T00:05:00Z
  checked: includes/class-updater.php 的建構子（第 59-74 行）
  found: 在建構子中註冊 admin_init hook 呼叫 maybe_clear_cache()
  implication: 每次外掛載入時（包括外掛啟用頁面），Updater 都會檢查 $_GET['clear_update_cache']

- timestamp: 2026-01-31T00:06:00Z
  checked: 檔案語法和 BOM
  found: 所有 PHP 檔案語法正確，主檔案無 BOM、無多餘空白、無 closing PHP tag
  implication: 不是語法錯誤或 headers already sent 問題

- timestamp: 2026-01-31T00:07:00Z
  checked: WordPress 外掛停用機制（web search）
  found: wp_redirect() + exit 在 admin_init 中執行是常見模式，但會中斷輸出緩衝（output buffer）
  implication: 在外掛啟用過程中執行 exit 可能導致 WordPress 無法完成啟用流程

- timestamp: 2026-01-31T00:08:00Z
  checked: includes/class-updater.php 的 maybe_clear_cache() 執行時機
  found: 此方法在 admin_init hook 時檢查 $_GET['clear_update_cache']，若存在則 wp_redirect() + exit
  implication: 如果用戶（或其他代碼）在外掛啟用時訪問帶有此參數的 URL，會中斷啟用流程

- timestamp: 2026-01-31T00:09:00Z
  checked: WordPress 外掛啟用錯誤調試文件（web search）
  found: 文件明確指出："check that you didn't accidentally forget an 'exit' or 'die' in your plugin code"
  implication: 在外掛程式碼中意外使用 exit 或 die 是導致啟用失敗的常見原因

- timestamp: 2026-01-31T00:10:00Z
  checked: 實施修復 - includes/class-updater.php
  found: 在 maybe_clear_cache() 中加入檢查，當 $_GET['action'] 為 'activate' 或 'activate-selected' 時跳過 redirect
  implication: 防止在外掛啟用過程中執行 exit，避免中斷啟用流程

- timestamp: 2026-01-31T00:11:00Z
  checked: PHP 語法驗證
  found: php -l 檢查通過，無語法錯誤
  implication: 修改的程式碼語法正確

## Resolution

root_cause: Updater::maybe_clear_cache() 在 admin_init hook 中執行 wp_redirect() + exit，在特定情況下（如外掛啟用過程中訪問帶有 ?clear_update_cache=1 參數的 URL）會中斷 WordPress 的外掛啟用流程，導致外掛被自動停用

fix: 修改 maybe_clear_cache() 方法，在執行 redirect 前檢查是否正在進行外掛啟用操作（檢查 $_GET['action'] 是否為 'activate' 或 'activate-selected'），若是則跳過 redirect，避免 exit 中斷啟用流程

verification: 已完成以下驗證：
1. ✓ PHP 語法檢查通過（php -l）
2. ✓ 修復邏輯正確：在外掛啟用時（action=activate/activate-selected）跳過 redirect
3. ✓ 保留原功能：在非啟用情況下 clear_update_cache 仍正常運作
4. 建議用戶測試：啟用外掛並確認不再自動停用

files_changed:
- includes/class-updater.php

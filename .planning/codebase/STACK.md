# Technology Stack

## Languages & Runtime

**Primary Language:** PHP 8.0+
- Namespace: `BuygoLineNotify\`
- PSR-4 autoloading
- Modern PHP features (typed properties, constructor property promotion support)

## Framework & Platform

**WordPress Plugin**
- Plugin architecture following WordPress standards
- Hooks: `init`, `admin_init`
- WordPress functions: `add_action`, `wp_remote_post`, `wp_json_encode`
- Text domain: `buygo-line-notify`

## Dependencies

### Production
- PHP ^8.0 || ^8.1 || ^8.2 || ^8.3

### Development
- PHPUnit 9.6 (testing framework)
- Yoast PHPUnit Polyfills 2.0 (WordPress compatibility for tests)

## External Services

**LINE Messaging API**
- Reply Message API: `https://api.line.me/v2/bot/message/reply`
- Push Message API: `https://api.line.me/v2/bot/message/push`
- Authentication: Bearer token (Channel Access Token)

## Testing

**Unit Tests**
- Framework: PHPUnit 9.6
- Configuration: `phpunit-unit.xml`
- No WordPress dependencies in unit tests (純 PHP 測試)
- Test structure: `tests/Unit/`
- Scripts:
  - `composer test` - 執行所有測試
  - `composer test:unit` - 詳細輸出
  - `composer test:coverage` - 覆蓋率報告

## Build & Deployment

**Package Manager:** Composer
- Autoloading: PSR-4 + classmap
- Scripts defined for testing

**Version Control:** Git (implicit from structure)

## Configuration

**Plugin Constants:**
- `BuygoLineNotify_PLUGIN_VERSION` - Plugin version (0.1.0)
- `BuygoLineNotify_PLUGIN_DIR` - Plugin directory path
- `BuygoLineNotify_PLUGIN_URL` - Plugin URL

## Notes

這是一個從 `wordpress-plugin-kit` 生成的骨架，已經具備：
- 完整的測試基礎設施
- 標準的 WordPress 外掛結構
- 服務層架構（從 buygo-plus-one-dev 遷移而來）

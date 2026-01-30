# BuyGo Line Notify 發布指南

## 🚀 快速發布新版本

只需要一個指令，完全自動化：

```bash
cd /Users/fishtv/Development/buygo-line-notify
./release.sh
```

腳本會自動完成：
1. ✅ 更新版本號（自動或手動選擇）
2. ✅ 建立 Git commit
3. ✅ 建立並推送 Git tag
4. ✅ 打包外掛（排除開發檔案）
5. ✅ 建立 GitHub Release（附上 ZIP）

執行完成後，所有安裝此外掛的 WordPress 站點會在 **12 小時內**自動檢測到更新。

---

## 📋 發布流程詳解

### 1. 執行發布腳本

```bash
./release.sh
```

### 2. 選擇版本類型

腳本會詢問要更新的版本類型：

```
選擇版本更新類型：
1) Patch (修訂號 +1) - 修 bug、小修正      例如: 0.1.0 → 0.1.1
2) Minor (次版本號 +1) - 新增功能          例如: 0.1.0 → 0.2.0
3) Major (主版本號 +1) - 重大變更          例如: 0.1.0 → 1.0.0
4) 自訂版本號
```

### 3. 輸入 Release Notes

腳本會要求輸入更新說明（可選）：

```
請輸入 Release Notes（可以多行，輸入 'END' 結束）:
- 修正 LINE 通知發送失敗的問題
- 新增 Webhook 日誌記錄功能
- 改善錯誤訊息顯示
END
```

### 4. 完成發布

腳本會顯示發布結果：

```
✅ 發布完成！
版本: 0.1.1
Tag: v0.1.1
GitHub Release: https://github.com/fishtvlvoe/buygo-line-notify/releases/tag/v0.1.1

提醒: WordPress 站點會在 12 小時內自動檢測到更新
或在外掛頁面加上 ?clear_update_cache=1 立即檢查更新
```

---

## 🔧 手動測試更新

如果想立即測試更新功能，不想等 12 小時：

1. 前往 WordPress 後台的外掛頁面
2. 在網址列加上 `?clear_update_cache=1`
3. 重新整理頁面
4. 應該會看到更新通知

---

## 📦 打包內容

發布時會自動排除以下開發檔案：

- `.git*` - Git 相關檔案
- `node_modules/` - Node.js 依賴
- `vendor/` - Composer 依賴（開發用）
- `tests/` - 測試檔案
- `*.sh` - 腳本檔案
- `.planning/` - 專案規劃檔案
- 開發用設定檔（composer.lock, phpunit.xml 等）

只會包含執行外掛必要的檔案。

---

## ⚠️ 注意事項

### GitHub CLI 必須已登入

首次使用需要登入 GitHub CLI：

```bash
gh auth login
```

### 版本號規則

遵循語意化版本（Semantic Versioning）：

- **Patch** (0.1.0 → 0.1.1): 向後相容的 bug 修正
- **Minor** (0.1.0 → 0.2.0): 向後相容的新功能
- **Major** (0.1.0 → 1.0.0): 不向後相容的重大變更

### 發布前檢查

- ✅ 確認所有變更已提交
- ✅ 確認功能正常運作
- ✅ 更新 README.md（如有需要）

---

## 🎯 使用情境範例

### 修正一個 Bug

```bash
# 1. 修正程式碼
# 2. 測試確認
# 3. 執行發布
./release.sh
# 選擇 1 (Patch)
```

### 新增功能

```bash
# 1. 開發新功能
# 2. 測試確認
# 3. 執行發布
./release.sh
# 選擇 2 (Minor)
```

### 重大更新

```bash
# 1. 完成重大變更
# 2. 完整測試
# 3. 執行發布
./release.sh
# 選擇 3 (Major)
```

---

## 📞 故障排除

### 錯誤：需要安裝 GitHub CLI

```bash
brew install gh
gh auth login
```

### 錯誤：工作目錄有未提交的變更

腳本會提示先提交變更，或取消發布。

### 更新沒有出現在 WordPress 後台

1. 清除更新快取：外掛頁面加上 `?clear_update_cache=1`
2. 等待 12 小時後 WordPress 自動檢查
3. 確認 GitHub Release 已正確建立

---

## 🔗 相關連結

- GitHub Repository: https://github.com/fishtvlvoe/buygo-line-notify
- GitHub Releases: https://github.com/fishtvlvoe/buygo-line-notify/releases

# Roadmap: BuyGo LINE Notify

## Overview

這個專案提供 WordPress 網站 LINE 整合功能，包括 Webhook 接收/發送和 LINE Login。

## Milestones

### ✅ v0.1 Milestone (Completed)

**完成日期:** 2026-01-28

- [x] **Phase 1: 基礎設施與設定** - 資料庫結構、後台管理頁面、設定管理系統
- [x] **Phase 2: Webhook 系統** - LINE Webhook 處理、簽名驗證、事件去重、背景處理

### ✅ v0.2 Milestone (Completed)

**完成日期:** 2026-01-31

- [x] **Phase 8: 資料表架構與查詢 API** - wp_buygo_line_users 專用表、資料遷移、查詢 API
- [x] **Phase 9: 標準 WordPress URL 機制** - login_init hook、OAuth callback、取代 REST API
- [x] **Phase 10: Register Flow Page 系統** - NSLContinuePageRenderException、Shortcode、表單處理
- [x] **Phase 11: 完整註冊/登入/綁定流程** - 新用戶註冊、Auto-link、已登入綁定、登入流程
- [x] **Phase 12: Profile Sync 與 Avatar 整合** - Profile 同步、Avatar hook、快取機制
- [x] **Phase 13: 前台整合** - 登入按鈕、綁定按鈕、Shortcode、樣式系統
- [x] **Phase 14: Webhook 系統** - Webhook API、WebhookHandler、LINE Console 驗證
- [x] **Phase 15: LINE Login 系統** - StateManager、LoginService、UserService、設定頁面整合

---

## 已完成功能

### Webhook 系統 ✅
- LINE Webhook 接收端點 (`/wp-json/buygo-line-notify/v1/webhook`)
- x-line-signature 簽名驗證
- webhookEventId 事件去重
- WordPress Hooks 供其他外掛整合

### LINE Login 系統 ✅
- OAuth 2.0 完整流程（Nextend 架構）
- 標準 WordPress URL 機制 (`wp-login.php?loginSocial=buygo-line`)
- Register Flow Page + Shortcode
- 新用戶註冊 / Auto-link / 已登入綁定
- Profile Sync（name、email、avatar）
- Avatar 整合（get_avatar_url filter）

### 前台整合 ✅
- wp-login.php LINE 登入按鈕
- `[buygo_line_login]` shortcode
- 帳號綁定狀態顯示
- LINE 官方設計規範按鈕樣式

### 後台管理 ✅
- LINE API 設定頁面
- Webhook URL / Callback URL 顯示
- 設定加密儲存

---

## 未來規劃 (v0.3)

以下功能延後到 v0.3，視需求開始：

- **通用通知系統** - Facade API、WooCommerce/FluentCart 整合
- **LIFF 整合** - LINE 內建瀏覽器無縫登入（如有需要）

使用 `/gsd:new-milestone` 開始新的開發週期。

---

## Progress Summary

| Milestone | Status | Completed |
|-----------|--------|-----------|
| v0.1 基礎架構 | ✅ Complete | 2026-01-28 |
| v0.2 LINE Login 重構 | ✅ Complete | 2026-01-31 |
| v0.3 進階功能 | 📋 Planned | - |

---

*Last Updated: 2026-01-31*

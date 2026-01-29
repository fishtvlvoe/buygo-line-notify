---
phase: 12-profile-sync-與-avatar-整合
plan: 03
subsystem: auth
tags: [profile-sync, user-service, login-handler, integration]

# Dependency graph
requires:
  - phase: 12-01
    provides: ProfileSyncService 核心服務類別
  - phase: 11-01
    provides: UserService 和 Login_Handler 基礎流程
provides:
  - UserService 整合 ProfileSyncService（create_user_from_line 和 bind_line_to_user）
  - Login_Handler 整合 ProfileSyncService（perform_login 和 handle_link_submission）
  - 完整的 Profile Sync 觸發機制（register/login/link 三種場景）
affects: [phase-13-frontend, phase-14-backend]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "ProfileSyncService 在三個流程點觸發（register/login/link）"
    - "login 流程從 state_data 取得 line_profile，perform_login 負責同步"
    - "link 流程在 UserService 和 Login_Handler 都呼叫 syncProfile"

key-files:
  created: []
  modified:
    - includes/services/class-user-service.php
    - includes/handlers/class-login-handler.php

key-decisions:
  - "create_user_from_line() 在建立用戶後立即呼叫 syncProfile('register')"
  - "bind_line_to_user() 在儲存 user_meta 後呼叫 syncProfile('link')"
  - "perform_login() 從 state_data['line_profile'] 取得 profile 資料並呼叫 syncProfile('login')"
  - "handle_link_submission() 在 linkUser 成功後呼叫 syncProfile('link')"
  - "使用 Services\ProfileSyncService 命名空間在 Login_Handler 中"

patterns-established:
  - "Profile Sync 在用戶操作完成後才觸發，確保主要流程不被阻塞"
  - "所有 syncProfile 呼叫都使用 ?? '' 防止 undefined index"
  - "login 流程檢查 line_profile 非空才執行同步"

# Metrics
duration: 1.5min
completed: 2026-01-29
---

# Phase 12 Plan 03: 整合 ProfileSyncService 到用戶流程

**將 ProfileSyncService 整合到 UserService 和 Login_Handler，確保 Profile Sync 在註冊、登入、綁定時正確觸發**

## Performance

- **Duration:** 1.5 min
- **Started:** 2026-01-29T09:31:01Z
- **Completed:** 2026-01-29T09:32:32Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments

- UserService 整合 ProfileSyncService：create_user_from_line() 使用 'register'，bind_line_to_user() 使用 'link'
- Login_Handler 整合 ProfileSyncService：perform_login() 使用 'login'，handle_link_submission() 使用 'link'
- 完成 SYNC-01（註冊時同步）、SYNC-02（登入時同步）、SYNC-03（綁定時同步）需求
- 所有程式碼語法檢查通過

## Task Commits

Each task was committed atomically:

1. **Task 1: 整合 ProfileSyncService 到 UserService** - `f672490` (feat)
   - create_user_from_line() 呼叫 syncProfile('register')
   - bind_line_to_user() 呼叫 syncProfile('link')

2. **Task 2: 整合 ProfileSyncService 到 Login_Handler** - `7718a44` (feat)
   - perform_login() 呼叫 syncProfile('login')
   - handle_link_submission() 呼叫 syncProfile('link')

## Files Created/Modified

### Modified

- `includes/services/class-user-service.php` - 新增 ProfileSyncService 呼叫
  - Line 106-110: create_user_from_line() 中的 syncProfile('register')
  - Line 174-178: bind_line_to_user() 中的 syncProfile('link')

- `includes/handlers/class-login-handler.php` - 新增 ProfileSyncService 呼叫
  - Line 403-413: perform_login() 中的 syncProfile('login')
  - Line 960-969: handle_link_submission() 中的 syncProfile('link')

## Decisions Made

1. **create_user_from_line() 在 wp_update_user 和儲存 profile picture 之後呼叫 syncProfile**
   - 理由：確保基本用戶資料和 LINE profile 都已儲存再進行同步
   - 實作：在 line 103（儲存 pictureUrl）和 line 112（綁定 LINE UID）之間呼叫

2. **bind_line_to_user() 在儲存 user_meta 之後、bindings 表之前呼叫 syncProfile**
   - 理由：確保 user_meta 已有 LINE 資料再進行同步
   - 實作：在 line 165（儲存 pictureUrl）和 line 167（LineUserService::bind_line_account）之間呼叫

3. **perform_login() 從 state_data['line_profile'] 取得 profile**
   - 理由：state_data 中已包含 LINE profile，無需重新查詢
   - 實作：檢查 line_profile 非空才執行同步，避免不必要的呼叫

4. **handle_link_submission() 在 linkUser 成功後、觸發 hook 之前呼叫 syncProfile**
   - 理由：確保綁定已完成再同步，且在 hook 觸發前完成所有內部操作
   - 實作：在 line 952（linkUser）和 line 972（do_action）之間呼叫

5. **使用 Services\ProfileSyncService 命名空間在 Login_Handler**
   - 理由：Login_Handler 已使用 Services 命名空間（LoginService, LineUserService 等）
   - 實作：保持一致性，使用 Services\ProfileSyncService::syncProfile()

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## Next Phase Readiness

**已就緒：**
- Profile Sync 在所有流程中正確觸發
- SYNC-01（註冊時同步）：create_user_from_line() ✅
- SYNC-02（登入時同步）：perform_login() ✅
- SYNC-03（綁定時同步）：bind_line_to_user() 和 handle_link_submission() ✅
- 所有程式碼語法正確，PHP lint 通過

**待完成（Phase 12 後續計畫）：**
- 12-04（如有）：後台 UI 整合（顯示同步日誌、衝突日誌）
- Phase 13：前台整合（LINE 登入按鈕、綁定狀態顯示）
- Phase 14：後台管理（Profile Sync 設定頁面）

**無阻礙或疑慮。**

---
*Phase: 12-profile-sync-與-avatar-整合*
*Completed: 2026-01-29*

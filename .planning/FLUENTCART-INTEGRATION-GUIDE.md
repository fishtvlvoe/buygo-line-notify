# FluentCart LINE 整合指南

## 概述

本外掛提供完整的 REST API endpoints，讓 FluentCart 客戶檔案頁面可以顯示 LINE 綁定狀態並管理綁定。

## REST API Endpoints

### 1. 取得 LINE 綁定狀態

**Endpoint**: `GET /wp-json/buygo-line-notify/v1/fluentcart/binding-status`

**權限**: 必須登入

**回應範例（未綁定）**:
```json
{
  "success": true,
  "is_linked": false,
  "message": "未綁定 LINE"
}
```

**回應範例（已綁定）**:
```json
{
  "success": true,
  "is_linked": true,
  "line_uid": "U1234567890abcdef",
  "display_name": "魚魚",
  "avatar_url": "https://profile.line-scdn.net/...",
  "linked_at": "2026-01-29 12:34:56"
}
```

---

### 2. 產生綁定 LINE 的授權 URL

**Endpoint**: `GET /wp-json/buygo-line-notify/v1/fluentcart/bind-url`

**權限**: 必須登入

**參數**:
- `redirect_url` (可選): 綁定完成後導向的 URL（預設為 `/my-account/`）

**範例請求**:
```
GET /wp-json/buygo-line-notify/v1/fluentcart/bind-url?redirect_url=https://test.buygo.me/my-account/
```

**回應範例**:
```json
{
  "success": true,
  "authorize_url": "https://access.line.me/oauth2/v2.1/authorize?..."
}
```

---

### 3. 解除 LINE 綁定

**Endpoint**: `POST /wp-json/buygo-line-notify/v1/fluentcart/unbind`

**權限**: 必須登入

**回應範例**:
```json
{
  "success": true,
  "message": "已成功解除 LINE 綁定"
}
```

---

## Vue 元件整合

### 方法 1：使用預製的 Vue 元件

```javascript
// 在 FluentCart Vue 應用中
import { LineBindingStatus } from './path/to/fluentcart-line-integration.js';

export default {
  components: {
    LineBindingStatus
  },

  template: `
    <div class="customer-profile">
      <!-- 其他客戶資料 -->

      <!-- LINE 綁定狀態 -->
      <LineBindingStatus />
    </div>
  `
}
```

### 方法 2：使用純 JavaScript

```javascript
import { renderLineBindingWidget } from './path/to/fluentcart-line-integration.js';

// 在頁面載入時渲染
document.addEventListener('DOMContentLoaded', () => {
  renderLineBindingWidget('#line-binding-container');
});
```

### 方法 3：自訂 Vue 元件

```vue
<template>
  <div class="line-binding">
    <div v-if="loading">載入中...</div>

    <div v-else-if="!isLinked">
      <button @click="bindLine">綁定 LINE 帳號</button>
    </div>

    <div v-else>
      <img :src="lineData.avatarUrl" alt="LINE Avatar" />
      <p>{{ lineData.displayName }}</p>
      <button @click="unbindLine">解除綁定</button>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      loading: true,
      isLinked: false,
      lineData: null
    }
  },

  mounted() {
    this.fetchBindingStatus();
  },

  methods: {
    async fetchBindingStatus() {
      const response = await fetch('/wp-json/buygo-line-notify/v1/fluentcart/binding-status', {
        credentials: 'same-origin'
      });
      const data = await response.json();

      if (data.success) {
        this.isLinked = data.is_linked;
        if (data.is_linked) {
          this.lineData = {
            lineUid: data.line_uid,
            displayName: data.display_name,
            avatarUrl: data.avatar_url,
            linkedAt: data.linked_at
          };
        }
      }
      this.loading = false;
    },

    async bindLine() {
      const redirectUrl = window.location.href;
      const response = await fetch(
        `/wp-json/buygo-line-notify/v1/fluentcart/bind-url?redirect_url=${encodeURIComponent(redirectUrl)}`,
        { credentials: 'same-origin' }
      );
      const data = await response.json();

      if (data.success && data.authorize_url) {
        window.location.href = data.authorize_url;
      }
    },

    async unbindLine() {
      if (!confirm('確定要解除 LINE 綁定嗎？')) return;

      const response = await fetch('/wp-json/buygo-line-notify/v1/fluentcart/unbind', {
        method: 'POST',
        credentials: 'same-origin'
      });
      const data = await response.json();

      if (data.success) {
        this.isLinked = false;
        this.lineData = null;
        alert('已成功解除 LINE 綁定');
      }
    }
  }
}
</script>
```

---

## FluentCart 整合步驟

### 步驟 1: 找到客戶檔案 Vue 元件

在 FluentCart 原始碼中找到客戶檔案頁面的 Vue 元件，通常位於：
```
resources/public/customer-profile/
```

### 步驟 2: 加入 LINE 綁定區塊

在客戶檔案元件的模板中加入 LINE 綁定狀態區塊：

```vue
<template>
  <div class="customer-profile">
    <!-- 現有的客戶資料區塊 -->
    <div class="profile-section">
      <h3>個人資料</h3>
      <!-- ... -->
    </div>

    <!-- 新增：LINE 綁定區塊 -->
    <div class="profile-section">
      <LineBindingStatus />
    </div>
  </div>
</template>
```

### 步驟 3: 註冊元件

```javascript
import { LineBindingStatus } from '@/path/to/fluentcart-line-integration.js';

export default {
  name: 'CustomerProfile',
  components: {
    LineBindingStatus
  },
  // ...
}
```

### 步驟 4: 加入樣式（可選）

```scss
.line-binding-status {
  padding: 20px;
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  background: #f9f9f9;

  h3 {
    margin-bottom: 12px;
    color: #333;
  }

  .line-profile {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 16px;

    .line-avatar {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      object-fit: cover;
    }

    .line-info {
      flex: 1;

      p {
        margin: 4px 0;
      }

      .line-name {
        font-size: 16px;
        font-weight: bold;
      }

      .line-uid {
        font-size: 12px;
        color: #666;
      }

      .line-date {
        font-size: 12px;
        color: #999;
      }
    }
  }

  .btn {
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;

    &.btn-primary {
      background: #06C755;
      color: white;

      &:hover {
        background: #05b34a;
      }
    }

    &.btn-danger {
      background: #dc3545;
      color: white;

      &:hover {
        background: #c82333;
      }
    }

    &:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }
  }
}
```

---

## 測試

### 測試 API Endpoints

使用瀏覽器開發者工具或 Postman 測試：

1. **測試取得綁定狀態**:
   ```
   GET https://test.buygo.me/wp-json/buygo-line-notify/v1/fluentcart/binding-status
   ```

2. **測試取得授權 URL**:
   ```
   GET https://test.buygo.me/wp-json/buygo-line-notify/v1/fluentcart/bind-url?redirect_url=/my-account/
   ```

3. **測試解除綁定**:
   ```
   POST https://test.buygo.me/wp-json/buygo-line-notify/v1/fluentcart/unbind
   ```

### 測試 Vue 元件

1. 登入 FluentCart 客戶檔案頁面
2. 檢查 LINE 綁定狀態區塊是否顯示
3. 測試綁定流程
4. 測試解除綁定流程

---

## 注意事項

1. **權限檢查**: 所有 API endpoints 都要求用戶已登入
2. **CORS**: API 使用 `credentials: 'same-origin'`，確保 Cookie 正確傳遞
3. **錯誤處理**: 建議在前端實作完整的錯誤處理和使用者提示
4. **載入狀態**: 顯示載入中狀態，避免使用者重複點擊

---

## 檔案位置

- **API**: `includes/api/class-fluentcart-integration-api.php`
- **JavaScript**: `assets/js/fluentcart-line-integration.js`
- **整合指南**: `.planning/FLUENTCART-INTEGRATION-GUIDE.md`

---

## 支援

如有問題或需要協助，請參考：
- REST API 文件
- Vue 元件範例
- 或聯繫開發團隊

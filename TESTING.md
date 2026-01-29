# 測試指南（Buygo Line Notify）

▋ 快速開始

進入外掛目錄後執行：

```bash
composer install

composer test
```


▋ 常用命令

跑所有單元測試：

```bash
composer test
```

跑特定測試：

```bash
composer test -- --filter "SampleServiceTest"
```

產出覆蓋率（輸出到 `coverage/`）：

```bash
composer test:coverage
```


▋ 目錄結構（摘要）

`includes/` 放你的實作程式碼。

`tests/` 放你的單元測試。


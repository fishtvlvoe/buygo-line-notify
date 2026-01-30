#!/bin/bash

# GitHub CLI 快速登入腳本

echo "🔐 GitHub CLI 授權設定"
echo ""
echo "請選擇登入方式："
echo "1) 使用瀏覽器登入（推薦 - 最簡單）"
echo "2) 使用 Personal Access Token"
echo ""
read -p "請選擇 (1 或 2): " choice

case $choice in
    1)
        echo ""
        echo "正在開啟瀏覽器進行登入..."
        gh auth login -h github.com -p https -w
        ;;
    2)
        echo ""
        echo "請前往 https://github.com/settings/tokens 建立 Personal Access Token"
        echo "需要的權限: repo (完整存取)"
        echo ""
        read -p "請貼上你的 Token: " token
        echo "$token" | gh auth login -h github.com -p https --with-token
        ;;
    *)
        echo "無效的選擇"
        exit 1
        ;;
esac

# 驗證登入
if gh auth status >/dev/null 2>&1; then
    echo ""
    echo "✅ 登入成功！"
    echo ""
    echo "現在你可以："
    echo "1. 執行 ./release.sh 發布新版本"
    echo "2. 或直接跟 Claude 說「發布新版本」"
else
    echo ""
    echo "❌ 登入失敗，請重試"
fi

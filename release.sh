#!/bin/bash

# BuyGo Line Notify 自動發布腳本
# 功能：自動更新版本號、建立 Git tag、發布到 GitHub Releases

set -e  # 遇到錯誤立即停止

# 顏色輸出
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 設定
PLUGIN_FILE="buygo-line-notify.php"
GITHUB_REPO="fishtvlvoe/buygo-line-notify"
PLUGIN_SLUG="buygo-line-notify"

# 檢查必要工具
command -v gh >/dev/null 2>&1 || {
    echo -e "${RED}錯誤: 需要安裝 GitHub CLI (gh)${NC}"
    echo "安裝方式: brew install gh"
    exit 1
}

# 檢查是否已登入 GitHub CLI
if ! gh auth status >/dev/null 2>&1; then
    echo -e "${RED}錯誤: 請先登入 GitHub CLI${NC}"
    echo "執行: gh auth login"
    exit 1
fi

# 取得目前版本
CURRENT_VERSION=$(grep "Version:" $PLUGIN_FILE | head -1 | awk '{print $3}')
echo -e "${BLUE}目前版本: $CURRENT_VERSION${NC}"

# 詢問版本類型
echo ""
echo "選擇版本更新類型："
echo "1) Patch (修訂號 +1) - 修 bug、小修正"
echo "2) Minor (次版本號 +1) - 新增功能"
echo "3) Major (主版本號 +1) - 重大變更"
echo "4) 自訂版本號"
read -p "請選擇 (1-4): " VERSION_TYPE

# 計算新版本號
case $VERSION_TYPE in
    1)
        NEW_VERSION=$(echo $CURRENT_VERSION | awk -F. '{printf "%d.%d.%d", $1, $2, $3+1}')
        ;;
    2)
        NEW_VERSION=$(echo $CURRENT_VERSION | awk -F. '{printf "%d.%d.0", $1, $2+1}')
        ;;
    3)
        NEW_VERSION=$(echo $CURRENT_VERSION | awk -F. '{printf "%d.0.0", $1+1}')
        ;;
    4)
        read -p "請輸入新版本號 (例如: 1.0.0): " NEW_VERSION
        ;;
    *)
        echo -e "${RED}無效的選擇${NC}"
        exit 1
        ;;
esac

echo -e "${GREEN}新版本: $NEW_VERSION${NC}"
echo ""

# 檢查 Git 狀態
if [[ -n $(git status -s) ]]; then
    echo -e "${YELLOW}警告: 工作目錄有未提交的變更${NC}"
    git status -s
    echo ""
    read -p "是否要先提交這些變更？(y/n) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        git add .
        read -p "請輸入 commit 訊息: " COMMIT_MSG
        git commit -m "$COMMIT_MSG"
    else
        echo -e "${RED}請先處理未提交的變更${NC}"
        exit 1
    fi
fi

# 更新版本號
echo ""
echo -e "${BLUE}更新版本號...${NC}"

# 更新主檔案
sed -i '' "s/Version: $CURRENT_VERSION/Version: $NEW_VERSION/" $PLUGIN_FILE
sed -i '' "s/BuygoLineNotify_PLUGIN_VERSION', '$CURRENT_VERSION/BuygoLineNotify_PLUGIN_VERSION', '$NEW_VERSION/" $PLUGIN_FILE

# 更新 README.md (如果存在)
if [ -f "README.md" ]; then
    sed -i '' "s/Version: $CURRENT_VERSION/Version: $NEW_VERSION/g" README.md
fi

echo -e "${GREEN}版本號已更新${NC}"

# Commit 版本更新
echo ""
echo -e "${BLUE}提交版本更新...${NC}"
git add .
git commit -m "chore: bump version to $NEW_VERSION"

# 建立並推送 tag
echo ""
echo -e "${BLUE}建立並推送 tag...${NC}"
git tag -a "v$NEW_VERSION" -m "Release version $NEW_VERSION"
git push origin main
git push origin "v$NEW_VERSION"

echo -e "${GREEN}Tag v$NEW_VERSION 已推送${NC}"

# 建立 Release Notes
echo ""
read -p "請輸入 Release Notes（可以多行，輸入 'END' 結束）: " -r
RELEASE_NOTES=""
while IFS= read -r line; do
    if [[ "$line" == "END" ]]; then
        break
    fi
    RELEASE_NOTES="${RELEASE_NOTES}${line}\n"
done

# 如果沒有輸入 Release Notes，使用預設訊息
if [ -z "$RELEASE_NOTES" ]; then
    RELEASE_NOTES="Release version $NEW_VERSION\n\n請查看 commit 歷史以了解更新內容。"
fi

# 建立 GitHub Release
echo ""
echo -e "${BLUE}建立 GitHub Release...${NC}"

# 打包外掛（排除不必要的檔案）
ZIP_FILE="${PLUGIN_SLUG}-${NEW_VERSION}.zip"
echo -e "${BLUE}打包外掛: $ZIP_FILE${NC}"

# 建立暫存目錄
TMP_DIR=$(mktemp -d)
PLUGIN_DIR="$TMP_DIR/$PLUGIN_SLUG"
mkdir -p "$PLUGIN_DIR"

# 複製檔案（排除開發用檔案）
rsync -av \
    --exclude='.git*' \
    --exclude='node_modules' \
    --exclude='vendor' \
    --exclude='tests' \
    --exclude='.phpunit.result.cache' \
    --exclude='composer.lock' \
    --exclude='phpunit.xml' \
    --exclude='*.sh' \
    --exclude='*.md' \
    --exclude='.planning' \
    --exclude='coverage' \
    ./ "$PLUGIN_DIR/"

# 建立 ZIP 檔案
cd "$TMP_DIR"
zip -r "$ZIP_FILE" "$PLUGIN_SLUG" > /dev/null
cd - > /dev/null

# 移動 ZIP 到目前目錄
mv "$TMP_DIR/$ZIP_FILE" ./

# 清理暫存目錄
rm -rf "$TMP_DIR"

echo -e "${GREEN}外掛已打包: $ZIP_FILE${NC}"

# 使用 gh 建立 release
echo -e "$RELEASE_NOTES" | gh release create "v$NEW_VERSION" \
    --title "Version $NEW_VERSION" \
    --notes-file - \
    "$ZIP_FILE"

# 清理 ZIP 檔案
rm "$ZIP_FILE"

echo ""
echo -e "${GREEN}✅ 發布完成！${NC}"
echo -e "${BLUE}版本: $NEW_VERSION${NC}"
echo -e "${BLUE}Tag: v$NEW_VERSION${NC}"
echo -e "${BLUE}GitHub Release: https://github.com/$GITHUB_REPO/releases/tag/v$NEW_VERSION${NC}"
echo ""
echo -e "${YELLOW}提醒: WordPress 站點會在 12 小時內自動檢測到更新${NC}"
echo -e "${YELLOW}或在外掛頁面加上 ?clear_update_cache=1 立即檢查更新${NC}"

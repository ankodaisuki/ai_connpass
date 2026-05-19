#!/bin/bash
# Claude Code Skills のセットアップスクリプト
# 用途: skills/ ディレクトリを .claude/skills/ にコピーして、Claude Code で自動読み込みする
# 実行方法: bash setup-skills.sh

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SKILLS_SOURCE="$SCRIPT_DIR/skills"
SKILLS_DEST="$SCRIPT_DIR/.claude/skills"

# 色定義
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${BLUE}=====================================${NC}"
echo -e "${BLUE}Claude Code Skills セットアップ${NC}"
echo -e "${BLUE}=====================================${NC}"
echo ""

# チェック: skills/ が存在するか
if [ ! -d "$SKILLS_SOURCE" ]; then
  echo -e "${YELLOW}警告: $SKILLS_SOURCE が見つかりません${NC}"
  exit 1
fi

# チェック: .claude/ ディレクトリが存在するか
if [ ! -d "$SCRIPT_DIR/.claude" ]; then
  echo -e "${YELLOW}情報: .claude/ ディレクトリを作成します${NC}"
  mkdir -p "$SCRIPT_DIR/.claude"
fi

# .claude/skills/ をクリア（古いスキルを削除）
if [ -d "$SKILLS_DEST" ]; then
  echo "既存の .claude/skills/ をクリアしています..."
  rm -rf "$SKILLS_DEST"
fi

# skills/ を .claude/skills/ にコピー
echo "スキルをコピーしています..."
cp -r "$SKILLS_SOURCE" "$SKILLS_DEST"

# コピー結果の確認
SKILL_COUNT=$(find "$SKILLS_DEST" -name "SKILL.md" | wc -l)

echo ""
echo -e "${GREEN}✅ セットアップ完了！${NC}"
echo ""
echo "📊 セットアップされたスキル:"
find "$SKILLS_DEST" -type d -maxdepth 1 -not -name "skills" | sort | while read dir; do
  if [ -d "$dir" ]; then
    SKILL_NAME=$(basename "$dir")
    echo "   • $SKILL_NAME"
  fi
done

echo ""
echo -e "${GREEN}合計: $SKILL_COUNT個のスキル${NC}"
echo ""
echo "✨ Claude Code はこれらのスキルを自動的に読み込みます"
echo ""

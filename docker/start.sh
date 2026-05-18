#!/bin/sh
set -e

# 診断
echo "Total env vars: $(env | wc -l)"
echo "RAILWAY_ vars: $(env | grep "^RAILWAY_" | cut -d= -f1 | tr '\n' ' ' || echo '(none)')"

# APP_KEY が渡されていない場合は生成（セッションはデプロイ毎にリセットされる）
if [ -z "$APP_KEY" ]; then
    export APP_KEY=$(php artisan key:generate --show --no-ansi 2>/dev/null | tr -d '[:space:]')
    echo "WARNING: APP_KEY not provided, generated temporarily: ${APP_KEY:0:20}..."
fi

# SQLite ファイルが存在しない場合は作成して権限付与
if [ ! -f database/database.sqlite ]; then
    touch database/database.sqlite
fi
chown www-data:www-data database/database.sqlite
chmod 664 database/database.sqlite

# RAILWAY_PUBLIC_DOMAIN が提供されている場合は APP_URL を設定
if [ -z "$APP_URL" ] && [ -n "$RAILWAY_PUBLIC_DOMAIN" ]; then
    export APP_URL="https://${RAILWAY_PUBLIC_DOMAIN}"
    echo "APP_URL set from RAILWAY_PUBLIC_DOMAIN: $APP_URL"
fi

# 設定・ルート・ビューをキャッシュ
php artisan config:cache
php artisan route:cache
php artisan view:cache

# ストレージリンクを作成
php artisan storage:link --force

# マイグレーションを実行
php artisan migrate --force

# nginx + php-fpm を起動
exec /usr/bin/supervisord -c /etc/supervisord.conf

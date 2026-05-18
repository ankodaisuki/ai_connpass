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

# 設定・ルート・ビューをキャッシュ
php artisan config:cache
php artisan route:cache
php artisan view:cache

# マイグレーションを実行
php artisan migrate --force

# nginx + php-fpm を起動
exec /usr/bin/supervisord -c /etc/supervisord.conf

#!/bin/sh
set -e

# 診断
echo "Has .env file: $([ -f .env ] && echo YES || echo NO)"
echo "DB_HOST in env: $([ -n "$DB_HOST" ] && echo YES || echo NO)"
echo "APP_KEY in env: $([ -n "$APP_KEY" ] && echo YES || echo NO)"

# 設定・ルート・ビューをキャッシュ
php artisan config:cache
php artisan route:cache
php artisan view:cache

# マイグレーションを実行
php artisan migrate --force

# nginx + php-fpm を起動
exec /usr/bin/supervisord -c /etc/supervisord.conf

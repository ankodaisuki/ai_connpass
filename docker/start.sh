#!/bin/sh
set -e

# 診断: APP_ で始まる全変数名を表示（値は非表示）
echo "=== APP_ variables in container ==="
env | grep "^APP_" | cut -d= -f1 | sort || echo "(none)"
echo "==================================="

# 設定・ルート・ビューをキャッシュ
php artisan config:cache
php artisan route:cache
php artisan view:cache

# マイグレーションを実行
php artisan migrate --force

# nginx + php-fpm を起動
exec /usr/bin/supervisord -c /etc/supervisord.conf

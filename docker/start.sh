#!/bin/sh
set -e

# 診断: APP_KEY が渡されているか確認
echo "APP_KEY set: $([ -n "$APP_KEY" ] && echo YES || echo NO)"

# 設定・ルート・ビューをキャッシュ
php artisan config:cache
php artisan route:cache
php artisan view:cache

# マイグレーションを実行
php artisan migrate --force

# nginx + php-fpm を起動
exec /usr/bin/supervisord -c /etc/supervisord.conf

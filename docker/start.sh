#!/bin/sh
set -e

# APP_KEY が未設定の場合は生成（Railway で変数が注入されない場合の fallback）
if [ -z "$APP_KEY" ]; then
    export APP_KEY=$(php artisan key:generate --show --no-ansi 2>/dev/null | tr -d '[:space:]')
    echo "WARNING: APP_KEY not provided by platform, generated temporarily"
fi

# APP_URL が未設定の場合は RAILWAY_PUBLIC_DOMAIN から設定
if [ -z "$APP_URL" ] && [ -n "$RAILWAY_PUBLIC_DOMAIN" ]; then
    export APP_URL="https://${RAILWAY_PUBLIC_DOMAIN}"
fi

# SQLite ファイルの権限設定
if [ ! -f database/database.sqlite ]; then
    touch database/database.sqlite
fi
chown www-data:www-data database/database.sqlite
chmod 664 database/database.sqlite

# 永続Volume（storage/app/public）をマウントしている場合、
# Volumeはroot所有でマウントされるため www-data が書き込めるよう権限を付与する。
# （Dockerfileのchownはビルド時のため、ランタイムでマウントされるVolumeには効かない）
mkdir -p storage/app/public
chown -R www-data:www-data storage/app/public
chmod -R 775 storage/app/public

# キャッシュをクリア（config:cache は外してランタイムで直接読む）
php artisan optimize:clear

# ストレージリンクを作成
php artisan storage:link --force

# マイグレーションを実行
php artisan migrate --force

# nginx + php-fpm を起動
exec /usr/bin/supervisord -c /etc/supervisord.conf

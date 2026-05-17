# ----------------------------------------
# Stage 1: フロントエンドのビルド
# ----------------------------------------
FROM node:20-alpine AS assets

WORKDIR /app

COPY package*.json ./
RUN npm ci

COPY . .
RUN npm run build

# ----------------------------------------
# Stage 2: 本番用 PHP イメージ
# ----------------------------------------
FROM php:8.4-fpm-alpine

# システムパッケージと PHP 拡張をインストール
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    libzip-dev \
    libpng-dev \
    oniguruma-dev \
    && docker-php-ext-install \
        pdo_mysql \
        zip \
        bcmath \
        opcache \
        mbstring

# OPcache を本番向けに設定
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.max_accelerated_files=10000'; \
    echo 'opcache.revalidate_freq=0'; \
    echo 'opcache.validate_timestamps=0'; \
} > /usr/local/etc/php/conf.d/opcache.ini

# Composer をインストール
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Composer の依存関係（レイヤーキャッシュ活用）
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# アプリケーションコードをコピー
COPY . .

# Stage 1 でビルドしたアセットをコピー
COPY --from=assets /app/public/build ./public/build

# autoload を最適化
RUN composer dump-autoload --optimize

# ストレージのパーミッションを設定
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# 設定ファイルをコピー
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 80

CMD ["/start.sh"]

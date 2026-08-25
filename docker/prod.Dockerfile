FROM node:24-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM php:8.3-fpm-alpine AS php
RUN apk add --no-cache icu-dev libzip-dev $PHPIZE_DEPS \
    && docker-php-ext-install pdo_mysql intl zip opcache \
    && rm -rf /tmp/*
WORKDIR /var/www/html
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY . .
COPY --from=frontend /app/public/build ./public/build
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && chown -R www-data:www-data storage bootstrap/cache
USER www-data
CMD ["php-fpm"]

FROM nginx:1.27-alpine AS nginx
WORKDIR /var/www/html
COPY public ./public
COPY --from=frontend /app/public/build ./public/build
COPY docker/nginx/production.conf /etc/nginx/conf.d/default.conf

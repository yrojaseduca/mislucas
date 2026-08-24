FROM php:8.3-fpm-alpine
RUN apk add --no-cache icu-dev libzip-dev $PHPIZE_DEPS && docker-php-ext-install pdo_mysql intl zip
WORKDIR /var/www/html
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY . .
RUN composer install --no-interaction --prefer-dist && chown -R www-data:www-data storage bootstrap/cache
CMD ["php-fpm"]

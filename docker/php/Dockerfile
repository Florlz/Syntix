FROM php:8.4-fpm-alpine

RUN apk add --no-cache $PHPIZE_DEPS postgresql-dev libpq linux-headers \
    && docker-php-ext-install pdo_pgsql pgsql \
    && apk del $PHPIZE_DEPS postgresql-dev linux-headers

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/conf.d/opcache.ini /usr/local/etc/php/conf.d/99-syntix-opcache.ini

WORKDIR /var/www/html

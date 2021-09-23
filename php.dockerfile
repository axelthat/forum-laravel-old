
FROM php:8.0.10-alpine

ARG APP_PORT

RUN apk add --update alpine-sdk autoconf

RUN pecl install redis

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

RUN cp /usr/local/etc/php/php.ini-development /usr/local/etc/php/php.ini

RUN echo "extension=redis.so" >> /usr/local/etc/php/php.ini

WORKDIR /var/www/html/laravel-forum

CMD php artisan serve --host ${APP_HOST} --port ${APP_PORT}
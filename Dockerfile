# syntax=docker/dockerfile:1

FROM php:8.4.1-fpm-bookworm

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

COPY . .

RUN apt update \
    && apt install $PHPIZE_DEPS libzip-dev -y \
    && pecl install zip \
    && echo "extension=zip.so" >> /usr/local/etc/php/php.ini \
    && composer install \
    && ./bin/console completion bash > "$HOME/.bashrc"

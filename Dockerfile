# syntax=docker/dockerfile:1

FROM php:8.4.1-fpm-bookworm

USER www-data

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

COPY . .


RUN composer install \
    && ./bin/console completion bash > "$HOME/.bashrc"


# Development image: PHP and Composer together, so dependencies are resolved
# against the exact PHP that later runs them.
#
# Splitting the two (the composer:2 image ships PHP 8.4, php:8.3 does not)
# makes Composer write a vendor/composer/platform_check.php for the wrong
# version, and every subsequent run aborts before autoloading.
ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-cli-alpine

# The phar is architecture- and libc-independent, so it runs fine on musl.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# git for source installs, unzip because php:cli ships without ext-zip, and
# bash because Alpine otherwise only has busybox sh for `compose run shell`.
RUN apk add --no-cache git unzip bash

# 0777 so the image works whether the container runs as root or as the host
# user's uid, neither of which is known at build time.
ENV COMPOSER_HOME=/tmp/composer \
    COMPOSER_CACHE_DIR=/tmp/composer/cache \
    COMPOSER_NO_INTERACTION=1
RUN mkdir -p /tmp/composer/cache && chmod -R 0777 /tmp/composer

WORKDIR /app

# syntax=docker/dockerfile:1

FROM composer:lts AS deps
WORKDIR /app
COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/tmp/cache \
    composer install --no-dev --no-interaction

FROM php:8.5-apache AS final
RUN docker-php-ext-install pdo pdo_mysql
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
RUN mkdir -p /var/lib/flavour-town/uploads && chown -R www-data:www-data /var/lib/flavour-town
COPY --from=deps app/vendor/ /var/www/html/vendor
COPY ./src /var/www/html
COPY default-images/ /var/lib/flavour-town/uploads/
RUN chown -R www-data:www-data /var/lib/flavour-town/uploads
USER www-data
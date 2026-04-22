FROM php:8.4-fpm-bookworm

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libicu-dev default-mysql-client \
    && docker-php-ext-install pdo pdo_mysql bcmath intl zip \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && rm -rf /var/lib/apt/lists/*

RUN { \
      echo "xdebug.mode=coverage"; \
      echo "xdebug.start_with_request=no"; \
    } > /usr/local/etc/php/conf.d/xdebug.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

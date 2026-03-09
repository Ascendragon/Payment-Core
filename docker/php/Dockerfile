FROM php:8.4-fpm-alpine

# System deps
RUN apk add --no-cache \
    bash \
    git \
    unzip \
    icu-dev \
    libzip-dev \
    postgresql-dev \
    rabbitmq-c-dev \
    linux-headers \
    $PHPIZE_DEPS


# PHP extensions
RUN docker-php-ext-install \
    intl \
    zip \
    pdo \
    pdo_pgsql \
    bcmath

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# (Optional) Install amqp extension (if you want native AMQP).
# Symfony Messenger can also work via amqp lib; but this is fine in dev.
RUN pecl install amqp && docker-php-ext-enable amqp

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

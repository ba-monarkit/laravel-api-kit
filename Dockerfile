# syntax=docker/dockerfile:1

# =============================================================================
# Base: PHP-FPM with the extensions the kit needs
# =============================================================================
FROM php:8.4-fpm AS base

ARG user=laravel
ARG uid=1000

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip intl opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN useradd -G www-data,root -u $uid -d /home/$user $user \
    && mkdir -p /home/$user/.composer \
    && chown -R $user:$user /home/$user

WORKDIR /var/www

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

# =============================================================================
# Development: source is bind-mounted, dependencies installed from the host
# =============================================================================
FROM base AS dev

COPY docker/php/local.ini /usr/local/etc/php/conf.d/local.ini

USER laravel

# =============================================================================
# Production: code and optimized autoloader baked into the image
# =============================================================================
FROM base AS prod

ARG user=laravel

COPY docker/php/production.ini /usr/local/etc/php/conf.d/production.ini

# Install dependencies first so this layer is cached between code changes
COPY --chown=$user:$user composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY --chown=$user:$user . .

RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R $user:$user storage bootstrap/cache

USER $user

# =============================================================================
# Nginx: serves /public and proxies PHP to the app container
# =============================================================================
FROM nginx:1.27-alpine AS nginx

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=prod /var/www/public /var/www/public

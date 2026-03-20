# ═══════════════════════════════════════════════════════════════════
#  YL Laravel — Dockerfile
#
#  Build argument SERVER controls which composer.json is used:
#    server1 → yl/helper + yl/products
#    server2 → yl/helper + yl/posts
#    server3 → yl/helper + yl/products + yl/posts
#
#  Usage:
#    docker build --build-arg SERVER=server1 .
# ═══════════════════════════════════════════════════════════════════

FROM php:8.3-fpm

# ── System dependencies ──────────────────────────────────────────
RUN apt-get update && apt-get install -y \
    nginx \
    supervisor \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    librabbitmq-dev \
    libssl-dev \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ── PHP extensions ───────────────────────────────────────────────
RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    sockets

# AMQP extension — required for RabbitMQ queue driver
RUN pecl install amqp \
    && docker-php-ext-enable amqp

# ── Composer ─────────────────────────────────────────────────────
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# ── Application setup ────────────────────────────────────────────
WORKDIR /var/www/html

# 1. Copy full project
COPY . .

# 2. Overlay the server-specific composer.json
ARG SERVER=server3
COPY docker/${SERVER}/composer.json ./composer.json

# 3. Fix ownership so Composer runs as www-data, not root
#    (running as root causes plugin errors and warnings)
RUN chown -R www-data:www-data /var/www/html

USER www-data

# 4. Install dependencies
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --no-scripts

USER root

# ── Nginx ────────────────────────────────────────────────────────
COPY docker/nginx/default.conf /etc/nginx/sites-available/default
RUN rm -f /etc/nginx/sites-enabled/default \
    && ln -s /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default

# ── Supervisor (runs php-fpm + nginx together) ───────────────────
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# ── Entrypoint (key:generate, wait for DB, migrate, start) ───────
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# ── Permissions ──────────────────────────────────────────────────
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html/storage -type d -exec chmod 775 {} \; \
    && find /var/www/html/storage -type f -exec chmod 664 {} \; \
    && chmod -R 775 /var/www/html/bootstrap/cache

RUN mkdir -p /var/www/html/storage/logs \
    && touch /var/www/html/storage/logs/laravel.log \
    && chown -R www-data:www-data /var/www/html/storage \
    && chmod -R 775 /var/www/html/storage

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

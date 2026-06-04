FROM php:8.2-fpm

ENV DEBIAN_FRONTEND=noninteractive

# System dependencies (including Nginx)
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx git unzip curl ca-certificates \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    libzip-dev libpq-dev libicu-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd bcmath pdo_pgsql intl zip opcache

# Configure uploads and PHP limits
RUN echo "upload_max_filesize=100M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size=105M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit=256M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_file_uploads=20" >> /usr/local/etc/php/conf.d/uploads.ini

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Node.js 20
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get update && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# 1) PHP dependencies (without scripts)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# 2) JS dependencies
COPY package.json package-lock.json* ./
RUN npm ci

# 3) App code
COPY . .

# 4) Run package discovery
RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi

# 5) Build assets via Vite
RUN npm run build

# Set permissions
RUN mkdir -p storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && chown -R www-data:www-data /app

# Configure Nginx & OPcache
COPY docker/nginx.conf /etc/nginx/sites-available/default
RUN ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default
COPY docker/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Entrypoint
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 8080
ENTRYPOINT ["/entrypoint.sh"]
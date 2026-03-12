# Stage 1: Composer for PHP dependencies
FROM php:8.4-fpm-alpine AS composer-builder

WORKDIR /var/www

# Install system dependencies for composer and extensions
RUN apk add --no-cache \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    zip \
    libzip-dev \
    unzip \
    git \
    curl \
    oniguruma-dev \
    libxml2-dev \
    icu-dev \
    linux-headers

# Install PHP extensions needed for Filament and Laravel
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip intl opcache

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy composer files
COPY composer.json composer.lock ./

# Install composer dependencies (needed for Filament assets)
RUN composer install --no-interaction --no-scripts --no-autoloader --no-dev

# Stage 2: Build assets
FROM node:20-alpine AS assets-builder

WORKDIR /app

# Copy package files
COPY package*.json ./
RUN npm install

# Copy all files including the vendor directory from composer-builder 
# because Tailwind needs to scan Filament's vendor files
COPY . .
COPY --from=composer-builder /var/www/vendor ./vendor

# Build assets
RUN npm run build

# Stage 3: Final PHP environment
FROM php:8.4-fpm-alpine

# Set working directory
WORKDIR /var/www

# Install system dependencies (runtime)
RUN apk add --no-cache \
    libpng \
    libjpeg-turbo \
    freetype \
    zip \
    libzip \
    unzip \
    curl \
    oniguruma \
    libxml2 \
    icu \
    sqlite \
    git

# Copy extensions from composer-builder stage
COPY --from=composer-builder /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=composer-builder /usr/local/etc/php/conf.d /usr/local/etc/php/conf.d

# Copy existing application directory contents
COPY . .

# Copy vendor from composer-builder
COPY --from=composer-builder /var/www/vendor ./vendor

# Copy built assets from Stage 2
COPY --from=assets-builder /app/public/build ./public/build

# Final composer optimization
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer dump-autoload --optimize --no-dev

# Set permissions for Laravel
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]

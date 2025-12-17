# Use official PHP image with FPM
FROM php:8.3-cli

# Set working directory
WORKDIR /var/www/html

# Install system dependencies and build tools for Swoole
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    zip \
    unzip \
    libssl-dev \
    libcurl4-openssl-dev \
    libnghttp2-dev \
    libpcre2-dev \
    build-essential \
    autoconf \
    libc-dev \
    pkg-config \
    supervisor \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip sockets intl

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Swoole extension with required flags
RUN pecl install -D 'enable-sockets="yes" enable-openssl="yes" enable-http2="yes" enable-mysqlnd="yes" enable-swoole-json="yes" enable-swoole-curl="yes"' swoole \
    && docker-php-ext-enable swoole

# Verify Swoole installation
RUN php --ri swoole

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application files
COPY . /var/www/html

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Expose Octane port
EXPOSE 8000

# Create entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

# Default command - removed --watch as it requires fswatch and can cause crashes
CMD ["php", "artisan", "octane:start", "--server=swoole", "--host=0.0.0.0", "--port=8000"]

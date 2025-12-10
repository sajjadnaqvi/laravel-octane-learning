#!/bin/bash
set -e

echo "Starting Laravel Octane Docker Container..."

# Wait for database to be ready (using host.docker.internal)
echo "Waiting for database connection..."
until php artisan db:show 2>/dev/null; do
  echo "Database is unavailable - waiting..."
  sleep 2
done

echo "Database is ready!"

# Run migrations if needed
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    echo "Running database migrations..."
    php artisan migrate --force
fi

# Seed database if needed
if [ "${RUN_SEEDERS:-false}" = "true" ]; then
    echo "Running database seeders..."
    php artisan db:seed --force
fi

# Cache config for production
if [ "$APP_ENV" = "production" ]; then
    echo "Caching configuration..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

# Clear cache in development
if [ "$APP_ENV" = "local" ]; then
    echo "Clearing caches..."
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear
fi

# Create storage link if not exists
if [ ! -L "/var/www/html/public/storage" ]; then
    echo "Creating storage link..."
    php artisan storage:link
fi

echo "Starting Octane server..."

# Execute the main command
exec "$@"

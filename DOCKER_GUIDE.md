# Docker Setup Guide for Laravel Octane

This guide will help you set up and run Laravel Octane with Swoole in a Dockerized environment.

## 📋 Prerequisites

- Docker Desktop installed (Windows/Mac) or Docker Engine (Linux)
- Docker Compose installed
- MySQL running on your host machine
- Git for version control

## 🏗️ Architecture

The Docker setup includes:

- **Laravel Octane Container** - PHP 8.3 with Swoole extension
- **Redis Container** - For caching, sessions, queues, and broadcasting
- **Node Container** - For Vite development server (optional)

**Note:** MySQL is NOT included as it's already running on your host system.

## 🚀 Quick Start

### 1. Environment Setup

First, update your `.env` file to use the host MySQL database:

```env
# Application
APP_NAME="Laravel Octane Chat"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database (connects to host MySQL)
DB_CONNECTION=mysql
DB_HOST=host.docker.internal
DB_PORT=3306
DB_DATABASE=octane_learning
DB_USERNAME=root
DB_PASSWORD=your_password

# Redis (Docker container)
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=null

# Cache
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Broadcasting
BROADCAST_DRIVER=redis

# Octane
OCTANE_SERVER=swoole
```

### 2. Create MySQL Database

On your host system, create the database:

```bash
mysql -u root -p
CREATE DATABASE octane_learning CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;
```

### 3. Build and Start Containers

```bash
# Build the Docker images
docker-compose build

# Start all services
docker-compose up -d

# View logs
docker-compose logs -f octane
```

### 4. Install Dependencies

```bash
# Install PHP dependencies
docker-compose exec octane composer install

# Install Node dependencies (if using frontend)
docker-compose exec node npm install
```

### 5. Run Migrations

```bash
# Run migrations
docker-compose exec octane php artisan migrate

# Seed database (optional)
docker-compose exec octane php artisan db:seed
```

### 6. Generate Application Key

```bash
docker-compose exec octane php artisan key:generate
```

### 7. Access Your Application

- **Application:** http://localhost:8000
- **Vite Dev Server:** http://localhost:5173 (if running)
- **Redis:** localhost:6379

## 🛠️ Common Commands

### Container Management

```bash
# Start containers
docker-compose up -d

# Stop containers
docker-compose down

# Restart Octane server
docker-compose restart octane

# View running containers
docker ps

# View logs
docker-compose logs -f octane
docker-compose logs -f redis
```

### Artisan Commands

```bash
# Run any artisan command
docker-compose exec octane php artisan [command]

# Examples:
docker-compose exec octane php artisan migrate
docker-compose exec octane php artisan make:model Message
docker-compose exec octane php artisan octane:status
docker-compose exec octane php artisan cache:clear
docker-compose exec octane php artisan config:clear
```

### Composer Commands

```bash
# Install package
docker-compose exec octane composer require package/name

# Update dependencies
docker-compose exec octane composer update

# Dump autoload
docker-compose exec octane composer dump-autoload
```

### NPM Commands

```bash
# Install packages
docker-compose exec node npm install

# Run dev server
docker-compose exec node npm run dev

# Build for production
docker-compose exec node npm run build
```

### Database Commands

```bash
# Access MySQL on host (from your system terminal)
mysql -u root -p octane_learning

# Or use a GUI tool like:
# - MySQL Workbench
# - phpMyAdmin
# - TablePlus
# - DBeaver
```

### Redis Commands

```bash
# Access Redis CLI
docker-compose exec redis redis-cli

# Monitor Redis commands
docker-compose exec redis redis-cli MONITOR

# Flush all Redis data
docker-compose exec redis redis-cli FLUSHALL
```

## 🔧 Advanced Configuration

### Octane Configuration

Edit `docker-compose.yml` to customize Octane settings:

```yaml
command: >
  php artisan octane:start 
  --server=swoole 
  --host=0.0.0.0 
  --port=8000 
  --workers=4 
  --task-workers=6 
  --max-requests=500
```

### Environment Variables

You can override environment variables in `docker-compose.yml`:

```yaml
environment:
  - APP_ENV=production
  - APP_DEBUG=false
  - OCTANE_WORKERS=8
  - RUN_MIGRATIONS=true
  - RUN_SEEDERS=false
```

### Volume Mounts

The setup mounts these volumes:

- `.` → `/var/www/html` - Entire application (for development)
- `./storage` → `/var/www/html/storage` - Persistent storage

### Custom PHP Configuration

Edit `docker/php/php.ini` to customize PHP settings, then rebuild:

```bash
docker-compose build octane
docker-compose up -d
```

## 🐛 Troubleshooting

### Can't Connect to Host MySQL

**Problem:** Container can't connect to MySQL on host.

**Solution:**
1. Ensure MySQL is listening on all interfaces:
   ```ini
   # my.ini or my.cnf
   bind-address = 0.0.0.0
   ```
2. Restart MySQL service
3. Use `host.docker.internal` in `.env` as `DB_HOST`

### Permission Denied Errors

**Problem:** Storage or cache permission errors.

**Solution:**
```bash
# Fix permissions
docker-compose exec octane chmod -R 775 storage bootstrap/cache
docker-compose exec octane chown -R www-data:www-data storage bootstrap/cache
```

### Swoole Extension Not Found

**Problem:** Swoole not installed properly.

**Solution:**
```bash
# Rebuild the image
docker-compose build --no-cache octane
docker-compose up -d
```

### Port Already in Use

**Problem:** Port 8000 or 6379 already in use.

**Solution:** Change ports in `docker-compose.yml`:
```yaml
ports:
  - "8001:8000"  # Use port 8001 instead
```

### Hot Reload Not Working

**Problem:** Code changes not reflected.

**Solution:**
```bash
# Restart Octane with watch mode
docker-compose exec octane php artisan octane:reload

# Or enable watch mode in Dockerfile CMD:
CMD ["php", "artisan", "octane:start", "--watch"]
```

## 📊 Monitoring

### Check Octane Status

```bash
docker-compose exec octane php artisan octane:status
```

### View Metrics

```bash
# Install Laravel Pulse
docker-compose exec octane composer require laravel/pulse

# Access at http://localhost:8000/pulse
```

### Container Resource Usage

```bash
# View resource usage
docker stats

# View specific container
docker stats laravel_octane
```

## 🚀 Production Deployment

### Build Production Image

```dockerfile
# Use multi-stage build for smaller image
FROM php:8.3-cli as production

# ... (same as Dockerfile but with production optimizations)

# Install dependencies without dev packages
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Cache Laravel config
RUN php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache
```

### Production Docker Compose

```yaml
# docker-compose.prod.yml
version: '3.8'

services:
  octane:
    build:
      context: .
      target: production
    restart: always
    environment:
      - APP_ENV=production
      - APP_DEBUG=false
    # ... rest of config
```

### Deploy

```bash
# Build production image
docker-compose -f docker-compose.prod.yml build

# Start in production mode
docker-compose -f docker-compose.prod.yml up -d

# Scale workers
docker-compose -f docker-compose.prod.yml up -d --scale octane=3
```

## 🔐 Security Considerations

1. **Never commit `.env` file**
2. **Use secrets management** for production credentials
3. **Run as non-root user** (already configured as www-data)
4. **Keep images updated**: `docker-compose pull && docker-compose up -d`
5. **Scan for vulnerabilities**: `docker scan laravel_octane`

## 📚 Additional Resources

- [Laravel Octane Documentation](https://laravel.com/docs/octane)
- [Swoole Documentation](https://www.swoole.co.uk/)
- [Docker Documentation](https://docs.docker.com/)
- [Docker Compose Documentation](https://docs.docker.com/compose/)

## 🎯 Next Steps

1. ✅ Start containers and verify everything works
2. ✅ Run migrations and seed data
3. ✅ Test Octane performance
4. ✅ Install Laravel Octane: `docker-compose exec octane composer require laravel/octane`
5. ✅ Configure broadcasting for WebSockets
6. ✅ Start building your chat application!

---

**Happy Coding! 🎉**

#!/bin/sh

echo "[start.sh] Starting Valtix services..."

# Create logs directory if it doesn't exist
mkdir -p /var/www/html/logs

# Start php-fpm in background
echo "[start.sh] Starting php-fpm..."
php-fpm -D
echo "[start.sh] php-fpm started."

# Start nginx in foreground (keeps container alive)
echo "[start.sh] Starting nginx..."
nginx -g 'daemon off;'

#!/bin/bash
set -e

echo "[start.sh] Starting Valtix services..."

# Create logs directory if it doesn't exist
mkdir -p /var/www/html/logs

# Start php-fpm in background
echo "[start.sh] Starting php-fpm..."
php-fpm -D
echo "[start.sh] php-fpm started."

# Start telegram_sender in background with proper paths
echo "[start.sh] Starting telegram_sender.php..."
cd /var/www/html
nohup php telegram_sender.php > /var/www/html/logs/telegram_sender.log 2>&1 &
echo "[start.sh] telegram_sender.php PID: $!"

# Start nginx in foreground (this keeps the container alive)
echo "[start.sh] Starting nginx..."
nginx -g 'daemon off;'

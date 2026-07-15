#!/bin/sh

echo "[start.sh] Starting Valtix services..."
echo "[start.sh] Working directory: $(pwd)"

# Create logs directory if it doesn't exist
mkdir -p /var/www/html/logs

# Start php-fpm in background
echo "[start.sh] Starting php-fpm..."
php-fpm -D
echo "[start.sh] php-fpm started."

# Give php-fpm a moment to initialize
sleep 1

# Start telegram_sender in background with full path and logging
echo "[start.sh] Starting telegram_sender.php..."
cd /var/www/html
nohup php /var/www/html/telegram_sender.php > /var/www/html/logs/telegram_sender.log 2>&1 &
TELEGRAM_PID=$!
echo "[start.sh] telegram_sender.php started with PID: ${TELEGRAM_PID}"

# Verify it's running
sleep 1
if kill -0 ${TELEGRAM_PID} 2>/dev/null; then
    echo "[start.sh] telegram_sender.php is running (PID ${TELEGRAM_PID})"
else
    echo "[start.sh] WARNING: telegram_sender.php failed to start!"
fi

# Start nginx in foreground (keeps container alive)
echo "[start.sh] Starting nginx..."
nginx -g 'daemon off;'

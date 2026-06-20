#!/bin/sh
# Start PHP-FPM in background
php-fpm -D

# Start the Telegram sender in background
php /var/www/html/telegram_sender.php &

# Start Nginx in foreground
nginx -g 'daemon off;'

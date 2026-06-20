FROM php:8.1-fpm-alpine

# Install nginx, curl, and PHP extensions
RUN apk add --no-cache nginx curl && \
    docker-php-ext-install -j$(nproc) pdo pdo_mysql openssl

# Copy nginx config
COPY nginx.conf /etc/nginx/http.d/default.conf

# Copy application files
COPY public/ /var/www/html/

# Create logs directory with proper permissions
RUN mkdir -p /var/www/html/logs /var/log/nginx /var/run && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html/logs

# Copy start script
COPY start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 80

CMD ["/start.sh"]

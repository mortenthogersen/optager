FROM php:8.4-fpm-bookworm

ENV DEBIAN_FRONTEND=noninteractive

# System dependencies + PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    ca-certificates \
    nginx \
    supervisor \
    libicu-dev \
    libzip-dev \
    libpng-dev \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        pdo_sqlite \
        mbstring \
        intl \
        bcmath \
        zip \
        gd \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# PHP config
RUN echo 'upload_max_filesize = 512M' > /usr/local/etc/php/conf.d/optager.ini \
    && echo 'post_max_size = 512M' >> /usr/local/etc/php/conf.d/optager.ini \
    && echo 'max_execution_time = 300' >> /usr/local/etc/php/conf.d/optager.ini

# Working directory
WORKDIR /var/www/html

# Copy app code
COPY . .

# Install PHP dependencies
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer install --no-dev --no-interaction --no-progress --optimize-autoloader

# Nginx config
RUN rm -f /etc/nginx/sites-enabled/default \
    && mkdir -p /etc/nginx/sites-enabled
COPY docker/nginx.conf /etc/nginx/sites-enabled/default

# Supervisor config
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Entrypoint
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Storage setup
RUN mkdir -p storage/app/recordings storage/framework/{cache,views,sessions} storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf"]

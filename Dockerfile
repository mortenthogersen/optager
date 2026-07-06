FROM php:8.4-fpm-bookworm

ENV DEBIAN_FRONTEND=noninteractive

# System dependencies + PHP extensions
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions

RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    ca-certificates \
    nginx \
    supervisor \
    && install-php-extensions \
        pdo_mysql \
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
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf

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

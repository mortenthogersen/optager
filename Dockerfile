FROM pytorch/pytorch:2.6.0-cuda12.6-cudnn9-runtime

ENV DEBIAN_FRONTEND=noninteractive
ENV PYTHONDONTWRITEBYTECODE=1
ENV PYTHONUNBUFFERED=1

# System dependencies + PHP 8.4 from ondrej PPA
RUN apt-get update && apt-get install -y --no-install-recommends \
    software-properties-common \
    curl \
    ca-certificates \
    && add-apt-repository -y ppa:ondrej/php \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        php8.4-fpm \
        php8.4-cli \
        php8.4-sqlite3 \
        php8.4-mbstring \
        php8.4-xml \
        php8.4-curl \
        php8.4-zip \
        php8.4-bcmath \
        php8.4-intl \
        php8.4-gd \
        nginx \
        supervisor \
        libgl1 \
        libgomp1 \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Python ASR dependencies
RUN pip install --no-cache-dir \
    transformers>=4.46.0 \
    librosa>=0.10.0 \
    numpy>=1.24.0

# Pre-download model during build (cached in Docker layer)
RUN python -c "from transformers import pipeline; pipeline('automatic-speech-recognition', model='CoRal-project/roest-v3-whisper-1.5b')" || true

# PHP config
RUN sed -i 's/upload_max_filesize = .*/upload_max_filesize = 512M/' /etc/php/8.4/fpm/php.ini \
    && sed -i 's/post_max_size = .*/post_max_size = 512M/' /etc/php/8.4/fpm/php.ini \
    && sed -i 's/max_execution_time = .*/max_execution_time = 300/' /etc/php/8.4/fpm/php.ini

# Working directory
WORKDIR /var/www/html

# Copy app code (vendor excluded via .dockerignore)
COPY . .

# Install PHP dependencies
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer install --no-dev --no-interaction --no-progress --optimize-autoloader

# Nginx config
COPY docker/nginx.conf /etc/nginx/sites-enabled/default
RUN rm -f /etc/nginx/sites-enabled/default.orig

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

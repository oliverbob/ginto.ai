# Ginto AI PHP Dockerfile
# PHP 8.3-FPM with all required extensions for Ginto AI
# Using Debian-based image for glibc compatibility (needed for Lightpanda browser)

FROM php:8.3-fpm

LABEL maintainer="Ginto AI <support@ginto.ai>"
LABEL description="PHP 8.3-FPM with extensions for Ginto AI"

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    bash \
    curl \
    git \
    zip \
    unzip \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libwebp-dev \
    # Docker CLI for sandbox management
    docker.io \
    # Node.js for Lightpanda web search
    nodejs \
    npm \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        mysqli \
        zip \
        intl \
        mbstring \
        gd \
        opcache \
        pcntl \
        sockets

# Install Redis extension
RUN pecl install redis \
    && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Create app directory
WORKDIR /var/www/html

# Create storage directory for logs (will be bind-mounted from host)
RUN mkdir -p /var/www/storage/logs \
    && chown -R www-data:www-data /var/www/storage

# PHP configuration
RUN echo "memory_limit = 512M" > /usr/local/etc/php/conf.d/memory.ini \
    && echo "upload_max_filesize = 100M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 100M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_execution_time = 300" > /usr/local/etc/php/conf.d/timeout.ini \
    && echo "max_input_time = 300" >> /usr/local/etc/php/conf.d/timeout.ini

# OPcache configuration for production
RUN echo "opcache.enable=1" > /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.interned_strings_buffer=16" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=10000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.save_comments=1" >> /usr/local/etc/php/conf.d/opcache.ini

# Copy entrypoint script
COPY docker/scripts/php-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 9000

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]

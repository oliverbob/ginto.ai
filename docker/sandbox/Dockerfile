# Ginto AI Sandbox Container Dockerfile
# Alpine Linux with PHP, Node.js, Python for isolated code execution
# 
# This image mirrors the LXD ginto-sandbox-image functionality for Docker environments
# Use for: macOS, Windows, Docker-only deployments

FROM alpine:3.19

LABEL maintainer="Ginto AI <support@ginto.ai>"
LABEL description="Ginto AI Sandbox - Isolated code execution environment"

# Install system dependencies
RUN apk add --no-cache \
    # Shell utilities
    bash \
    curl \
    wget \
    git \
    openssh-client \
    # Editors
    vim \
    nano \
    # Build tools
    make \
    gcc \
    g++ \
    musl-dev \
    linux-headers \
    # PHP 8.3 and extensions
    php83 \
    php83-fpm \
    php83-phar \
    php83-mbstring \
    php83-openssl \
    php83-curl \
    php83-json \
    php83-dom \
    php83-xml \
    php83-xmlwriter \
    php83-xmlreader \
    php83-simplexml \
    php83-tokenizer \
    php83-ctype \
    php83-session \
    php83-fileinfo \
    php83-iconv \
    php83-zip \
    php83-pdo \
    php83-pdo_mysql \
    php83-pdo_sqlite \
    php83-sqlite3 \
    php83-mysqli \
    php83-gd \
    php83-intl \
    php83-bcmath \
    php83-pecl-redis \
    php83-sockets \
    php83-pcntl \
    # Python 3.12
    python3 \
    py3-pip \
    py3-setuptools \
    # Node.js 20
    nodejs \
    npm \
    # Database clients
    mysql-client \
    sqlite \
    # Network utilities
    bind-tools \
    iputils \
    # Process utilities
    procps \
    htop \
    # Compression
    zip \
    unzip \
    tar \
    gzip \
    # Web server (optional for dev)
    caddy \
    # Supervisor for process management
    supervisor \
    # Docker CLI for running containers
    docker-cli

# Create symlinks for PHP
RUN ln -sf /usr/bin/php83 /usr/bin/php && \
    ln -sf /usr/sbin/php-fpm83 /usr/sbin/php-fpm

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Create sandbox user
RUN addgroup -g 1000 sandbox && \
    adduser -u 1000 -G sandbox -h /home/sandbox -s /bin/bash -D sandbox

# Create directory structure
RUN mkdir -p /home/sandbox/project && \
    mkdir -p /home/sandbox/.config && \
    mkdir -p /var/log/supervisor && \
    chown -R sandbox:sandbox /home/sandbox

# Setup Python virtual environment for user
RUN python3 -m venv /home/sandbox/.venv && \
    chown -R sandbox:sandbox /home/sandbox/.venv

# Configure PHP-FPM for sandbox
RUN mkdir -p /run/php && \
    sed -i 's/user = nobody/user = sandbox/' /etc/php83/php-fpm.d/www.conf && \
    sed -i 's/group = nobody/group = sandbox/' /etc/php83/php-fpm.d/www.conf && \
    sed -i 's/listen = 127.0.0.1:9000/listen = 0.0.0.0:9000/' /etc/php83/php-fpm.d/www.conf

# Configure Caddy for sandbox
RUN mkdir -p /etc/caddy && \
    printf '%s\n' \
    ':80 {' \
    '    root * /home/sandbox' \
    '    file_server' \
    '    php_fastcgi localhost:9000' \
    '}' > /etc/caddy/Caddyfile

# Configure supervisor
COPY supervisord.conf /etc/supervisord.conf

# Set PATH for user
RUN echo 'export PATH="/home/sandbox/.venv/bin:$PATH"' >> /home/sandbox/.bashrc && \
    echo 'cd /home/sandbox/project' >> /home/sandbox/.bashrc

# Create a simple health check script
RUN echo '#!/bin/sh\necho "OK"' > /usr/local/bin/healthcheck.sh && \
    chmod +x /usr/local/bin/healthcheck.sh

# Copy entrypoint
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Working directory
WORKDIR /home/sandbox/project

# Expose ports
EXPOSE 8080 9000

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 \
    CMD /usr/local/bin/healthcheck.sh

ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]

# Ginto AI Sandbox Container Dockerfile
# Alpine Linux with PHP, Node.js, Python for isolated code execution
# 
# This image mirrors the LXD ginto-sandbox-image functionality for Docker environments
# Use for: macOS, Windows, Docker-only deployments

FROM debian:12-slim

LABEL maintainer="Ginto AI <support@ginto.ai>"
LABEL description="Ginto AI Sandbox - Isolated code execution environment (Debian slim)"

ENV DEBIAN_FRONTEND=noninteractive

# Install core system dependencies and wkhtmltopdf + xvfb
RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates \
    curl \
    wget \
    git \
    openssh-client \
    bash \
    vim \
    nano \
    build-essential \
    python3 \
    python3-venv \
    python3-pip \
    nodejs \
    npm \
    php-cli \
    php-fpm \
    php-curl \
    php-mbstring \
    php-xml \
    php-zip \
    php-sqlite3 \
    wkhtmltopdf \
    xvfb \
    fontconfig \
    fonts-dejavu-core \
    supervisor \
    mysql-client \
    sqlite3 \
    procps \
    zip \
    unzip \
    ca-certificates \
    pandoc \
    fonts-noto \
    --no-install-recommends && rm -rf /var/lib/apt/lists/*

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Create sandbox user
RUN groupadd -g 1000 sandbox || true && \
    useradd -m -u 1000 -g sandbox -s /bin/bash sandbox || true

# Create directory structure and set ownership
RUN mkdir -p /home/sandbox/project /var/log/supervisor && \
    chown -R sandbox:sandbox /home/sandbox

# Setup Python virtual environment for user
RUN python3 -m venv /home/sandbox/.venv && \
    chown -R sandbox:sandbox /home/sandbox/.venv

# Configure PHP-FPM pool to run as sandbox user if default pool exists
RUN if [ -f /etc/php/8.2/fpm/pool.d/www.conf ]; then \
      sed -i 's/user = www-data/user = sandbox/' /etc/php/8.2/fpm/pool.d/www.conf || true; \
      sed -i 's/group = www-data/group = sandbox/' /etc/php/8.2/fpm/pool.d/www.conf || true; \
      sed -i 's/listen = 127.0.0.1:9000/listen = 0.0.0.0:9000/' /etc/php/8.2/fpm/pool.d/www.conf || true; \
    fi || true

# Copy supervisor config and entrypoint
COPY supervisord.conf /etc/supervisord.conf
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Set PATH and default working directory for sandbox user
RUN echo 'export PATH="/home/sandbox/.venv/bin:$PATH"' >> /home/sandbox/.bashrc && \
    echo 'cd /home/sandbox/project' >> /home/sandbox/.bashrc

WORKDIR /home/sandbox/project

# Expose ports
EXPOSE 8080 9000

# Health check
RUN echo '#!/bin/sh\necho OK' > /usr/local/bin/healthcheck.sh && chmod +x /usr/local/bin/healthcheck.sh
HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 CMD /usr/local/bin/healthcheck.sh

ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]

# Ginto AI Sandbox Manager
# Lightweight PHP container for managing user sandbox containers
# Does NOT run the main app - only the sandbox manager daemon

FROM php:8.3-cli-alpine

LABEL maintainer="Ginto AI <support@ginto.ai>"
LABEL description="Sandbox Manager for Ginto AI user sandboxes"

# Install minimal dependencies
RUN apk add --no-cache \
    bash \
    curl \
    docker-cli \
    && docker-php-ext-install pdo pdo_mysql

# Create non-root user
RUN adduser -D -u 1000 ginto

WORKDIR /var/www/html

# The application code is mounted as a volume
# Only bin/sandbox_manager.php and src/ are needed

USER ginto

CMD ["php", "bin/sandbox_manager.php", "--daemon"]

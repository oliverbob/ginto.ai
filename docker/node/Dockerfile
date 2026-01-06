# Ginto AI Node.js Dockerfile
# Node.js 20 for sandbox-proxy and terminal-server

FROM node:20-alpine

LABEL maintainer="Ginto AI <support@ginto.ai>"
LABEL description="Node.js services for Ginto AI (sandbox-proxy, terminal-server)"

# Install system dependencies for node-pty and Docker CLI
RUN apk add --no-cache \
    python3 \
    make \
    g++ \
    bash \
    curl \
    docker-cli

# Create app directory
WORKDIR /app

# Create non-root user
RUN addgroup -g 1001 -S nodejs \
    && adduser -S nodejs -u 1001

# Copy package files for sandbox-proxy
COPY tools/sandbox-proxy/package*.json ./sandbox-proxy/

# Copy package files for terminal-server
COPY tools/terminal-server/package*.json ./terminal-server/

# Install dependencies
RUN cd sandbox-proxy && npm install --omit=dev \
    && cd ../terminal-server && npm install --omit=dev

# Copy application code
COPY tools/sandbox-proxy/sandbox-proxy.js ./sandbox-proxy/
COPY tools/terminal-server/server.js ./terminal-server/

# Copy entrypoint script
COPY docker/scripts/node-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Set ownership
RUN chown -R nodejs:nodejs /app

USER nodejs

EXPOSE 3000
EXPOSE 3001

ENTRYPOINT ["docker-entrypoint.sh"]

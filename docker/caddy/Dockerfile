# Ginto AI Caddy Dockerfile
# Caddy 2 with PHP-FPM support

FROM caddy:2-alpine

LABEL maintainer="Ginto AI <support@silverqueen.pro>"
LABEL description="Caddy 2 web server for Ginto AI"

# Install curl for healthchecks
RUN apk add --no-cache curl

# Create directories (Caddy runs as root in Alpine, handles permissions internally)
RUN mkdir -p /srv/public \
    && mkdir -p /etc/caddy \
    && mkdir -p /data/caddy \
    && mkdir -p /config/caddy

# Copy Caddyfile
COPY docker/caddy/Caddyfile /etc/caddy/Caddyfile

EXPOSE 80
EXPOSE 443

HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

CMD ["caddy", "run", "--config", "/etc/caddy/Caddyfile", "--adapter", "caddyfile"]

#!/bin/bash
# Node.js Entrypoint for Ginto AI Docker
# Starts sandbox-proxy and terminal-server services

set -e

echo "🟢 Ginto AI Node.js Container Starting..."

# Determine which service to run based on SERVICE env var
SERVICE="${SERVICE:-all}"

# Wait for Redis to be ready (needed by sandbox-proxy)
wait_for_redis() {
    echo "⏳ Waiting for Redis to be ready..."
    local max_attempts=30
    local attempt=1
    
    while [ $attempt -le $max_attempts ]; do
        if node -e "
            const redis = require('redis');
            const client = redis.createClient({
                url: 'redis://${REDIS_HOST:-redis}:${REDIS_PORT:-6379}'
            });
            client.connect().then(() => {
                console.log('connected');
                client.quit();
                process.exit(0);
            }).catch(() => process.exit(1));
        " 2>/dev/null; then
            echo "✅ Redis is ready!"
            return 0
        fi
        
        echo "   Attempt $attempt/$max_attempts - Redis not ready yet..."
        sleep 2
        attempt=$((attempt + 1))
    done
    
    echo "⚠️ Redis not available, continuing anyway..."
    return 0
}

# Start sandbox-proxy
start_sandbox_proxy() {
    echo "🔄 Starting Sandbox Proxy on port 3000..."
    cd /app/sandbox-proxy
    node sandbox-proxy.js &
    PROXY_PID=$!
    echo "✅ Sandbox Proxy started (PID: $PROXY_PID)"
}

# Start terminal-server
start_terminal_server() {
    echo "🔄 Starting Terminal Server on port 3001..."
    cd /app/terminal-server
    node server.js &
    TERMINAL_PID=$!
    echo "✅ Terminal Server started (PID: $TERMINAL_PID)"
}

# Graceful shutdown
shutdown() {
    echo "🛑 Shutting down Node.js services..."
    [ -n "$PROXY_PID" ] && kill $PROXY_PID 2>/dev/null || true
    [ -n "$TERMINAL_PID" ] && kill $TERMINAL_PID 2>/dev/null || true
    exit 0
}

trap shutdown SIGTERM SIGINT

# Main entrypoint logic
main() {
    case "$SERVICE" in
        "proxy"|"sandbox-proxy")
            wait_for_redis
            start_sandbox_proxy
            wait $PROXY_PID
            ;;
        "terminal"|"terminal-server")
            # Terminal server doesn't need Redis
            start_terminal_server
            wait $TERMINAL_PID
            ;;
        "all"|*)
            wait_for_redis
            start_sandbox_proxy
            start_terminal_server
            echo ""
            echo "🚀 Ginto AI Node.js Services Ready!"
            echo "   Sandbox Proxy: http://0.0.0.0:3000"
            echo "   Terminal Server: ws://0.0.0.0:3001"
            echo ""
            # Wait for any process to exit
            wait -n
            ;;
    esac
}

main "$@"

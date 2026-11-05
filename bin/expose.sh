#!/bin/bash
#
# Ginto Tunnel - Expose local OpenWebUI to the web
# 
# Usage: ./expose.sh [subdomain] [local_port]
#
# Examples:
#   ./expose.sh myapp 8088       # Expose port 8088 as myapp.silverqueen.pro
#   ./expose.sh                  # Interactive mode
#

set -e

# Use wss:// with path /tunnel/ws (Caddy proxies to tunnel server)
TUNNEL_SERVER="${GINTO_TUNNEL_SERVER:-wss://silverqueen.pro/tunnel/ws}"
TUNNEL_HTTP="${GINTO_TUNNEL_HTTP:-https://silverqueen.pro}"
DEFAULT_PORT=8088
AUTH_TOKEN="${GINTO_AUTH_TOKEN:-}"
MAX_RECONNECT_ATTEMPTS=5
RECONNECT_DELAY=2

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# State
RECONNECT_COUNT=0
CONNECTED=false
LAST_PONG_TIME=0

print_banner() {
    echo -e "${BLUE}"
    echo "╔═══════════════════════════════════════════╗"
    echo "║         Ginto Tunnel - Expose Local       ║"
    echo "║            Services to the Web            ║"
    echo "╚═══════════════════════════════════════════╝"
    echo -e "${NC}"
}

check_dependencies() {
    local missing=()
    
    if ! command -v websocat &> /dev/null; then
        missing+=("websocat")
    fi
    
    if ! command -v jq &> /dev/null; then
        missing+=("jq")
    fi
    
    if ! command -v curl &> /dev/null; then
        missing+=("curl")
    fi
    
    if [ ${#missing[@]} -gt 0 ]; then
        echo -e "${RED}Missing dependencies: ${missing[*]}${NC}"
        echo ""
        echo "Install them with:"
        echo ""
        if command -v apt &> /dev/null; then
            echo "  sudo apt install -y ${missing[*]}"
        elif command -v apk &> /dev/null; then
            echo "  sudo apk add ${missing[*]}"
        elif command -v brew &> /dev/null; then
            echo "  brew install ${missing[*]}"
        else
            echo "  Please install: ${missing[*]}"
        fi
        echo ""
        exit 1
    fi
}

validate_subdomain() {
    local subdomain="$1"
    if [[ ! "$subdomain" =~ ^[a-z0-9][a-z0-9-]{1,30}[a-z0-9]$ ]]; then
        echo -e "${RED}Invalid subdomain format.${NC}"
        echo "Use 3-32 lowercase letters, numbers, and hyphens."
        echo "Must start and end with a letter or number."
        return 1
    fi
    return 0
}

check_local_port() {
    local port="$1"
    if ! curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:${port}/" --connect-timeout 2 | grep -q "200\|302\|301"; then
        echo -e "${YELLOW}Warning: No service detected on port ${port}${NC}"
        echo "Make sure your service is running before exposing."
        read -p "Continue anyway? (y/n) " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            exit 1
        fi
    fi
}

# Tunnel client - handles WebSocket communication and HTTP forwarding
run_tunnel() {
    local subdomain="$1"
    local port="$2"
    
    while true; do
        echo -e "${GREEN}Connecting to tunnel server...${NC}"
        CONNECTED=false
        LAST_PONG_TIME=$(date +%s)
        
        # Create named pipes for bidirectional communication
        local pipe_in=$(mktemp -u)
        local pipe_out=$(mktemp -u)
        mkfifo "$pipe_in" "$pipe_out"
        
        # Cleanup function
        cleanup() {
            rm -f "$pipe_in" "$pipe_out"
        }
        trap cleanup EXIT INT TERM
        
        # Start WebSocket connection
        (
            # Send registration message
            local register_msg
            if [ -n "$AUTH_TOKEN" ]; then
                register_msg="{\"type\":\"register\",\"subdomain\":\"${subdomain}\",\"auth_token\":\"${AUTH_TOKEN}\"}"
            else
                register_msg="{\"type\":\"register\",\"subdomain\":\"${subdomain}\"}"
            fi
            echo "$register_msg"
            
            # Read from input pipe and send
            cat "$pipe_in"
        ) | websocat -t --ping-interval 25 --ping-timeout 60 "$TUNNEL_SERVER" 2>/dev/null | while read -r line; do
            # Handle incoming messages
            local msg_type=$(echo "$line" | jq -r '.type // empty' 2>/dev/null)
            
            case "$msg_type" in
                "registered")
                    CONNECTED=true
                    RECONNECT_COUNT=0
                    local url=$(echo "$line" | jq -r '.url')
                    local expires_in=$(echo "$line" | jq -r '.expires_in')
                    local authenticated=$(echo "$line" | jq -r '.authenticated')
                    
                    echo ""
                    echo -e "${GREEN}✓ Tunnel established!${NC}"
                    echo ""
                    echo -e "  ${BLUE}Public URL:${NC} ${url}"
                    echo -e "  ${BLUE}Local:${NC}      http://127.0.0.1:${port}/"
                    echo ""
                    
                    if [ "$authenticated" = "false" ]; then
                        local mins=$((expires_in / 60))
                        echo -e "${YELLOW}⚠ Tunnel expires in ${mins} minutes${NC}"
                        echo -e "  Register at ${BLUE}https://silverqueen.pro/register${NC} for non-expiring tunnels"
                    else
                        echo -e "${GREEN}✓ Authenticated - tunnel will not expire${NC}"
                    fi
                    
                    echo ""
                    echo "Press Ctrl+C to stop the tunnel"
                    echo ""
                    ;;
                    
                "http_request")
                # Forward HTTP request to local service
                local request_id=$(echo "$line" | jq -r '.request_id')
                local method=$(echo "$line" | jq -r '.method // "GET"')
                local uri=$(echo "$line" | jq -r '.uri // "/"')
                local body=$(echo "$line" | jq -r '.body // ""')
                local headers=$(echo "$line" | jq -r '.headers // {}')
                
                # Build curl command with headers
                local curl_args=("-s" "-w" '\n%{http_code}' "-X" "$method")
                
                # Add common headers
                local host_header=$(echo "$headers" | jq -r '.Host // ""')
                if [ -n "$host_header" ]; then
                    curl_args+=("-H" "Host: $host_header")
                fi
                
                local content_type=$(echo "$headers" | jq -r '."Content-Type" // ""')
                if [ -n "$content_type" ]; then
                    curl_args+=("-H" "Content-Type: $content_type")
                fi
                
                # Add body for non-GET requests
                if [ -n "$body" ] && [ "$method" != "GET" ]; then
                    curl_args+=("-d" "$body")
                fi
                
                curl_args+=("http://127.0.0.1:${port}${uri}")
                
                # Make request to local service
                local response
                response=$(curl "${curl_args[@]}" 2>/dev/null || echo -e "\n502")
                
                # Parse response
                local http_code=$(echo "$response" | tail -n1)
                local response_body=$(echo "$response" | sed '$d')
                
                # Send response back through tunnel
                local response_msg=$(jq -n \
                    --arg type "tunnel_response" \
                    --arg request_id "$request_id" \
                    --arg status "$http_code" \
                    --arg body "$response_body" \
                    '{type: $type, request_id: $request_id, status: ($status | tonumber), body: $body, headers: {"Content-Type": "text/html"}}')
                
                echo "$response_msg" > "$pipe_in"
                ;;
                
            "ping")
                echo '{"type":"pong"}' > "$pipe_in"
                LAST_PONG_TIME=$(date +%s)
                ;;
            
            "pong")
                LAST_PONG_TIME=$(date +%s)
                ;;
                
            "expired")
                local message=$(echo "$line" | jq -r '.message // "Tunnel expired"')
                echo ""
                echo -e "${RED}✗ ${message}${NC}"
                echo ""
                exit 0
                ;;
                
            "error")
                local error=$(echo "$line" | jq -r '.message // "Unknown error"')
                echo -e "${RED}Error: ${error}${NC}"
                ;;
        esac
    done
    
    # Connection lost - attempt reconnection
    cleanup
    
    if [ "$RECONNECT_COUNT" -lt "$MAX_RECONNECT_ATTEMPTS" ]; then
        RECONNECT_COUNT=$((RECONNECT_COUNT + 1))
        local delay=$((RECONNECT_DELAY * RECONNECT_COUNT))
        echo ""
        echo -e "${YELLOW}Connection lost. Reconnecting in ${delay}s (attempt ${RECONNECT_COUNT}/${MAX_RECONNECT_ATTEMPTS})...${NC}"
        sleep "$delay"
    else
        echo ""
        echo -e "${RED}✗ Maximum reconnection attempts reached. Exiting.${NC}"
        exit 1
    fi
    done
}

# Main
print_banner
check_dependencies

# Parse arguments
SUBDOMAIN="${1:-}"
PORT="${2:-$DEFAULT_PORT}"

# Interactive mode if no subdomain provided
if [ -z "$SUBDOMAIN" ]; then
    echo "Enter a subdomain for your tunnel:"
    echo -e "  Your URL will be: ${BLUE}[subdomain].silverqueen.pro${NC}"
    echo ""
    read -p "Subdomain: " SUBDOMAIN
    echo ""
    
    if [ -z "$SUBDOMAIN" ]; then
        # Generate random subdomain
        SUBDOMAIN="tunnel-$(cat /dev/urandom | tr -dc 'a-z0-9' | fold -w 8 | head -n 1)"
        echo -e "Using random subdomain: ${BLUE}${SUBDOMAIN}${NC}"
    fi
    
    read -p "Local port to expose [${DEFAULT_PORT}]: " input_port
    PORT="${input_port:-$DEFAULT_PORT}"
    echo ""
fi

# Validate
if ! validate_subdomain "$SUBDOMAIN"; then
    exit 1
fi

if ! [[ "$PORT" =~ ^[0-9]+$ ]] || [ "$PORT" -lt 1 ] || [ "$PORT" -gt 65535 ]; then
    echo -e "${RED}Invalid port number: ${PORT}${NC}"
    exit 1
fi

# Check local service
check_local_port "$PORT"

# Run tunnel
run_tunnel "$SUBDOMAIN" "$PORT"

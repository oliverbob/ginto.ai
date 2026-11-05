#!/usr/bin/env python3
"""
Ginto Tunnel Client - WebSocket relay for exposing local services

Usage: gtunnel_client.py <subdomain> <local_port>
Example: gtunnel_client.py myapp 8088
"""

import sys
import json
import time
import signal
import threading
import urllib.request
import urllib.error
from http.client import HTTPConnection

# WebSocket support via websockets or websocket-client
try:
    import websocket
    HAS_WEBSOCKET = True
except ImportError:
    HAS_WEBSOCKET = False

TUNNEL_SERVER = "wss://silverqueen.pro/tunnel/ws"


def forward_request(local_port: int, request_data: dict) -> dict:
    """Forward HTTP request to local service"""
    try:
        method = request_data.get('method', 'GET')
        uri = request_data.get('uri', '/')
        body = request_data.get('body', '')
        headers = request_data.get('headers', {})
        
        conn = HTTPConnection('127.0.0.1', local_port, timeout=30)
        
        # Prepare headers
        req_headers = {}
        for k, v in headers.items():
            if k.lower() not in ['host', 'connection', 'upgrade']:
                req_headers[k] = v
        
        # Make request
        conn.request(method, uri, body=body if body else None, headers=req_headers)
        response = conn.getresponse()
        
        # Read response
        response_body = response.read().decode('utf-8', errors='replace')
        response_headers = dict(response.getheaders())
        
        conn.close()
        
        return {
            'status': response.status,
            'body': response_body,
            'headers': response_headers
        }
    except Exception as e:
        return {
            'status': 502,
            'body': f'Bad Gateway: {str(e)}',
            'headers': {'Content-Type': 'text/plain'}
        }


def run_tunnel(subdomain: str, local_port: int, auth_token: str = None):
    """Run the WebSocket tunnel client"""
    
    if not HAS_WEBSOCKET:
        print("Error: websocket-client is required. Install with: pip install websocket-client")
        sys.exit(1)
    
    def on_message(ws, message):
        try:
            data = json.loads(message)
            msg_type = data.get('type')
            
            if msg_type == 'registered':
                url = data.get('url', f'https://{subdomain}.silverqueen.pro/')
                expires_in = data.get('expires_in', 600)
                authenticated = data.get('authenticated', False)
                
                print(f"""
╔═══════════════════════════════════════════════════════════════╗
║  ✓ Tunnel Active!                                              ║
╠═══════════════════════════════════════════════════════════════╣
║  Public URL:  {url:<48} ║
║  Local Port:  {local_port:<48} ║
╠═══════════════════════════════════════════════════════════════╣
║  {'Expires in ' + str(expires_in // 60) + ' minutes' if not authenticated else 'Authenticated - no expiry':<60} ║
╚═══════════════════════════════════════════════════════════════╝
""")
                sys.stdout.flush()
                
            elif msg_type == 'http_request':
                request_id = data.get('request_id')
                
                # Forward to local service
                response = forward_request(local_port, data)
                
                # Send response back
                ws.send(json.dumps({
                    'type': 'tunnel_response',
                    'request_id': request_id,
                    'status': response['status'],
                    'body': response['body'],
                    'headers': response['headers']
                }))
                
            elif msg_type == 'ping':
                ws.send(json.dumps({'type': 'pong'}))
                
            elif msg_type == 'expired':
                print(f"\n⚠ Tunnel expired: {data.get('message', '')}")
                ws.close()
                
            elif msg_type == 'error':
                print(f"\n✗ Error: {data.get('message', 'Unknown error')}")
                
        except Exception as e:
            print(f"Error handling message: {e}")
    
    def on_error(ws, error):
        print(f"WebSocket error: {error}")
    
    def on_close(ws, close_status_code, close_msg):
        print("\nTunnel disconnected.")
    
    def on_open(ws):
        # Send registration
        register_msg = {'type': 'register', 'subdomain': subdomain}
        if auth_token:
            register_msg['auth_token'] = auth_token
        ws.send(json.dumps(register_msg))
        print(f"Connecting to {TUNNEL_SERVER}...")
    
    # Setup signal handler
    def cleanup(signum, frame):
        print("\nDisconnecting...")
        sys.exit(0)
    
    signal.signal(signal.SIGINT, cleanup)
    signal.signal(signal.SIGTERM, cleanup)
    
    # Connect
    ws = websocket.WebSocketApp(
        TUNNEL_SERVER,
        on_open=on_open,
        on_message=on_message,
        on_error=on_error,
        on_close=on_close
    )
    
    ws.run_forever(ping_interval=30, ping_timeout=10)


def main():
    if len(sys.argv) < 3:
        print(f"Usage: {sys.argv[0]} <subdomain> <local_port>")
        print(f"Example: {sys.argv[0]} myapp 8088")
        sys.exit(1)
    
    subdomain = sys.argv[1]
    local_port = int(sys.argv[2])
    auth_token = sys.argv[3] if len(sys.argv) > 3 else None
    
    print(f"Starting tunnel: {subdomain}.silverqueen.pro -> localhost:{local_port}")
    run_tunnel(subdomain, local_port, auth_token)


if __name__ == '__main__':
    main()

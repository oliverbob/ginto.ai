#!/usr/bin/env python3
"""
Ginto Tunnel (gtunnel) - SirTunnel-style SSH tunnel with Caddy API

Two modes of operation:
1. SERVER MODE (run on the server with Caddy):
   gtunnel.py sub.silverqueen.pro 9001
   
2. CLIENT MODE (run from any machine, creates SSH tunnel):
   gtunnel.py --expose sub.silverqueen.pro 8080 [--server silverqueen.pro] [--user oliverbob]

Example (from client):
   gtunnel.py --expose myapp.silverqueen.pro 8080
   # This creates: ssh -tR 9001:localhost:8080 silverqueen.pro gtunnel.py myapp.silverqueen.pro 9001
"""

import sys
import os
import json
import time
import signal
import subprocess
import random
from urllib import request, error

CADDY_API = 'http://127.0.0.1:2019'
DEFAULT_SERVER = 'silverqueen.pro'
DEFAULT_USER = 'oliverbob'
REMOTE_PORT_RANGE = (10000, 19999)


def create_route(host: str, port: str) -> str:
    """Create a tunnel route in Caddy (server-side)"""
    subdomain = host.split('.')[0]
    route_id = f"gtunnel-{subdomain}-{port}"
    
    caddy_route = {
        "@id": route_id,
        "match": [{
            "host": [host],
        }],
        "handle": [{
            "handler": "reverse_proxy",
            "upstreams": [{
                "dial": f"127.0.0.1:{port}"
            }]
        }],
        "terminal": True
    }
    
    body = json.dumps(caddy_route).encode('utf-8')
    headers = {'Content-Type': 'application/json'}
    
    # Insert at position 0 (beginning) to take priority over wildcard routes
    create_url = f'{CADDY_API}/config/apps/http/servers/srv0/routes/0'
    req = request.Request(method='PUT', url=create_url, headers=headers, data=body)
    
    try:
        response = request.urlopen(req)
        if response.status < 200 or response.status >= 300:
            raise SystemExit(1)
        return route_id
    except error.HTTPError as e:
        print(f"Error creating route: {e.read().decode()}", file=sys.stderr)
        raise SystemExit(1)


def delete_route(route_id: str):
    """Remove the tunnel route from Caddy (server-side)"""
    delete_url = f'{CADDY_API}/id/{route_id}'
    req = request.Request(method='DELETE', url=delete_url)
    try:
        response = request.urlopen(req)
        if response.status < 200 or response.status >= 300:
            print(f"Warning: Could not delete route: {response.status}", file=sys.stderr)
        else:
            print(f"✓ Route {route_id} cleaned up")
    except error.HTTPError as e:
        print(f"Warning: Could not delete route: {e}", file=sys.stderr)


def server_mode(host: str, port: str) -> int:
    """Run in server mode - configure Caddy and wait"""
    print(f"Creating tunnel: https://{host}/ -> localhost:{port}")
    
    route_id = create_route(host, port)
    
    print(f"""
╔═══════════════════════════════════════════════════════════════╗
║  ✓ Tunnel Active!                                              ║
╠═══════════════════════════════════════════════════════════════╣
║  Public URL:  https://{host:<40} ║
║  Route ID:    {route_id:<47} ║
║  Local Port:  {port:<47} ║
╠═══════════════════════════════════════════════════════════════╣
║  Press Ctrl+C to disconnect                                    ║
╚═══════════════════════════════════════════════════════════════╝
""")
    
    def cleanup(signum, frame):
        print("\nDisconnecting tunnel...")
        delete_route(route_id)
        sys.exit(0)
    
    signal.signal(signal.SIGINT, cleanup)
    signal.signal(signal.SIGTERM, cleanup)
    
    try:
        while True:
            time.sleep(1)
    except KeyboardInterrupt:
        cleanup(None, None)
    
    return 0


def client_mode(host: str, local_port: str, server: str, user: str) -> int:
    """Run in client mode - create SSH tunnel to server"""
    # Pick a random remote port
    remote_port = random.randint(*REMOTE_PORT_RANGE)
    
    # Get the path to this script on the remote server
    script_name = os.path.basename(__file__)
    remote_script = f"~/silverqueen.pro/bin/{script_name}"
    
    # Build the SSH command (SirTunnel pattern)
    # ssh -tR <remote_port>:localhost:<local_port> <server> gtunnel.py <host> <remote_port>
    ssh_cmd = [
        'ssh',
        '-t',  # Force TTY for signal propagation
        '-o', 'ServerAliveInterval=30',
        '-o', 'ServerAliveCountMax=3',
        '-o', 'ExitOnForwardFailure=yes',
        f'-R{remote_port}:localhost:{local_port}',
        f'{user}@{server}',
        'python3', remote_script, host, str(remote_port)
    ]
    
    print(f"""
╔═══════════════════════════════════════════════════════════════╗
║  Ginto Tunnel - Client Mode                                    ║
╠═══════════════════════════════════════════════════════════════╣
║  Local:       localhost:{local_port:<44} ║
║  Remote:      {server}:{remote_port:<44} ║
║  Public URL:  https://{host:<40} ║
╠═══════════════════════════════════════════════════════════════╣
║  Connecting via SSH...                                         ║
╚═══════════════════════════════════════════════════════════════╝
""")
    
    try:
        # Run SSH and let it handle everything
        proc = subprocess.run(ssh_cmd)
        return proc.returncode
    except KeyboardInterrupt:
        print("\nTunnel disconnected.")
        return 0


def parse_args(argv: list[str]) -> dict:
    """Parse command line arguments"""
    args = {
        'expose': False,
        'host': None,
        'port': None,
        'server': DEFAULT_SERVER,
        'user': DEFAULT_USER,
    }
    
    i = 1
    while i < len(argv):
        arg = argv[i]
        
        if arg == '--expose' or arg == '-e':
            args['expose'] = True
        elif arg == '--server' or arg == '-s':
            i += 1
            if i < len(argv):
                args['server'] = argv[i]
        elif arg == '--user' or arg == '-u':
            i += 1
            if i < len(argv):
                args['user'] = argv[i]
        elif arg == '--help' or arg == '-h':
            print_usage(argv[0])
            sys.exit(0)
        elif args['host'] is None:
            args['host'] = arg
        elif args['port'] is None:
            args['port'] = arg
        
        i += 1
    
    return args


def print_usage(prog: str):
    print(f"""
Ginto Tunnel (gtunnel) - SirTunnel-style SSH tunnel

SERVER MODE (run on the server with Caddy):
  {prog} <subdomain.domain> <port>
  Example: {prog} myapp.silverqueen.pro 9001

CLIENT MODE (run from any machine, creates SSH tunnel):
  {prog} --expose <subdomain.domain> <local_port> [options]
  Example: {prog} --expose myapp.silverqueen.pro 8080

Options:
  --expose, -e          Run in client mode (create SSH tunnel)
  --server, -s <host>   SSH server hostname (default: {DEFAULT_SERVER})
  --user, -u <user>     SSH username (default: {DEFAULT_USER})
  --help, -h            Show this help
""")


def main(argv: list[str]) -> int:
    args = parse_args(argv)
    
    if args['host'] is None or args['port'] is None:
        print_usage(argv[0])
        return 1
    
    if args['expose']:
        return client_mode(args['host'], args['port'], args['server'], args['user'])
    else:
        return server_mode(args['host'], args['port'])


if __name__ == '__main__':
    sys.exit(main(sys.argv))
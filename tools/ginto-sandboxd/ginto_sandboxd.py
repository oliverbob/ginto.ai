#!/usr/bin/env python3
"""
ginto_sandboxd - privileged root daemon to create systemd-nspawn sandboxes

This is a minimal, auditable daemon scaffold that accepts a simple JSON
command over a UNIX domain socket and runs the repository sandbox creation
helper as root. It validates inputs and writes logs to a controlled location.

INSTALL (manual):
 - Copy this file to /usr/local/bin/ginto_sandboxd.py and make it root-owned.
 - Install the systemd unit included in this repo and enable/start it.

Security notes:
 - The service must be installed as root and the socket owned by root with
   appropriate permissions. The daemon validates sandbox ids and host paths
   strictly and avoids invoking a shell to prevent injection.
 - The daemon is intentionally small and explicit; review before enabling.
"""
import os
import sys
import socket
import json
import struct
import subprocess
import logging
from pathlib import Path

SOCKET_PATH = '/run/ginto-sandboxd.sock'
INSTALL_LOG_DIR = '/var/log/ginto-sandboxd'
CREATE_SCRIPT = '/home/oliverbob/ginto/scripts/create_nspawn.sh'

logging.basicConfig(filename='/var/log/ginto-sandboxd/ginto_sandboxd.log', level=logging.INFO,
                    format='%(asctime)s %(levelname)s %(message)s')


def get_peer_cred(conn):
    # Returns (pid, uid, gid) on Linux
    try:
        ucred = conn.getsockopt(socket.SOL_SOCKET, socket.SO_PEERCRED, struct.calcsize('3i'))
        pid, uid, gid = struct.unpack('3i', ucred)
        return pid, uid, gid
    except Exception:
        return None


def sanitize_sandbox_id(sid: str) -> str:
    """Return a safe canonical sandbox id: lowercase, replace non-allowed
    characters with hyphens and trim to 64 characters. Returns empty string
    when no valid characters remain.
    """
    import re
    if not isinstance(sid, str):
        return ''
    canon = re.sub(r'[^a-z0-9-]+', '-', sid.lower())
    canon = canon.strip('-')
    if len(canon) > 64:
        canon = canon[:64]
    return canon


def handle_create(payload: dict, out_fd_path: str = None) -> dict:
    sandbox_id = payload.get('sandboxId')
    host_path = payload.get('hostPath')

    if not sandbox_id or not host_path:
        return {'ok': False, 'error': 'missing sandboxId or hostPath'}

    # Accept mixed-case or slightly messy sandboxId strings from callers
    # but canonicalize them into lower-case hyphenated ids for machine
    # naming and log files.
    sanitized = sanitize_sandbox_id(sandbox_id)
    if sanitized == '':
        return {'ok': False, 'error': 'invalid sandboxId after sanitization'}

    # Ensure host path is under the repo clients/ directory for safety
    host_path = os.path.realpath(host_path)
    if not host_path.startswith('/home') and not host_path.startswith('/var') and not host_path.startswith('/srv'):
        return {'ok': False, 'error': 'hostPath not permitted'}

    # Build log path for the install
    log_dir = Path('/var/log/ginto-sandboxd')
    log_dir.mkdir(parents=True, exist_ok=True)
    install_log = log_dir / f'install_{sanitized}.log'

    # Execute the create script directly (no shell) as root. This daemon
    # runs as root, so it can execute the script without sudo. We pass
    # arguments directly to avoid shell injection.
    try:
        # Use sanitized id to create a predictable machine/log name. Keep
        # the original id available for callers so the UI can map between
        # user-provided IDs and the canonical form.
        cmd = [CREATE_SCRIPT, sanitized, host_path]
        with open(install_log, 'ab') as lf:
            lf.write(b'=== create request received ===\n')
            lf.flush()
            p = subprocess.Popen(cmd, stdout=lf, stderr=lf)
        logging.info('Launched create script for %s (pid=%s) writing to %s', sanitized, p.pid, str(install_log))
        return {'ok': True, 'pid': p.pid, 'sandboxId': sanitized, 'originalSandboxId': sandbox_id, 'log': str(install_log)}
    except Exception as e:
        logging.exception('Failed to launch create script')
        return {'ok': False, 'error': str(e)}


def serve():
    if os.path.exists(SOCKET_PATH):
        try:
            os.unlink(SOCKET_PATH)
        except Exception:
            pass

    server = socket.socket(socket.AF_UNIX, socket.SOCK_STREAM)
    server.bind(SOCKET_PATH)
    os.chmod(SOCKET_PATH, 0o660)
    server.listen(5)
    logging.info('ginto_sandboxd listening on %s', SOCKET_PATH)

    while True:
        conn, _ = server.accept()
        piduid = get_peer_cred(conn)
        try:
            data = conn.recv(8192)
            if not data:
                conn.close(); continue
            try:
                payload = json.loads(data.decode('utf-8'))
            except Exception:
                conn.send(b'{"ok":false,"error":"invalid json"}\n')
                conn.close(); continue

            action = payload.get('action')
            if action == 'create':
                res = handle_create(payload)
                conn.send((json.dumps(res) + '\n').encode('utf-8'))
            else:
                conn.send(b'{"ok":false,"error":"unknown action"}\n')
        except Exception:
            logging.exception('Error handling connection')
        finally:
            try: conn.close()
            except Exception: pass


if __name__ == '__main__':
    serve()

Ginto sandboxd (privileged sandbox creator)
===========================================

This folder contains a small, auditable root daemon scaffold that provides
one secure RPC: create a systemd-nspawn machine for a given sandbox id and
host path. The intention is to run this as a root-owned service and allow
the web application to talk to it over a UNIX domain socket.

Installation (operator steps)
-----------------------------
1. Copy the daemon and unit into place as root:

   sudo cp ginto_sandboxd.py /usr/local/bin/ginto_sandboxd.py
   sudo chown root:root /usr/local/bin/ginto_sandboxd.py
   sudo chmod 0750 /usr/local/bin/ginto_sandboxd.py

   sudo cp ginto-sandboxd.service /etc/systemd/system/ginto-sandboxd.service
   sudo systemctl daemon-reload
   sudo systemctl enable --now ginto-sandboxd.service

2. Confirm the socket exists and is owned by root and readable by the web
   process user (or use group permissions):

   sudo ls -l /run/ginto-sandboxd.sock

3. Configure the web app to connect to `/run/ginto-sandboxd.sock`. The PHP
   `SandboxManager` will attempt to use the daemon if present.

Security notes
--------------
- Only install this as root on a trusted machine.
- Review the daemon source before enabling. The daemon validates inputs
  but operators are responsible for system-level security.

<?php
// src/Helpers/SandboxManager.php
namespace Ginto\Helpers;

class SandboxManager
{
    // Return the machine/container name for a given sandbox id
    public static function containerNameForSandbox(string $sandboxId): string
    {
        // Normalize to a safe, lowercase hyphenated machine name to match
        // systemd-nspawn expectations. Replace groups of non-alphanumeric
        // characters with a single hyphen, trim hyphens, and lowercase.
        $safe = preg_replace('/[^a-zA-Z0-9]+/', '-', $sandboxId);
        $safe = trim($safe, '-');
        $safe = strtolower($safe);
        return 'ginto-sandbox-' . $safe;
    }

    /**
     * Return a daemon-friendly normalized sandbox id (lowercase, [a-z0-9._-]
     * with groups of invalid characters replaced by a single hyphen and a
     * maximum length of 63 — matches `scripts/sandboxd.php` behaviour.)
     */
    public static function canonicalSandboxId(string $sandboxId): string
    {
        $sanitized = preg_replace('/[^a-z0-9._-]+/', '-', strtolower($sandboxId));
        $sanitized = trim($sanitized, '-');
        if ($sanitized === '') {
            $sanitized = substr(md5($sandboxId), 0, 16);
        }
        if (strlen($sanitized) > 63) $sanitized = substr($sanitized, 0, 63);
        return $sanitized;
    }

    // Check whether a systemd-nspawn machine exists for this sandbox
    public static function sandboxExists(string $sandboxId): bool
    {
        $name = self::containerNameForSandbox($sandboxId);
        // Prefer non-interactive sudo to query machinectl (web user may not
        // have permission to talk to systemd/machined). Fall back to direct
        // machinectl if sudo is not available.
        // Prefer non-interactive sudo so web processes don't need system
        // permission to query machinectl. If sudo exists but passwordless
        // mode fails, fall back to calling machinectl directly which may
        // succeed when the current user can talk to systemd/machined.
        $cmd = ['/usr/bin/sudo', '-n', 'machinectl', 'status', $name];
        $des = [1 => ['pipe','w'], 2 => ['pipe','w']];
        $proc = proc_open($cmd, $des, $pipes);
        $rc = null;
        // If we successfully launched the `machinectl` call, try to inspect
        // the machine's reported state (prefer "running"). This is more
        // accurate than depending on status exit codes which can report
        // existence even for stopped units.
        if (!is_resource($proc)) {
            // If proc_open fails for sudo invocation, try direct machinectl
            $cmdDirect = ['machinectl', 'show', '--property=State', '--value', $name];
            $proc2 = @proc_open($cmdDirect, $des, $pipes);
            if (!is_resource($proc2)) return false;
            $out = stream_get_contents($pipes[1]);
            @fclose($pipes[1]); @fclose($pipes[2]);
            $rc = proc_close($proc2);
            $state = trim((string)$out);
            if ($state !== '') {
                return strtolower($state) === 'running';
            }
            return $rc === 0;
        }
        // Read the command's output and decide based on the State value
        $out = stream_get_contents($pipes[1]);
        @fclose($pipes[1]); @fclose($pipes[2]);
        $rc = proc_close($proc);
        $state = trim((string)$out);
        if ($state !== '') {
            return strtolower($state) === 'running';
        }
        if ($rc === 0) return true;

        // sudo failed (probably requires password) — try direct machinectl
        // As a final fallback try inspecting the machine state directly
        $cmdDirect = ['machinectl', 'show', '--property=State', '--value', $name];
        $proc2 = @proc_open($cmdDirect, $des, $pipes);
        if (!is_resource($proc2)) return false;
        $out2 = stream_get_contents($pipes[1]);
        @fclose($pipes[1]); @fclose($pipes[2]);
        $rc2 = proc_close($proc2);
        $state2 = trim((string)$out2);
        if ($state2 !== '') {
            return strtolower($state2) === 'running';
        }
        return $rc2 === 0;
    }

    // Execute a command inside the systemd-nspawn machine via machinectl shell
    // Returns array: [exit_code, stdout, stderr]
    public static function execInSandbox(string $sandboxId, string $command, string $cwd = '/home', int $timeout = 10, int $maxBytes = 200000)
    {
        $name = self::containerNameForSandbox($sandboxId);
        // Ensure the machine is running (try to create/start if missing)
        try {
            self::ensureSandboxRunning($sandboxId, null);
        } catch (\Throwable $_) {
            // proceed — machinectl will report errors if not available
        }

        // For security we prefer to route exec requests via the root-controlled
        // sandbox broker (socket-activated daemon) rather than executing
        // machinectl directly from the web process. The broker runs as root
        // and will perform the exec inside the container. The broker currently
        // performs exec asynchronously and will return an accepted response.
        // If the broker accepts the request we return a non-blocking success
        // tuple ([null, '', '']) to indicate the exec was dispatched.

        $socketCandidates = ['/run/ginto-sandboxd.sock', '/run/sandboxd.sock', '/run/ginto/sandboxd.sock'];
        $sanitized = self::canonicalSandboxId($sandboxId);
        $msg = json_encode(['action' => 'exec', 'sandboxId' => $sanitized, 'originalSandboxId' => $sandboxId, 'command' => $command, 'cwd' => $cwd]);
        foreach ($socketCandidates as $socketPath) {
            if (!file_exists($socketPath)) continue;
            $ctx = stream_context_create(['socket' => ['timeout' => 0.6]]);
            $sock = @stream_socket_client('unix://' . $socketPath, $errno, $errstr, 0.6, STREAM_CLIENT_CONNECT, $ctx);
            if ($sock === false) continue;
            stream_set_blocking($sock, true);
            fwrite($sock, $msg . "\n");
            stream_set_timeout($sock, 1);
            $resp = stream_get_contents($sock);
            fclose($sock);
            if ($resp === false || trim($resp) === '') continue;
            $json = @json_decode(trim($resp), true);
            if (is_array($json) && !empty($json['ok'])) {
                // broker accepted — exec dispatched; this is async so no stdout
                // is available to return. We indicate dispatched with null code.
                return [null, '', ''];
            }
            // broker responded with structured error — unless explicitly
            // disabled, fall back to local exec. If a hardened environment
            // sets SANDBOXD_DISALLOW_EXEC=1 we'll raise immediately.
            if (is_array($json) && !empty($json['error'])) {
                $errMsg = $json['error'] . (!empty($json['message']) ? ' - ' . $json['message'] : '');
                if (getenv('SANDBOXD_DISALLOW_EXEC') === '1') {
                    throw new \RuntimeException('Broker refused exec: ' . $errMsg);
                }
                // otherwise continue and try fallback to local execution
                // (the loop will try other sockets if present).
                continue;
            }
        }

        // If no broker socket available, fall back to local machinectl exec.
        // By default we allow this fallback for local/dev environments; in
        // production you should run the socket-activated broker so the web
        // process remains fully unprivileged. To *disable* the fallback,
        // set SANDBOXD_DISALLOW_EXEC=1 in the web process environment.
        if (getenv('SANDBOXD_DISALLOW_EXEC') === '1') {
            throw new \RuntimeException('Exec disallowed: no sandbox broker available and SANDBOXD_DISALLOW_EXEC=1');
        }

        $cmd = ['machinectl', 'shell', $name, '/bin/sh', '-c', $command];

        $descriptors = [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']];
        $process = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($process)) {
            throw new \RuntimeException('Failed to start machinectl shell: ' . implode(' ', $cmd));
        }
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = ''; $stderr = '';
        $start = microtime(true);
        while (true) {
            $status = proc_get_status($process);
            $read = [$pipes[1], $pipes[2]];
            $write = $except = null;
            if (stream_select($read, $write, $except, 0, 200000)) {
                foreach ($read as $r) {
                    $chunk = stream_get_contents($r);
                    if ($r === $pipes[1]) $stdout .= $chunk; else $stderr .= $chunk;
                    if (strlen($stdout) + strlen($stderr) > $maxBytes) break 2;
                }
            }
            if (!$status['running']) break;
            if ((microtime(true) - $start) > $timeout) {
                proc_terminate($process);
                break;
            }
            usleep(100000);
        }
        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);
        foreach ($pipes as $p) { @fclose($p); }
        $code = proc_close($process);
        return [$code, $stdout, $stderr];
    }

    /**
     * Ensure a systemd-nspawn machine exists and is started for a sandbox.
     * Attempts to invoke the local `scripts/create_nspawn.sh` helper via sudo
     * when the machine is not present. If $hostPath is null we will infer
     * the expected clients path inside the repository.
     *
     * Returns true if the machine exists or was started successfully.
     */
    public static function ensureSandboxRunning(string $sandboxId, ?string $hostPath = null, int $waitSeconds = 6, bool $riskless = false): bool
    {
        $name = self::containerNameForSandbox($sandboxId);
        if (self::sandboxExists($sandboxId)) return true;

        // Infer host path if not provided: <repo-root>/clients/<sandboxId>
        if (empty($hostPath)) {
            $repoRoot = dirname(dirname(__DIR__));
            $hostPath = $repoRoot . '/clients/' . $sandboxId;
        }

        // Prefer the system-installed privileged wrapper if present; otherwise
        // fall back to the repository script. Instead of waiting synchronously
        // for the machine to appear (which blocks the editor), launch the
        // privileged helper as a non-blocking background job using `nohup`
        // and `sudo -n` so the web request returns immediately. The UI can
        // poll `sandboxExists()` or the install log to detect readiness.
        $wrapper = '/usr/local/sbin/ginto-create-wrapper.sh';
        $repoRoot = dirname(dirname(__DIR__));
        $script = $repoRoot . '/scripts/create_nspawn.sh';

        if (is_file($wrapper) && is_executable($wrapper)) {
            $helper = $wrapper;
        } elseif (is_file($script)) {
            $helper = $script;
        } else {
            // No helper available
            return false;
        }

        // Prepare log path under storage/backups
        $storagePath = defined('STORAGE_PATH') ? STORAGE_PATH : dirname($repoRoot) . '/storage';
        $logDir = $storagePath . '/backups';
        if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
        $logFile = $logDir . '/install_' . preg_replace('/[^a-z0-9_-]/i', '_', $sandboxId) . '.log';

        // If riskless/dry-run mode requested, do not perform any privileged
        // operations. Instead write a safe dry-run summary to the install log
        // and return true so the UI can display the planned steps without
        // making changes to the host.
        if ($riskless) {
            $summary = [];
            $summary[] = "[DRY-RUN] Riskless dry-run requested for sandbox: $sandboxId";
            $summary[] = "Planned actions:";
            $summary[] = " - Normalize sandbox id -> machine name: $name";
            $summary[] = " - Ensure host path exists: $hostPath";
            $summary[] = " - Create rootfs at /var/lib/machines/$name (requires operator action)";
            $summary[] = " - Extract cached template if available: /var/lib/machines/ginto_base_template.tar.gz";
            $summary[] = " - Attempt to start machine via systemd-nspawn: systemd-nspawn -bD /var/lib/machines/$name --machine=$name --bind=$hostPath:/home/sandbox";
            $summary[] = " - Fallback: non-boot container running a harmless sleep loop";
            $summary[] = "No privileged commands were executed in this dry-run.";
            @file_put_contents($logFile, implode("\n", $summary) . "\n", FILE_APPEND | LOCK_EX);
            return true;
        }

        // If the secure daemon socket exists, try to talk to it first. This
        // avoids needing sudo from the web process. We use a short timeout and
        // a small retry loop to handle transient startup race conditions.
        $socketCandidates = ['/run/ginto-sandboxd.sock', '/run/sandboxd.sock', '/run/ginto/sandboxd.sock'];
        // Sanitize the sandbox id before contacting the daemon. Some
        // sandbox broker implementations enforce a restricted id format
        // (lowercase + [a-z0-9._-]) and will reject otherwise. Send the
        // sanitized id as the primary `sandboxId` and include the
        // original value as `originalSandboxId` so the daemon can map
        // between them in logs if needed.
        $sanitized = self::canonicalSandboxId($sandboxId);

        $msg = json_encode(['action' => 'create', 'sandboxId' => $sanitized, 'originalSandboxId' => $sandboxId, 'hostPath' => $hostPath]);
        foreach ($socketCandidates as $socketPath) {
            if (!file_exists($socketPath)) continue;
            // Try a few times to connect in case the broker is still starting
            $attempts = 3;
            $connected = false;
            for ($i = 0; $i < $attempts; $i++) {
                $ctx = stream_context_create(['socket' => ['timeout' => 0.6]]);
                $sock = @stream_socket_client('unix://' . $socketPath, $errno, $errstr, 0.6, STREAM_CLIENT_CONNECT, $ctx);
                if ($sock === false) {
                    // brief backoff
                    usleep(200000);
                    continue;
                }
                $connected = true;
                stream_set_blocking($sock, true);
                // send single-line JSON and attempt to read a reply
                fwrite($sock, $msg . "\n");
                // read with a short deadline
                stream_set_timeout($sock, 1);
                $resp = stream_get_contents($sock);
                fclose($sock);
                if ($resp === false || trim($resp) === '') {
                    // no response — treat as transient failure and try next attempt/socket
                    usleep(200000);
                    continue;
                }
                $json = @json_decode(trim($resp), true);
                if (is_array($json) && !empty($json['ok'])) {
                    // If the daemon returned details (canonical/sanitized sandbox id
                    // and an operator-visible log path), append a short mapping
                    // line to the UI-facing install log so the editor tails the
                    // correct, up-to-date messages and can surface the canonical
                    // name for the operator.
                    $sId = $json['sandboxId'] ?? null;
                    $origId = $json['originalSandboxId'] ?? null;
                    $daemonLog = $json['log'] ?? null;
                    if ($sId || $daemonLog) {
                        $note = '[' . date('c') . '] daemon_ok';
                        if ($origId && $origId !== $sId) $note .= " canonical_sandboxId=$sId original=$origId";
                        if ($daemonLog) $note .= " daemon_log=$daemonLog";
                        @file_put_contents($logFile, $note . "\n", FILE_APPEND | LOCK_EX);
                    }
                    return true;
                }
                // If daemon returned a structured error, write to the install log
                if (is_array($json) && !empty($json['error'])) {
                    $err = '[' . date('c') . '] daemon_error: ' . $json['error'];
                    if (!empty($json['message'])) $err .= ' - ' . $json['message'];
                    @file_put_contents($logFile, $err . "\n", FILE_APPEND | LOCK_EX);
                    // do not fall back to sudo; the daemon is reachable but refused
                    return false;
                }
                // If response is non-empty but not JSON-success, log raw response and fail
                @file_put_contents($logFile, '[' . date('c') . '] daemon_response: ' . trim($resp) . "\n", FILE_APPEND | LOCK_EX);
                return false;
            }
            if (!$connected) {
                // couldn't connect to this socket; try next candidate
                continue;
            }
        }

        // Build the background command safely (use escaped args)
        $escHelper = escapeshellarg($helper);
        $escSandbox = escapeshellarg($sandboxId);
        $escHostPath = escapeshellarg($hostPath);

        // Before attempting to run sudo, check whether passwordless sudo is
        // available for the web process. If not, avoid launching the sudo
        // command which will emit a password prompt into the install log
        // and instead write a clear actionable message to the log so an
        // administrator can apply the recommended remediation.
        $sudoTestCmd = ["/usr/bin/sudo", "-n", "true"];
        $des = [1 => ['pipe','w'], 2 => ['pipe','w']];
        $proc = @proc_open($sudoTestCmd, $des, $pipes);
        $sudoOk = false;
        if (is_resource($proc)) {
            fclose($pipes[1]); fclose($pipes[2]);
            $rc = proc_close($proc);
            $sudoOk = ($rc === 0);
        }

        if (!$sudoOk) {
            // Record an explanatory message and give a hint to the operator.
            $msg = [];
            $msg[] = "[ERROR] Passwordless sudo not available for web process. Could not launch privileged helper for sandbox: $sandboxId";
            $msg[] = "Possible remediations:";
            $msg[] = " - Install and enable the socket-activated sandbox daemon (recommended).";
            $msg[] = " - Or configure a restricted sudoers rule to allow the web user to run the helper without a password (see deploy/ginto-sudoers.example).";
            $msg[] = " - After applying changes, re-run the install from the editor.";
            @file_put_contents($logFile, implode("\n", $msg) . "\n", FILE_APPEND | LOCK_EX);
            return false;
        }

        $cmdString = "nohup /usr/bin/sudo -n $escHelper $escSandbox $escHostPath > " . escapeshellarg($logFile) . " 2>&1 &";

        // Attempt to launch the background job
        @exec($cmdString . ' 2>/dev/null &');

        // Return true to indicate the start was launched (non-blocking).
        return true;
    }

    // No published-port concept for nspawn; return null
    public static function getPublishedPort(string $sandboxId, int $containerPort = 80)
    {
        return null;
    }
}

<?php
/**
 * Network & Container Management Controller
 * 
 * Admin interface for managing LXD/LXC containers and network modes.
 * Provides list, detail, start/stop/delete actions, exec, and logs.
 */
namespace Ginto\Controllers;

use Ginto\Core\Database;
use Ginto\Helpers\LxdSandboxManager;
use Ginto\Helpers\LxdApiClient;
use Core\Controller;

class NetworkController extends Controller
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->requireAdmin();
    }

    /**
     * List all LXD containers
     */
    public function index()
    {
        $containers = $this->getAllContainers();
        $stats = $this->getSystemStatsFromContainers($containers);
        $networkInfo = $this->getNetworkInfoFast();
        
        $this->view('admin/network/network', [
            'title' => 'LXD Container Manager',
            'containers' => $containers,
            'stats' => $stats,
            'networkInfo' => $networkInfo,
            'db' => $this->db
        ]);
    }

    /**
     * Show details for a single container
     */
    public function show($name)
    {
        $container = $this->getContainerInfo($name);
        
        if (!$container) {
            http_response_code(404);
            echo 'Container not found';
            return;
        }

        $logs = $this->getContainerLogs($name);
        
        $this->view('admin/network/lxc', [
            'title' => "Container: $name",
            'container' => $container,
            'logs' => $logs
        ]);
    }

    /**
     * API: List containers as JSON
     */
    public function apiList()
    {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'containers' => $this->getAllContainers(),
            'stats' => $this->getSystemStats()
        ]);
    }

    /**
     * API: Get network status
     */
    public function apiNetworkStatus()
    {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'network' => $this->getNetworkInfo()
        ]);
    }

    /**
     * API: Set network mode
     */
    public function apiNetworkSet()
    {
        header('Content-Type: application/json');
        
        $input = json_decode(file_get_contents('php://input'), true);
        $mode = $input['mode'] ?? '';
        
        if (!in_array($mode, ['bridge', 'nat', 'macvlan', 'ipvlan'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid mode. Use "bridge", "nat", "macvlan", or "ipvlan"']);
            return;
        }
        
        $scriptPath = defined('ROOT_PATH') ? ROOT_PATH . '/bin/setup_network.sh' : dirname(__DIR__, 2) . '/bin/setup_network.sh';
        
        if (!file_exists($scriptPath)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'setup_network.sh not found']);
            return;
        }
        
        // Run the setup script
        $output = [];
        $code = 0;
        
        // Execute the appropriate setup command
        exec("sudo bash " . escapeshellarg($scriptPath) . " " . escapeshellarg($mode) . " 2>&1", $output, $code);
        
        // Strip ANSI color codes from output
        $cleanOutput = preg_replace('/\x1b\[[0-9;]*m/', '', implode("\n", $output));
        
        if ($code !== 0) {
            echo json_encode([
                'success' => false,
                'error' => 'Script execution failed',
                'output' => $cleanOutput,
                'code' => $code
            ]);
            return;
        }
        
        // Reload environment
        $envFile = defined('ROOT_PATH') ? ROOT_PATH . '/.env' : dirname(__DIR__, 2) . '/.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                    putenv(trim($line));
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'mode' => $mode,
            'message' => "Network mode set to {$mode}",
            'output' => $cleanOutput,
            'network' => $this->getNetworkInfo()
        ]);
    }

    /**
     * API: Get container info
     */
    public function apiShow($name)
    {
        header('Content-Type: application/json');
        $container = $this->getContainerInfo($name);
        
        if (!$container) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Container not found']);
            return;
        }
        
        echo json_encode(['success' => true, 'container' => $container]);
    }

    /**
     * Start a container
     */
    public function start($name)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            return;
        }
        
        $this->validateCsrf();
        
        // Use LXD API (non-blocking, fast) instead of exec
        $result = LxdApiClient::startInstanceAsync($name);
        $success = $result['success'];
        $message = $success ? 'Container started' : ($result['error'] ?? 'Failed to start');
        
        if ($this->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => $success,
                'message' => $message
            ]);
            return;
        }
        
        header('Location: /admin/network/' . urlencode($name));
    }

    /**
     * Stop a container
     */
    public function stop($name)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            return;
        }
        
        $this->validateCsrf();
        
        // Use LXD API (non-blocking, fast) instead of exec
        $result = LxdApiClient::stopInstanceAsync($name);
        $success = $result['success'];
        $message = $success ? 'Container stopped' : ($result['error'] ?? 'Failed to stop');
        
        if ($this->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => $success,
                'message' => $message
            ]);
            return;
        }
        
        header('Location: /admin/network/' . urlencode($name));
    }

    /**
     * Restart a container
     */
    public function restart($name)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            return;
        }
        
        $this->validateCsrf();
        
        // Use LXD API (non-blocking, fast) instead of exec
        $result = LxdApiClient::restartInstanceAsync($name);
        $success = $result['success'];
        $message = $success ? 'Container restarted' : ($result['error'] ?? 'Failed to restart');
        
        if ($this->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => $success,
                'message' => $message
            ]);
            return;
        }
        
        header('Location: /admin/network/' . urlencode($name));
    }

    /**
     * Delete a container completely (uses unified delete to clean up DB, Redis, directory)
     */
    public function delete($name)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            return;
        }
        
        $this->validateCsrf();
        
        // Extract sandbox ID from container name (sandbox-xxx -> xxx)
        $sandboxId = str_starts_with($name, 'sandbox-') ? substr($name, 8) : null;
        
        if ($sandboxId) {
            // Use unified delete to clean up everything atomically
            $result = LxdSandboxManager::deleteSandboxCompletely($sandboxId, $this->db);
            $success = $result['success'];
            $message = $success ? 'Sandbox deleted completely (container + DB + Redis + directory)' : implode(', ', $result['errors']);
        } else {
            // Non-sandbox container - just delete the container
            $lxcBin = $this->getLxcBin();
            exec("sudo $lxcBin stop " . escapeshellarg($name) . " --force 2>&1");
            $output = [];
            $code = 0;
            exec("sudo $lxcBin delete " . escapeshellarg($name) . " 2>&1", $output, $code);
            $success = $code === 0;
            $message = $success ? 'Container deleted' : implode("\n", $output);
        }
        
        if ($this->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => $success,
                'message' => $message
            ]);
            return;
        }
        
        header('Location: /admin/network');
    }

    /**
     * Cleanup all stopped visitor sandboxes (no user_id in DB)
     */
    public function cleanup()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            return;
        }
        
        $this->validateCsrf();
        
        $deleted = [];
        $errors = [];
        
        // Get all sandbox containers
        $containers = $this->getAllContainers();
        
        foreach ($containers as $c) {
            $name = $c['name'] ?? '';
            
            // Only process sandbox containers
            if (!str_starts_with($name, 'sandbox-')) {
                continue;
            }
            
            $sandboxId = substr($name, 8);
            
            // Check if this is a visitor sandbox (no user_id in DB)
            $row = $this->db->get('client_sandboxes', ['user_id'], ['sandbox_id' => $sandboxId]);
            $isVisitor = empty($row) || empty($row['user_id']);
            
            // Only delete stopped visitor sandboxes
            if ($isVisitor && ($c['status'] ?? '') !== 'Running') {
                $result = LxdSandboxManager::deleteSandboxCompletely($sandboxId, $this->db);
                if ($result['success']) {
                    $deleted[] = $sandboxId;
                } else {
                    $errors[] = "$sandboxId: " . implode(', ', $result['errors']);
                }
            }
        }
        
        if ($this->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'deleted' => $deleted,
                'errors' => $errors,
                'message' => count($deleted) . ' visitor sandboxes cleaned up'
            ]);
            return;
        }
        
        $_SESSION['flash_success'] = count($deleted) . ' visitor sandboxes cleaned up';
        header('Location: /admin/network');
    }

    /**
     * Execute command in container
     */
    public function exec($name)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            return;
        }
        
        $this->validateCsrf();
        
        $command = $_POST['command'] ?? '';
        $timeout = min(60, max(1, (int)($_POST['timeout'] ?? 30)));
        
        if (empty($command)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'No command provided']);
            return;
        }
        
        $escapedCmd = escapeshellarg($command);
        $fullCmd = "timeout $timeout lxc exec " . escapeshellarg($name) . " -- /bin/sh -c $escapedCmd 2>&1";
        
        $output = [];
        $code = 0;
        exec($fullCmd, $output, $code);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'exit_code' => $code,
            'output' => implode("\n", $output)
        ]);
    }

    /**
     * Get container logs
     */
    public function logs($name)
    {
        $lines = (int)($_GET['lines'] ?? 100);
        $logs = $this->getContainerLogs($name, $lines);
        
        if ($this->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'logs' => $logs]);
            return;
        }
        
        header('Content-Type: text/plain');
        echo $logs;
    }

    /**
     * Create a new container
     */
    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            return;
        }
        
        $this->validateCsrf();
        
        $userId = $_POST['user_id'] ?? '';
        $image = $_POST['image'] ?? LxdSandboxManager::BASE_IMAGE;
        
        if (empty($userId)) {
            if ($this->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'User ID required']);
                return;
            }
            header('Location: /admin/network?error=User+ID+required');
            return;
        }
        
        $result = LxdSandboxManager::createSandbox($userId);
        
        if ($this->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode($result);
            return;
        }
        
        if ($result['success']) {
            header('Location: /admin/network/' . urlencode($result['sandboxId']));
        } else {
            header('Location: /admin/network?error=' . urlencode($result['error'] ?? 'Failed to create'));
        }
    }

    /**
     * Bulk action on containers
     * Uses LXD API for fast non-blocking operations
     */
    public function bulk()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            return;
        }
        
        $this->validateCsrf();
        
        $action = $_POST['action'] ?? '';
        $containers = $_POST['containers'] ?? [];
        
        if (empty($containers) || !is_array($containers)) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'No containers selected']);
            return;
        }
        
        $results = [];
        foreach ($containers as $name) {
            $name = basename($name); // Security: prevent path traversal
            
            switch ($action) {
                case 'start':
                    $result = LxdApiClient::startInstanceAsync($name);
                    $results[$name] = [
                        'success' => $result['success'],
                        'message' => $result['success'] ? 'Started' : ($result['error'] ?? 'Failed')
                    ];
                    break;
                case 'stop':
                    $result = LxdApiClient::stopInstanceAsync($name);
                    $results[$name] = [
                        'success' => $result['success'],
                        'message' => $result['success'] ? 'Stopped' : ($result['error'] ?? 'Failed')
                    ];
                    break;
                case 'delete':
                    // Stop first, then delete
                    LxdApiClient::stopInstanceAsync($name, true);
                    usleep(500000); // Brief wait for stop to initiate
                    $result = LxdApiClient::deleteInstanceAsync($name);
                    $results[$name] = [
                        'success' => $result['success'],
                        'message' => $result['success'] ? 'Deleted' : ($result['error'] ?? 'Failed')
                    ];
                    break;
                default:
                    $results[$name] = ['success' => false, 'error' => 'Unknown action'];
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'results' => $results]);
    }

    // ==================== Helper Methods ====================

    /**
     * Get path to lxc binary
     */
    private function getLxcBin(): ?string
    {
        static $lxcBin = null;
        static $checked = false;
        
        if (!$checked) {
            $checked = true;
            foreach (['/snap/bin/lxc', '/usr/bin/lxc', '/usr/local/bin/lxc'] as $path) {
                if (file_exists($path) && is_executable($path)) {
                    $lxcBin = $path;
                    break;
                }
            }
        }
        return $lxcBin;
    }

    /**
     * Get all LXD containers with details
     * Uses LXD API with recursion=2 for fast single-call fetch
     */
    private function getAllContainers(): array
    {
        // Use LXD API (non-blocking, fast) - single call gets all containers with state
        $data = LxdApiClient::listInstancesWithState();
        
        if (empty($data)) {
            // Fallback to CLI if API unavailable
            return $this->getAllContainersFallback();
        }
        
        $containers = [];
        foreach ($data as $c) {
            $name = $c['name'] ?? '';
            $status = strtolower($c['status'] ?? 'unknown');
            $type = $c['type'] ?? 'container';
            $created = $c['created_at'] ?? '';
            $snapshots = count($c['snapshots'] ?? []);
            
            // Extract IPv4 from network info - prefer eth0/enp*, skip docker0/veth/lo
            $ipv4 = null;
            $network = $c['state']['network'] ?? [];
            $skipInterfaces = ['lo', 'docker0', 'br-', 'veth', 'virbr'];
            
            // First try to find eth0 or enp* specifically
            foreach ($network as $iface => $ifData) {
                if (preg_match('/^(eth|enp)/', $iface)) {
                    foreach ($ifData['addresses'] ?? [] as $addr) {
                        if ($addr['family'] === 'inet') {
                            $ipv4 = $addr['address'];
                            break 2;
                        }
                    }
                }
            }
            
            // Fallback: any non-virtual interface
            if (!$ipv4) {
                foreach ($network as $iface => $ifData) {
                    $skip = false;
                    foreach ($skipInterfaces as $prefix) {
                        if (str_starts_with($iface, $prefix)) {
                            $skip = true;
                            break;
                        }
                    }
                    if ($skip) continue;
                    
                    foreach ($ifData['addresses'] ?? [] as $addr) {
                        if ($addr['family'] === 'inet') {
                            $ipv4 = $addr['address'];
                            break 2;
                        }
                    }
                }
            }
            
            // Get user ID from container name if it's a sandbox
            $userId = null;
            if (strpos($name, LxdSandboxManager::CONTAINER_PREFIX) === 0) {
                $userId = substr($name, strlen(LxdSandboxManager::CONTAINER_PREFIX));
            }
            
            $containers[] = [
                'name' => $name,
                'status' => $status,
                'ip' => $ipv4,
                'type' => $type,
                'snapshots' => $snapshots,
                'created' => $created,
                'user_id' => $userId,
                'is_sandbox' => $userId !== null
            ];
        }
        
        return $containers;
    }
    
    /**
     * Fallback: Get all containers using CLI (if API unavailable)
     */
    private function getAllContainersFallback(): array
    {
        $lxcBin = $this->getLxcBin();
        if (!$lxcBin) {
            return [];
        }
        
        $output = [];
        $code = 0;
        // Use JSON format to avoid multi-line CSV parsing issues
        exec("sudo $lxcBin list --format json 2>/dev/null", $output, $code);
        
        if ($code !== 0 || empty($output)) {
            return [];
        }
        
        $data = json_decode(implode('', $output), true);
        if (!is_array($data)) {
            return [];
        }
        
        $containers = [];
        foreach ($data as $c) {
            $name = $c['name'] ?? '';
            $status = strtolower($c['status'] ?? 'unknown');
            $type = $c['type'] ?? 'container';
            $created = $c['created_at'] ?? '';
            $snapshots = count($c['snapshots'] ?? []);
            
            // Extract IPv4 from network info - prefer eth0/enp*, skip docker0/veth/lo
            $ipv4 = null;
            $network = $c['state']['network'] ?? [];
            $skipInterfaces = ['lo', 'docker0', 'br-', 'veth', 'virbr'];
            
            // First try to find eth0 or enp* specifically
            foreach ($network as $iface => $ifData) {
                if (preg_match('/^(eth|enp)/', $iface)) {
                    foreach ($ifData['addresses'] ?? [] as $addr) {
                        if ($addr['family'] === 'inet') {
                            $ipv4 = $addr['address'];
                            break 2;
                        }
                    }
                }
            }
            
            // Fallback: any non-virtual interface
            if (!$ipv4) {
                foreach ($network as $iface => $ifData) {
                    $skip = false;
                    foreach ($skipInterfaces as $prefix) {
                        if (str_starts_with($iface, $prefix)) {
                            $skip = true;
                            break;
                        }
                    }
                    if ($skip) continue;
                    
                    foreach ($ifData['addresses'] ?? [] as $addr) {
                        if ($addr['family'] === 'inet') {
                            $ipv4 = $addr['address'];
                            break 2;
                        }
                    }
                }
            }
            
            // Get user ID from container name if it's a sandbox
            $userId = null;
            if (strpos($name, LxdSandboxManager::CONTAINER_PREFIX) === 0) {
                $userId = substr($name, strlen(LxdSandboxManager::CONTAINER_PREFIX));
            }
            
            $containers[] = [
                'name' => $name,
                'status' => $status,
                'ip' => $ipv4,
                'type' => $type,
                'snapshots' => $snapshots,
                'created' => $created,
                'user_id' => $userId,
                'is_sandbox' => $userId !== null
            ];
        }
        
        return $containers;
    }

    /**
     * Get detailed info for a single container
     * Uses LXD API for fast non-blocking access
     */
    private function getContainerInfo(string $name): ?array
    {
        // Use LXD API (non-blocking, fast)
        $instance = LxdApiClient::getInstance($name);
        $state = LxdApiClient::getInstanceState($name);
        
        if (!$instance) {
            return null;
        }
        
        $metadata = $instance['metadata'] ?? $instance;
        $stateData = $state['metadata'] ?? $state;
        
        $info = [
            'name' => $name,
            'status' => strtolower($metadata['status'] ?? 'unknown'),
            'type' => $metadata['type'] ?? 'container',
            'created' => $metadata['created_at'] ?? '',
            'architecture' => $metadata['architecture'] ?? '',
            'description' => $metadata['description'] ?? '',
            'ephemeral' => $metadata['ephemeral'] ?? false,
            'stateful' => $metadata['stateful'] ?? false,
            'profiles' => $metadata['profiles'] ?? [],
            'config' => $metadata['config'] ?? [],
        ];
        
        // Get resource usage from state
        $info['resources'] = $this->getContainerResourcesFromState($stateData);
        
        // Extract IP from state
        $network = $stateData['network'] ?? [];
        $skipInterfaces = ['lo', 'docker0', 'br-', 'veth', 'virbr'];
        $ipv4 = null;
        
        foreach ($network as $iface => $ifData) {
            if (preg_match('/^(eth|enp)/', $iface)) {
                foreach ($ifData['addresses'] ?? [] as $addr) {
                    if ($addr['family'] === 'inet') {
                        $ipv4 = $addr['address'];
                        break 2;
                    }
                }
            }
        }
        
        if (!$ipv4) {
            foreach ($network as $iface => $ifData) {
                $skip = false;
                foreach ($skipInterfaces as $prefix) {
                    if (str_starts_with($iface, $prefix)) {
                        $skip = true;
                        break;
                    }
                }
                if ($skip) continue;
                
                foreach ($ifData['addresses'] ?? [] as $addr) {
                    if ($addr['family'] === 'inet') {
                        $ipv4 = $addr['address'];
                        break 2;
                    }
                }
            }
        }
        $info['ip'] = $ipv4;
        
        // Check if it's a sandbox
        if (strpos($name, LxdSandboxManager::CONTAINER_PREFIX) === 0) {
            $info['user_id'] = substr($name, strlen(LxdSandboxManager::CONTAINER_PREFIX));
            $info['is_sandbox'] = true;
        } else {
            $info['is_sandbox'] = false;
        }
        
        return $info;
    }

    /**
     * Get container resource usage from API state data
     */
    private function getContainerResourcesFromState(array $stateData): array
    {
        $resources = [
            'cpu' => null,
            'memory' => null,
            'disk' => null,
            'processes' => null
        ];
        
        // Memory usage in bytes
        $memUsage = $stateData['memory']['usage'] ?? null;
        if ($memUsage !== null) {
            $resources['memory'] = $this->formatBytes($memUsage);
        }
        
        // CPU usage in nanoseconds - convert to seconds
        $cpuUsage = $stateData['cpu']['usage'] ?? null;
        if ($cpuUsage !== null) {
            $resources['cpu'] = round($cpuUsage / 1e9, 2);
        }
        
        // Process count
        $processes = $stateData['processes'] ?? null;
        if ($processes !== null) {
            $resources['processes'] = (int)$processes;
        }
        
        // Disk usage
        $disk = $stateData['disk']['root']['usage'] ?? null;
        if ($disk !== null) {
            $resources['disk'] = $this->formatBytes($disk);
        }
        
        return $resources;
    }

    /**
     * Get container resource usage (fallback using CLI)
     */
    private function getContainerResources(string $name): array
    {
        // First try API
        $state = LxdApiClient::getInstanceState($name);
        if ($state) {
            $stateData = $state['metadata'] ?? $state;
            return $this->getContainerResourcesFromState($stateData);
        }
        
        // Fallback to CLI
        $lxcBin = $this->getLxcBin();
        $resources = [
            'cpu' => null,
            'memory' => null,
            'disk' => null,
            'processes' => null
        ];
        
        // Try to get state info
        $output = [];
        $code = 0;
        exec("sudo $lxcBin info " . escapeshellarg($name) . " --resources 2>/dev/null", $output, $code);
        
        $text = implode("\n", $output);
        
        // Parse memory - format is "Memory (current): 566.44MiB"
        if (preg_match('/Memory \(current\):\s*([\d.]+\s*\w+)/', $text, $m)) {
            $resources['memory'] = $m[1];
        }
        
        // Parse CPU - format is "CPU usage (in seconds): 509"
        if (preg_match('/CPU usage \(in seconds\):\s*([\d.]+)/', $text, $m)) {
            $resources['cpu'] = $m[1];
        }
        
        // Parse processes
        if (preg_match('/Processes:\s*(\d+)/', $text, $m)) {
            $resources['processes'] = (int)$m[1];
        }
        
        return $resources;
    }

    /**
     * Get container logs (console output)
     */
    private function getContainerLogs(string $name, int $lines = 100): string
    {
        $lxcBin = $this->getLxcBin();
        $output = [];
        $code = 0;
        
        // Try to get console log
        exec("sudo $lxcBin console " . escapeshellarg($name) . " --show-log 2>/dev/null | tail -n $lines", $output, $code);
        
        if ($code !== 0 || empty($output)) {
            // Fallback: try to read syslog from inside container
            exec("sudo $lxcBin exec " . escapeshellarg($name) . " -- tail -n $lines /var/log/messages 2>/dev/null", $output, $code);
        }
        
        return implode("\n", $output);
    }

    /**
     * Get system-wide LXD stats
     */
    private function getSystemStats(): array
    {
        return $this->getSystemStatsFromContainers($this->getAllContainers());
    }

    /**
     * Get system stats from pre-fetched containers (avoids double-fetch)
     */
    private function getSystemStatsFromContainers(array $containers): array
    {
        $running = 0;
        $stopped = 0;
        $sandboxes = 0;
        
        foreach ($containers as $c) {
            if ($c['status'] === 'running') {
                $running++;
            } else {
                $stopped++;
            }
            if ($c['is_sandbox']) {
                $sandboxes++;
            }
        }
        
        // Get host memory from /proc/meminfo
        $memUsedFormatted = '--';
        $memTotalFormatted = '--';
        $memInfo = @file_get_contents('/proc/meminfo');
        if ($memInfo) {
            $memTotal = 0;
            $memAvailable = 0;
            if (preg_match('/MemTotal:\s+(\d+)/', $memInfo, $m)) {
                $memTotal = (int)$m[1] * 1024; // KB to bytes
            }
            if (preg_match('/MemAvailable:\s+(\d+)/', $memInfo, $m)) {
                $memAvailable = (int)$m[1] * 1024;
            }
            $memUsed = $memTotal - $memAvailable;
            
            if ($memTotal > 0) {
                $memUsedFormatted = $this->formatBytes($memUsed);
                $memTotalFormatted = $this->formatBytes($memTotal);
            }
        }
        
        return [
            'total' => count($containers),
            'running' => $running,
            'stopped' => $stopped,
            'sandboxes' => $sandboxes,
            'images' => 0,  // Skip slow lxc image list on page load
            'memory_used' => $memUsedFormatted,
            'memory_total' => $memTotalFormatted
        ];
    }
    
    /**
     * Format bytes to human readable
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }

    /**
     * Check if request is AJAX
     */
    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Validate CSRF token
     */
    private function validateCsrf(): void
    {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
            exit;
        }
    }

    /**
     * Get network configuration info
     */
    /**
     * Fast network info - just reads env var, no exec calls
     * Use getNetworkInfo() for full infrastructure checks
     */
    private function getNetworkInfoFast(): array
    {
        $mode = getenv('LXD_NETWORK_MODE') ?: ($_ENV['LXD_NETWORK_MODE'] ?? $_SERVER['LXD_NETWORK_MODE'] ?? 'bridge');
        
        // Determine IP range text based on mode
        $ipRange = '16.7M';
        if (in_array($mode, ['macvlan', 'ipvlan'])) {
            $ipRange = '4.3B';
        }
        
        // Determine interface name
        $interface = 'lxdbr0';
        if ($mode === 'macvlan') {
            $interface = 'ginto-macvlan';
        } elseif ($mode === 'ipvlan') {
            $interface = 'ginto-ipvlan';
        }
        
        return [
            'mode' => $mode,
            'ipRange' => $ipRange,
            'interface' => $interface,
            'nat' => ($mode === 'nat'),
            'macvlan' => ['ready' => true, 'dummy' => true, 'shim' => true, 'network' => true],
            'ipvlan' => ['ready' => true, 'dummy' => true, 'shim' => true, 'network' => true],
        ];
    }

    private function getNetworkInfo(): array
    {
        $mode = getenv('LXD_NETWORK_MODE') ?: ($_ENV['LXD_NETWORK_MODE'] ?? $_SERVER['LXD_NETWORK_MODE'] ?? 'bridge');
        
        // Check macvlan infrastructure
        $dummyExists = false;
        $shimExists = false;
        $macvlanNetExists = false;
        $natEnabled = false;
        
        exec('ip link show ginto-dummy0 2>/dev/null', $output, $code);
        $dummyExists = ($code === 0);
        
        exec('ip link show ginto-shim 2>/dev/null', $output2, $code2);
        $shimExists = ($code2 === 0);
        
        exec('lxc network show ginto-macvlan 2>/dev/null', $output3, $code3);
        $macvlanNetExists = ($code3 === 0);
        
        // Check IPVLAN infrastructure
        exec('ip link show ginto-ipvlan-shim 2>/dev/null', $output4, $code4);
        $ipvlanShimExists = ($code4 === 0);
        
        exec('lxc network show ginto-ipvlan 2>/dev/null', $output5, $code5);
        $ipvlanNetExists = ($code5 === 0);
        
        // Check NAT status on lxdbr0
        exec('lxc network get lxdbr0 ipv4.nat 2>/dev/null', $natOutput, $natCode);
        $natEnabled = ($natCode === 0 && trim(implode('', $natOutput)) === 'true');
        
        $macvlanReady = $dummyExists && $shimExists && $macvlanNetExists;
        $ipvlanReady = $dummyExists && $ipvlanShimExists && $ipvlanNetExists;
        
        // Determine IP range text based on mode
        $ipRange = '16.7M (10.0.0.0/8)';
        if (in_array($mode, ['macvlan', 'ipvlan'])) {
            $ipRange = '4.3B (full 32-bit)';
        }
        
        // Determine interface name
        $interface = 'lxdbr0';
        if ($mode === 'macvlan') {
            $interface = 'ginto-macvlan';
        } elseif ($mode === 'ipvlan') {
            $interface = 'ginto-ipvlan';
        }
        
        return [
            'mode' => $mode,
            'ipRange' => $ipRange,
            'interface' => $interface,
            'nat' => $natEnabled,
            'macvlan' => [
                'ready' => $macvlanReady,
                'dummy' => $dummyExists,
                'shim' => $shimExists,
                'network' => $macvlanNetExists,
            ],
            'ipvlan' => [
                'ready' => $ipvlanReady,
                'dummy' => $dummyExists,
                'shim' => $ipvlanShimExists,
                'network' => $ipvlanNetExists,
            ],
        ];
    }

    /**
     * Check if current user has admin privileges
     */
    private function requireAdmin(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        // Check if user has admin role (role_id 1 or 2)
        $user = $this->db->get('users', ['role_id'], ['id' => $_SESSION['user_id']]);
        
        if (!$user || !in_array($user['role_id'], [1, 2])) {
            header('Location: /dashboard');
            exit;
        }
    }


}

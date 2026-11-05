<?php
namespace Ginto\Controllers;

use Ginto\Core\Database;
use Core\Controller;

class SettingsController extends \Core\Controller
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? Database::getInstance();
        if (!isset($_SESSION['user_id'])) { header('Location: /login'); exit; }
        $user = $this->db->get('users', ['role_id'], ['id' => $_SESSION['user_id']]);
        if (!$user || !in_array($user['role_id'], [1, 2])) { http_response_code(403); echo '<h1>403 Forbidden</h1>'; exit; }
    }

    public function index()
    {
        // Minimal settings overview - could be replaced with dynamic content
        $settings = [];
        try { $settings = $this->db->select('settings', ['id','key','value']); } catch (\Throwable $e) {}
        $this->view('admin/settings/index', ['title' => 'Settings', 'settings' => $settings]);
    }

    /**
     * GET /admin/settings/icon-colors
     * Return the saved icon color mapping JSON (if present)
     */
    public function getIconColors()
    {
        try {
            $row = $this->db->get('settings', ['value'], ['key' => 'admin_icon_colors']);
            if ($row && isset($row['value'])) {
                header('Content-Type: application/json');
                echo $row['value'];
                return;
            }
        } catch (\Throwable $e) {}

        header('Content-Type: application/json');
        echo '{}';
    }

    /**
     * GET /admin/settings/routes
     * Return any saved routes settings JSON (if present). This allows the settings UI
     * to load server-stored routes settings and apply them to the client or edit them.
     */
    public function getRoutesSettings()
    {
        try {
            $row = $this->db->get('settings', ['value'], ['key' => 'routes_settings']);
            if ($row && isset($row['value'])) {
                header('Content-Type: application/json');
                echo $row['value'];
                return;
            }
        } catch (\Throwable $e) {}

        header('Content-Type: application/json');
        echo '{}';
    }

    /**
     * POST /admin/settings/save
     * Accepts JSON or form data with `icon_colors` payload (object mapping route->hex)
     */
    public function save()
    {
        // parse body
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) $input = $_POST;

        // Detect request type early (JSON/AJAX vs HTML form)
        $contentType = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $isJsonRequest = (stripos($contentType, 'application/json') !== false) || (stripos($accept, 'application/json') !== false) || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

        if (!isset($input['icon_colors']) && !isset($input['routes_settings'])) {
            if ($isJsonRequest) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Missing icon_colors']);
                return;
            }
            // HTML form submit -> redirect back with flash
            if (session_status() !== PHP_SESSION_ACTIVE) session_start();
            $_SESSION['flash_message'] = 'Missing icon_colors payload';
            header('Location: /admin/settings');
            exit;
        }

        // We support either icon_colors OR routes_settings being saved via this endpoint.
        if (isset($input['icon_colors'])) {
            $map = $input['icon_colors'];
            $settingKey = 'admin_icon_colors';
            $description = 'Sidebar icon color mappings';
            $group = 'appearance';
            $type = 'json';
        } else {
            $map = $input['routes_settings'];
            $settingKey = 'routes_settings';
            $description = 'Routes page settings (JSON)';
            $group = 'appearance';
            $type = 'json';
        }
        // support receiving either an array/object or a JSON string (form submission)
        if (!is_array($map) && is_string($map)) {
            $decoded = json_decode($map, true);
            if (is_array($decoded)) $map = $decoded;
        }

        if (!is_array($map)) {
            if ($isJsonRequest) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'icon_colors must be a JSON object']);
                return;
            }
            if (session_status() !== PHP_SESSION_ACTIVE) session_start();
            $_SESSION['flash_message'] = 'icon_colors must be a valid JSON object';
            header('Location: /admin/settings');
            exit;
        }

        $value = json_encode($map);
        try {
            $exists = $this->db->get('settings', 'id', ['key' => $settingKey]);
            if ($exists) {
                $this->db->update('settings', ['value' => $value, 'type' => $type], ['key' => $settingKey]);
            } else {
                $this->db->insert('settings', ['key' => $settingKey, 'value' => $value, 'type' => $type, 'group_name' => $group, 'description' => $description, 'is_public' => 0]);
            }

            // Determine whether caller expects JSON (AJAX/API call) or an HTML form submit
            $contentType = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
            $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
            $isJsonRequest = (stripos($contentType, 'application/json') !== false) || (stripos($accept, 'application/json') !== false) || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

            if ($isJsonRequest) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                return;
            }

            // Regular form POST — set a flash message and redirect back to settings page
            if (session_status() !== PHP_SESSION_ACTIVE) session_start();
            $_SESSION['flash_message'] = 'Settings saved.';
            header('Location: /admin/settings');
            exit;
        } catch (\Throwable $e) {
            if ($isJsonRequest) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'DB error']);
                return;
            }
            if (session_status() !== PHP_SESSION_ACTIVE) session_start();
            $_SESSION['flash_message'] = 'Failed to save settings (server error)';
            header('Location: /admin/settings');
            exit;
        }
    }
}

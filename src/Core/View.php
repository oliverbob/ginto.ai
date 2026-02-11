<?php
namespace Ginto\Core;

class View
{
    private string $viewsPath = ROOT_PATH . '/src/Views/';

    public function render(string $view, array $data = []): void
    {
        // Always include CSRF token unless explicitly set
        if (!isset($data['csrf_token'])) {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            if (empty($_SESSION['csrf_token'])) {
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
            $data['csrf_token'] = $_SESSION['csrf_token'];
        }
        extract($data);
        ob_start();
        // Always include BASE_URL helper for all views
        require_once ROOT_PATH . '/src/Core/UrlHelper.php';
        $viewFile = $this->viewsPath . $view . '.php';
        try {
            if (file_exists($viewFile)) {
                require $viewFile;
            } else {
                echo "Error: View file not found: " . htmlspecialchars($view);
            }
        } catch (\Throwable $e) {
            $logDir = defined('STORAGE_PATH') ? STORAGE_PATH . '/logs' : dirname(__DIR__, 3) . '/storage/logs';
            if (!is_dir($logDir)) @mkdir($logDir, 0777, true);
            $msg = "[" . date('Y-m-d H:i:s') . "] View render error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\n" . $e->getTraceAsString() . "\n";
            @file_put_contents($logDir . '/view-errors.log', $msg, FILE_APPEND);
                echo "An error occurred while rendering the page.";
        }
        $content = ob_get_clean();
        echo $content;
    }

    /**
     * Helper for static view rendering (for use outside View class)
     */
    public static function view(string $view, array $data = []): void
    {
        $instance = new self();
        $instance->render($view, $data);
    }
}
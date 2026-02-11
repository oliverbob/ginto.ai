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
        // Enable error display temporarily for debugging view rendering
        @ini_set('display_errors', '1');
        @error_reporting(E_ALL);
        // Always include BASE_URL helper for all views
        require_once ROOT_PATH . '/src/Core/UrlHelper.php';
        $viewFile = $this->viewsPath . $view . '.php';
        if (file_exists($viewFile)) {
            // Diagnostic markers to help detect where rendering stops
            echo "<!--VIEW-START-->";
            flush();
            try {
                require $viewFile;
            } catch (\Throwable $e) {
                $logDir = ROOT_PATH . '/storage/logs';
                if (!is_dir($logDir)) @mkdir($logDir, 0777, true);
                $msg = "[" . date('Y-m-d H:i:s') . "] View render error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\n" . $e->getTraceAsString() . "\n";
                @file_put_contents($logDir . '/view-errors.log', $msg, FILE_APPEND);
                echo "<!--VIEW-ERROR-->";
            }
            echo "<!--VIEW-END-->";
        } else {
            echo "Error: View file not found: " . htmlspecialchars($view);
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
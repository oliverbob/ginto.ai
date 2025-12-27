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
        if (file_exists($viewFile)) {
            require $viewFile;
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
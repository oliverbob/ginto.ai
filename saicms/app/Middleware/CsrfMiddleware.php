<?php

namespace App\Middleware;

class CsrfMiddleware
{
    /**
     * Handle an incoming request.
     *
     * This method verifies the CSRF token for all state-changing requests (POST, PUT, DELETE, etc.).
     */
    public function handle()
    {
        // We only need to check non-safe methods. GET, HEAD, OPTIONS are generally safe.
        if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'DELETE') {
            
            // Start the session if it's not already started
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }

            // Get the token stored in the session
            $sessionToken = $_SESSION['csrf_token'] ?? null;

            // Get the token from the incoming request.
            // We'll check for a POST field (for traditional forms) or an HTTP header (for AJAX/Fetch).
            $requestToken = $_POST['csrf_token'] ?? $this->getTokenFromHeader();

            if (!$sessionToken || !$requestToken || !hash_equals($sessionToken, $requestToken)) {
                // Token is invalid or missing.
                // We'll respond with a 403 Forbidden error.
                header('Content-Type: application/json', true, 403);
                echo json_encode([
                    'success' => false,
                    'error' => 'CSRF token validation failed. Please refresh the page and try again.'
                ]);
                exit; // Stop execution
            }
        }
    }

    /**
     * Get the token from the X-CSRF-TOKEN header.
     *
     * @return string|null
     */
    private function getTokenFromHeader(): ?string
    {
        // Headers can be prefixed with HTTP_ in the $_SERVER superglobal
        $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        return $header;
    }
}
<?php
namespace Ginto\Controllers;

/**
 * Simple test controller for route testing
 */
class TestController
{
    /**
     * Test endpoint - returns request info as JSON
     * Works with all HTTP methods (GET, POST, PUT, PATCH, DELETE)
     */
    public function test()
    {
        header('Content-Type: application/json');
        
        $method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
        
        echo json_encode([
            'success' => true,
            'message' => 'Route test successful!',
            'method' => $method,
            'timestamp' => date('c'),
            'note' => 'This route uses $router->req() - supports all HTTP methods with automatic CSRF'
        ], JSON_PRETTY_PRINT);
        exit;
    }
}

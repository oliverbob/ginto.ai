<?php

namespace Ginto\Handlers;

use Ginto\Database;

/**
 * ChatStreamHandler - Handles the streaming chat POST requests
 * 
 * This handler encapsulates the complex streaming logic for the /chat route,
 * including rate limiting, provider selection, conversation history, 
 * image handling, and SSE streaming.
 */
class ChatStreamHandler
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? Database::getInstance();
        
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
    }

    /**
     * Handle the streaming chat request (POST /chat)
     */
    public function handle(): void
    {
        // Include the extracted chat stream logic
        // This file contains all the POST handler logic from the original /chat route
        $db = $this->db;
        require __DIR__ . '/chat_stream_logic.php';
    }

    /**
     * Helper: Fix malformed code blocks for Parsedown.
     * Streaming chunks may produce ```php<?php without newline, breaking markdown parsing.
     */
    public static function fixCodeBlockNewlines(string $content): string
    {
        // Fix missing newline after language identifier (```php + opening tag -> ```php\n + opening tag)
        $content = preg_replace('/```([a-zA-Z0-9+#]+)(?!\n)/', "```$1\n", $content);
        
        $phpOpen = '<' . '?php';
        $phpClose = '?' . '>';
        
        // Fix opening tag immediately followed by comment - add space
        $content = preg_replace('/' . preg_quote($phpOpen, '/') . '(?=\/\/)/', $phpOpen . ' ', $content);
        
        // Fix opening tag immediately followed by code (not comment/space) - add newline
        $content = preg_replace('/' . preg_quote($phpOpen, '/') . '(?=[^\s\/])/', $phpOpen . "\n", $content);
        
        // Add missing closing tag to PHP code blocks
        // Match ```php ... ``` blocks that start with opening PHP tag but don't end with closing tag
        $content = preg_replace_callback(
            '/```php\s*(' . preg_quote($phpOpen, '/') . '[\s\S]*?)```/i',
            function($matches) use ($phpClose) {
                $code = $matches[1];
                // Check if it already ends with closing tag
                if (!preg_match('/' . preg_quote($phpClose, '/') . '\s*$/', trim($code))) {
                    // Add closing tag before closing fence
                    $code = rtrim($code) . "\n" . $phpClose;
                }
                return "```php\n" . $code . "\n```";
            },
            $content
        );
        
        return $content;
    }

    /**
     * Helper: Send SSE data chunk for chat streaming.
     */
    public static function sendSSE(string $content, $parsedown): void
    {
        if ($content === '') return;

        try {
            // Fix malformed code blocks before parsing
            $content = self::fixCodeBlockNewlines($content);
            if ($parsedown !== null) {
                $html = $parsedown->text($content);
            } else {
                $html = '<pre>' . htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>';
            }
            echo "data: " . json_encode(['html' => $html], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
            flush();
        } catch (\Throwable $_) {
            echo $content;
            flush();
        }
    }
}

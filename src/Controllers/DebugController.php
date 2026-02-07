<?php

namespace Ginto\Controllers;

use Ginto\Database;

/**
 * DebugController - Handles debug and diagnostic routes
 */
class DebugController
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? Database::getInstance();
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    }

    public function ipHeaders(): void
    {
        // Only allow admins
        if (empty($_SESSION['is_admin'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Admin only']);
            exit;
        }

        header('Content-Type: application/json');

        $relevantHeaders = [];
        $headerKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 
                       'HTTP_CLIENT_IP', 'HTTP_FORWARDED', 'REMOTE_ADDR'];

        foreach ($headerKeys as $key) {
            $relevantHeaders[$key] = $_SERVER[$key] ?? null;
        }

        echo json_encode([
            'detected_ip' => \Ginto\Helpers\TransactionHelper::getClientIp(),
            'display_ip' => \Ginto\Helpers\TransactionHelper::getDisplayIp(),
            'is_private' => \Ginto\Helpers\TransactionHelper::isPrivateIp(\Ginto\Helpers\TransactionHelper::getClientIp()),
            'headers' => $relevantHeaders
        ], JSON_PRETTY_PRINT);
        exit;
    }

    public function llm(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        $isAdmin = (!empty($_SESSION['role']) && $_SESSION['role'] === 'admin');
        $tokenHeader = $_SERVER['HTTP_X_GINTO_ADMIN_TOKEN'] ?? $_SERVER['HTTP_X_ADMIN_TOKEN'] ?? null;
        $expected = getenv('GINTO_ADMIN_TOKEN') ?: getenv('ADMIN_TOKEN');
        if (!$isAdmin && $expected && $tokenHeader && hash_equals((string)$expected, (string)$tokenHeader)) $isAdmin = true;

        // Allow localhost requests for debugging when not authenticated
        $remote = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!$isAdmin && $remote !== '127.0.0.1' && $remote !== '::1') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'unauthorized']);
            exit;
        }

        // Gather safe diagnostics (do NOT echo secret values)
        $vars = [
            'LLM_PROVIDER' => getenv('LLM_PROVIDER') ?: null,
            'LLM_MODEL' => getenv('LLM_MODEL') ?: null,
        ];
        $keys = [
            'GROQ_API_KEY' => (getenv('GROQ_API_KEY') ? true : false),
            'OPENAI_API_KEY' => (getenv('OPENAI_API_KEY') ? true : false),
            'ANTHROPIC_API_KEY' => (getenv('ANTHROPIC_API_KEY') ? true : false),
        ];

        // Which providers appear configured according to LLMProviderFactory
        $configured = [];
        try { $configured = \App\Core\LLM\LLMProviderFactory::getConfiguredProviders(); } catch (\Throwable $_) { $configured = []; }

        // Try to create a provider instance from env
        $provider_info = null;
        try {
            $prov = \App\Core\LLM\LLMProviderFactory::fromEnv();
            $provider_info = [
                'name' => $prov->getName(),
                'configured' => $prov->isConfigured(),
                'default_model' => $prov->getDefaultModel(),
                'available_models' => $prov->getModels(),
            ];

            if (!empty($_GET['save_session'])) {
                $_SESSION['llm_provider_name'] = $provider_info['name'];
                $_SESSION['llm_model'] = $provider_info['default_model'];
            }
        } catch (\Throwable $e) {
            $provider_info = ['error' => $e->getMessage()];
        }

        echo json_encode([
            'success' => true,
            'remote_addr' => $remote,
            'vars' => $vars,
            'api_keys_present' => $keys,
            'providers_configured' => $configured,
            'active_provider' => $provider_info,
            'session' => [
                'llm_provider_name' => $_SESSION['llm_provider_name'] ?? null,
                'llm_model' => $_SESSION['llm_model'] ?? null,
            ],
            'php_sapi' => php_sapi_name(),
        ], JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Debug-only: set session LLM provider/model (POST). Allowed from localhost only.
     * POST JSON: { "provider": "groq", "model": "meta-llama/llama-4-maverick-17b-128e-instruct" }
     */
    public function setSession(): void
    {
        header('Content-Type: application/json');
        if (session_status() !== PHP_SESSION_ACTIVE) @session_start();

        $remote = $_SERVER['REMOTE_ADDR'] ?? '';
        if ($remote !== '127.0.0.1' && $remote !== '::1') {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'forbidden']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $provider = $input['provider'] ?? null;
        $model = $input['model'] ?? null;

        if ($provider) $_SESSION['llm_provider_name'] = $provider;
        if ($model) $_SESSION['llm_model'] = $model;

        echo json_encode(['success' => true, 'provider' => $_SESSION['llm_provider_name'] ?? null, 'model' => $_SESSION['llm_model'] ?? null]);
        exit;
    }
}

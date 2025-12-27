<?php
/**
 * Chat Configuration Helper
 * 
 * Loads and provides access to chat.json configuration
 * Similar to VS Code's settings.json pattern
 */

namespace Ginto\Helpers;

class ChatConfig
{
    private static ?array $config = null;
    private static string $configPath = '';
    
    /**
     * Load configuration from chat.json
     */
    private static function load(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }
        
        // Find config file
        $root = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2);
        self::$configPath = $root . '/config/chat.json';
        
        if (!file_exists(self::$configPath)) {
            self::$config = self::getDefaults();
            return self::$config;
        }
        
        $json = file_get_contents(self::$configPath);
        $parsed = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('ChatConfig: Failed to parse chat.json: ' . json_last_error_msg());
            self::$config = self::getDefaults();
            return self::$config;
        }
        
        // Merge with defaults to ensure all keys exist
        self::$config = array_replace_recursive(self::getDefaults(), $parsed);
        return self::$config;
    }
    
    /**
     * Get default configuration values
     */
    private static function getDefaults(): array
    {
        return [
            'streaming' => [
                'renderMarkdownOnServer' => true,
            ],
            'rateLimit' => [
                'delayBetweenRequests' => 500,
            ],
            'agentPlan' => [
                'maxToolCallsPerPlan' => 10,
            ],
            'visitor' => [
                'maxPromptsPerHour' => 5,
            ],
            'tools' => [
                'sandbox_exec' => [
                    'requiresPremium' => true,
                ],
                'autoApproveDefault' => false,
            ],
        ];
    }
    
    /**
     * Get a configuration value using dot notation
     * 
     * @param string $key Dot-notation key like "streaming.renderMarkdownOnServer"
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        $config = self::load();
        $keys = explode('.', $key);
        $value = $config;
        
        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
    
    /**
     * Get the entire configuration array
     */
    public static function all(): array
    {
        return self::load();
    }
    
    /**
     * Reload configuration from disk
     */
    public static function reload(): void
    {
        self::$config = null;
        self::load();
    }
}

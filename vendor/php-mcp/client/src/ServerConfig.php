<?php

declare(strict_types=1);

namespace PhpMcp\Client;

use PhpMcp\Client\Enum\TransportType;
use PhpMcp\Client\Exception\ConfigurationException;
use ValueError; // For enum matching

/**
 * Value Object holding configuration for a single MCP Server connection.
 */
class ServerConfig
{
    /**
     * @param  string  $name  A unique identifier for this server configuration.
     * @param  TransportType  $transport  The transport type (Stdio or Http).
     * @param  float  $timeout  Default request timeout in seconds for this server.
     * @param  string|null  $command  The executable command (for Stdio). Required if transport is Stdio.
     * @param  array<int, string>  $args  Arguments for the command (for Stdio). Defaults to empty array.
     * @param  string|null  $url  Base URL for the MCP server (for Http). Required if transport is Http.
     * @param  string|null  $workingDir  Working directory for the command (for Stdio).
     * @param  array<string, string|false>|null  $env  Environment variables for the command (for Stdio). Allow false values for unsetting.
     * @param  array<string, string>|null  $headers  Additional HTTP headers (for Http).
     * @param  string|null  $sessionId  Optional externally managed session ID (for Http).
     */
    public function __construct(
        public readonly string $name,
        public readonly TransportType $transport,
        public readonly float $timeout = 30.0,
        // Stdio specific
        public readonly ?string $command = null,
        public readonly array $args = [],
        public readonly ?string $workingDir = null,
        public readonly ?array $env = null,
        // Http specific
        public readonly ?string $url = null,
        public readonly ?array $headers = null,
        public readonly ?string $sessionId = null,
    ) {
        if (empty($this->name)) {
            throw new ConfigurationException("Server configuration requires a non-empty 'name'.");
        }
        $this->validate();
    }

    /**
     * Creates a ServerConfig instance from an array, typically decoded from JSON.
     *
     * @param  string  $name  The unique name/key for this server configuration.
     * @param  array  $config  The configuration data array.
     *
     * @throws ConfigurationException If configuration is invalid.
     */
    public static function fromArray(string $name, array $config): self
    {
        $transportValue = $config['transport'] ?? null;
        if ($transportValue === null) {
            if (isset($config['url'])) {
                $transportValue = TransportType::Http->value;
            } elseif (isset($config['command'])) {
                $transportValue = TransportType::Stdio->value;
            } else {
                throw new ConfigurationException("Missing or ambiguous transport type for server '{$name}'. Specify 'transport', 'url', or 'command'.");
            }
        }
        if (! is_string($transportValue)) {
            throw new ConfigurationException("Invalid 'transport' value type for server '{$name}'. Expected string.");
        }
        try {
            $transport = TransportType::from(strtolower($transportValue));
        } catch (ValueError $e) {
            throw new ConfigurationException("Invalid transport type '{$transportValue}' specified for server '{$name}'. Must be 'stdio' or 'http'.", 0, $e);
        }

        $timeout = $config['timeout'] ?? 30.0;
        if (! is_numeric($timeout)) {
            throw new ConfigurationException("Invalid 'timeout' value type for server '{$name}'. Expected number.");
        }

        $command = null;
        $args = [];
        $workingDir = null;
        $env = null;
        $url = null;
        $headers = null;
        $sessionId = null;

        if ($transport === TransportType::Stdio) {
            $command = $config['command'] ?? null;

            if (! is_string($command) || empty($command)) {
                throw new ConfigurationException("Missing or invalid 'command' for stdio server '{$name}'.");
            }

            $args = $config['args'] ?? [];
            if (! is_array($args) || (! empty($args) && ! self::isStringList($args))) {
                throw new ConfigurationException("Invalid 'args' format for stdio server '{$name}'. Expected array of strings.");
            }

            $workingDir = $config['workingDir'] ?? $config['cwd'] ?? null;
            if ($workingDir !== null && ! is_string($workingDir)) {
                throw new ConfigurationException("Invalid 'workingDir' type for stdio server '{$name}'. Expected string or null.");
            }

            $env = $config['env'] ?? null;
            if ($env !== null && ! self::isStringMap($env)) {
                throw new ConfigurationException("Invalid 'env' format for stdio server '{$name}'. Expected map<string, string|false> or null.");
            }

        } elseif ($transport === TransportType::Http) {
            $url = $config['url'] ?? null;
            if (! is_string($url) || empty($url)) {
                throw new ConfigurationException("Missing or invalid 'url' for http server '{$name}'.");
            }

            $headers = $config['headers'] ?? null;
            if ($headers !== null && ! self::isStringMap($headers, false)) {
                throw new ConfigurationException("Invalid 'headers' format for http server '{$name}'. Expected map<string, string> or null.");
            }

            $sessionId = $config['sessionId'] ?? $config['session_id'] ?? null;
            if ($sessionId !== null && ! is_string($sessionId)) {
                throw new ConfigurationException("Invalid 'sessionId' type for http server '{$name}'. Expected string or null.");
            }
        }

        return new self(
            name: $name,
            transport: $transport,
            timeout: (float) $timeout,
            command: $command,
            args: $args,
            workingDir: $workingDir,
            env: $env,
            url: $url,
            headers: $headers,
            sessionId: $sessionId
        );
    }

    /** Helper to check if array is list<string> */
    private static function isStringList(array $arr): bool
    {
        if (array_is_list($arr)) {
            foreach ($arr as $value) {
                if (! is_string($value)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    /** Helper to check if array is map<string, string|false> */
    private static function isStringMap(array $arr, bool $allowFalseValues = true): bool
    {
        if (array_is_list($arr)) {
            return false;
        }
        foreach ($arr as $key => $value) {
            if (! is_string($key)) {
                return false;
            }
            if (! is_string($value) && ! ($allowFalseValues && $value === false)) {
                return false;
            }
        }

        return true;
    }

    private function validate(): void
    {
        if ($this->timeout <= 0) {
            throw new ConfigurationException("Server timeout must be positive for server '{$this->name}'.");
        }

        match ($this->transport) {
            TransportType::Stdio => $this->validateStdio(),
            TransportType::Http => $this->validateHttp(),
        };
    }

    private function validateStdio(): void
    {
        if (empty($this->command) || ! is_string($this->command)) {
            throw new ConfigurationException("The 'command' property is required for the Stdio transport for server '{$this->name}'.");
        }

        if (! is_array($this->args)) {
            // Should be caught by constructor/fromArray typing, but good check
            throw new ConfigurationException("Internal error: 'args' property must be an array for Stdio server '{$this->name}'.");
        }

        if ($this->url !== null) {
            throw new ConfigurationException("The 'url' property is not applicable for the Stdio transport for server '{$this->name}'.");
        }

        if ($this->headers !== null) {
            throw new ConfigurationException("The 'headers' property is not applicable for the Stdio transport for server '{$this->name}'.");
        }
    }

    private function validateHttp(): void
    {
        if (empty($this->url)) {
            throw new ConfigurationException("The 'url' property is required for the Http transport for server '{$this->name}'.");
        }

        if (filter_var($this->url, FILTER_VALIDATE_URL) === false && ! preg_match('/^https?:\/\/[^\s\/$.?#].[^\s]*$/iS', $this->url)) {
            throw new ConfigurationException("The 'url' property must be a valid URL for server '{$this->name}'.");
        }

        if ($this->command !== null) {
            throw new ConfigurationException("The 'command' property is not applicable for the Http transport for server '{$this->name}'.");
        }

        if ($this->args !== []) {
            throw new ConfigurationException("The 'args' property is not applicable for the Http transport for server '{$this->name}'.");
        }

        if ($this->workingDir !== null) {
            throw new ConfigurationException("The 'workingDir' property is not applicable for the Http transport for server '{$this->name}'.");
        }
        if ($this->env !== null) {
            throw new ConfigurationException("The 'env' property is not applicable for the Http transport for server '{$this->name}'.");
        }
    }
}

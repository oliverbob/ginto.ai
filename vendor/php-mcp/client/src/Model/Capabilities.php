<?php

declare(strict_types=1);

namespace PhpMcp\Client\Model;

use stdClass;

/**
 * Represents MCP capabilities for client or server.
 * Structure reflects the JSON representation in the MCP spec.
 */
final class Capabilities
{
    /** @var array{'listChanged'?: bool}|null */
    public ?array $roots = null;

    /** @var array{}|null Empty array indicates basic support */
    public ?array $sampling = null;

    /** @var array<string, mixed>|null Structure depends on feature */
    public ?array $experimental = null;

    /** @var array{'listChanged'?: bool}|null */
    public ?array $tools = null;

    /** @var array{'subscribe'?: bool, 'listChanged'?: bool}|null */
    public ?array $resources = null;

    /** @var array{'listChanged'?: bool}|null */
    public ?array $prompts = null;

    /** @var array{}|null Empty array indicates basic support */
    public ?array $logging = null;

    /**
     * Private constructor, use factory methods.
     */
    private function __construct() {}

    /**
     * Creates Capabilities object for client declaration.
     *
     * @param  bool  $supportsSampling  If true, declares basic support for server-initiated sampling.
     * @param  bool|null  $supportsRootListChanged  If true/false, declares 'roots' capability with listChanged. If null, 'roots' is omitted.
     * @param  array<string, mixed>|null  $experimental  Experimental capabilities declared by the client.
     */
    public static function forClient(
        bool $supportsSampling = true,
        ?bool $supportsRootListChanged = null,
        ?array $experimental = null
    ): self {
        $caps = new self;

        if ($supportsSampling) {
            $caps->sampling = [];
        }

        if ($supportsRootListChanged !== null) {
            $caps->roots = ['listChanged' => $supportsRootListChanged];
        }

        $caps->experimental = $experimental;

        return $caps;
    }

    /**
     * Creates Capabilities object from server response data.
     */
    public static function fromServerResponse(array $data): self
    {
        $caps = new self;
        $caps->prompts = isset($data['prompts']) && is_array($data['prompts']) ? $data['prompts'] : null;
        $caps->resources = isset($data['resources']) && is_array($data['resources']) ? $data['resources'] : null;
        $caps->tools = isset($data['tools']) && is_array($data['tools']) ? $data['tools'] : null;
        $caps->logging = isset($data['logging']) && is_array($data['logging']) ? $data['logging'] : null;
        $caps->experimental = isset($data['experimental']) && is_array($data['experimental']) ? $data['experimental'] : null;

        $caps->roots = null;
        $caps->sampling = null;

        return $caps;
    }

    /**
     * Converts client capabilities to array for initialize request.
     */
    public function toClientArray(): array|stdClass
    {
        $data = [];
        if ($this->roots !== null) {
            $data['roots'] = $this->roots;
        }
        if ($this->sampling !== null) {
            $data['sampling'] = empty($this->sampling) ? new stdClass : $this->sampling;
        }
        if ($this->experimental !== null) {
            $data['experimental'] = $this->experimental;
        }

        return empty($data) ? new stdClass : $data;
    }

    public function serverSupportsTools(): bool
    {
        return $this->tools !== null;
    }

    public function serverSupportsToolListChanged(): bool
    {
        return $this->tools['listChanged'] ?? false;
    }

    public function serverSupportsResources(): bool
    {
        return $this->resources !== null;
    }

    public function serverSupportsResourceSubscription(): bool
    {
        return $this->resources['subscribe'] ?? false;
    }

    public function serverSupportsResourceListChanged(): bool
    {
        return $this->resources['listChanged'] ?? false;
    }

    public function serverSupportsPrompts(): bool
    {
        return $this->prompts !== null;
    }

    public function serverSupportsPromptListChanged(): bool
    {
        return $this->prompts['listChanged'] ?? false;
    }

    public function serverSupportsLogging(): bool
    {
        return $this->logging !== null;
    }
}

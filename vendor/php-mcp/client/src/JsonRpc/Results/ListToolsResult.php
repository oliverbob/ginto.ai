<?php

namespace PhpMcp\Client\JsonRpc\Results;

use PhpMcp\Client\Exception\ProtocolException;
use PhpMcp\Client\JsonRpc\Result;
use PhpMcp\Client\Model\Definitions\ToolDefinition;

class ListToolsResult extends Result
{
    /**
     * @param  array<ToolDefinition>  $tools  The list of tool definitions.
     * @param  string|null  $nextCursor  The cursor for the next page, or null if this is the last page.
     */
    public function __construct(
        public readonly array $tools,
        public readonly ?string $nextCursor = null
    ) {}

    public static function fromArray(array $data): static
    {
        if (! isset($data['tools']) || ! is_array($data['tools'])) {
            throw new ProtocolException("Missing or invalid 'tools' array in ListToolsResult.");
        }

        $tools = [];
        foreach ($data['tools'] as $toolData) {
            $tools[] = ToolDefinition::fromArray($toolData);
        }

        return new static($tools, $data['nextCursor'] ?? null);
    }

    public function toArray(): array
    {
        $result = [
            'tools' => array_map(fn (ToolDefinition $t) => $t->toArray(), $this->tools),
        ];

        if ($this->nextCursor) {
            $result['nextCursor'] = $this->nextCursor;
        }

        return $result;
    }
}

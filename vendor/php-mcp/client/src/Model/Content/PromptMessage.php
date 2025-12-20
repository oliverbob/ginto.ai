<?php

declare(strict_types=1);

namespace PhpMcp\Client\Model\Content;

use PhpMcp\Client\Contracts\ContentInterface;
use PhpMcp\Client\Exception\ProtocolException;

class PromptMessage
{
    public function __construct(
        public readonly string $role,
        public readonly ContentInterface $content
    ) {
        if ($role !== 'user' && $role !== 'assistant') {
            throw new \InvalidArgumentException("Invalid role '{$role}', must be 'user' or 'assistant'.");
        }
    }

    /** @throws ProtocolException */
    public static function fromArray(array $data): self
    {
        if (empty($data['role']) || ! in_array($data['role'], ['user', 'assistant'])) {
            throw new ProtocolException("Missing or invalid 'role' in PromptMessage.");
        }
        if (empty($data['content']) || ! is_array($data['content'])) {
            throw new ProtocolException("Missing or invalid 'content' in PromptMessage.");
        }
        $content = ContentFactory::createFromArray($data['content']);

        return new self($data['role'], $content);
    }

    public function toArray(): array
    {
        return [
            'role' => $this->role,
            'content' => $this->content->toArray(),
        ];
    }
}

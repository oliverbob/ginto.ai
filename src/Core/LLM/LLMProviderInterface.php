<?php

declare(strict_types=1);

namespace App\Core\LLM;

/**
 * Interface for LLM providers.
 * 
 * This interface abstracts the differences between OpenAI-compatible APIs
 * (Groq, OpenAI, Together, etc.) and Anthropic-style APIs.
 * 
 * Tool calling conventions:
 * - OpenAI style: Uses 'tools' array with 'tool_calls' in response
 * - Anthropic style: Uses 'tools' array with 'tool_use' content blocks
 */
interface LLMProviderInterface
{
    /**
     * Get the provider name identifier.
     */
    public function getName(): string;

    /**
     * Get the provider style (openai or anthropic).
     */
    public function getStyle(): string;

    /**
     * Check if the provider is properly configured.
     */
    public function isConfigured(): bool;

    /**
     * Send a chat completion request.
     *
     * @param array $messages Normalized message array [{role, content, ...}]
     * @param array $tools Normalized tool definitions
     * @param array $options Additional options (temperature, max_tokens, etc.)
     * @return LLMResponse
     */
    public function chat(array $messages, array $tools = [], array $options = []): LLMResponse;

    /**
     * Send a streaming chat completion request.
     *
     * @param array $messages Normalized message array
     * @param array $tools Normalized tool definitions
     * @param array $options Additional options
     * @param callable $onChunk Callback for each chunk: fn(string $content, ?array $toolCall)
     * @return LLMResponse Final aggregated response
     */
    public function chatStream(array $messages, array $tools = [], array $options = [], callable $onChunk = null): LLMResponse;

    /**
     * Get available models for this provider.
     *
     * @return array List of model identifiers
     */
    public function getModels(): array;

    /**
     * Get the default model for this provider.
     */
    public function getDefaultModel(): string;

    /**
     * Set the model to use.
     */
    public function setModel(string $model): void;

    /**
     * Convert normalized tool format to provider-specific format.
     *
     * @param array $tools Normalized tools [{name, description, parameters}]
     * @return array Provider-specific tool format
     */
    public function formatTools(array $tools): array;

    /**
     * Convert normalized messages to provider-specific format.
     *
     * @param array $messages Normalized messages
     * @return array Provider-specific message format
     */
    public function formatMessages(array $messages): array;
}

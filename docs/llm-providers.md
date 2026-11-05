# LLM Provider Architecture

This document describes the standardized LLM provider architecture that supports multiple AI providers with a unified interface.

## Overview

The system uses a provider-agnostic architecture that abstracts the differences between:

- **OpenAI-compatible APIs**: OpenAI, Groq, Together AI, Fireworks AI, Cerebras
- **Anthropic Claude API**: Uses different message format and tool calling conventions
- **Ollama API**: Native Ollama format for local and Ollama Cloud

## Configuration

### Environment Variables

```bash
# Provider selection (auto-detected if not set)
LLM_PROVIDER=groq          # Options: openai, groq, anthropic, together, fireworks, ollama

# Model selection (defaults based on provider)
LLM_MODEL=llama-3.3-70b-versatile

# API Keys (set the ones you need)
GROQ_API_KEY=your_groq_api_key
OPENAI_API_KEY=your_openai_api_key
ANTHROPIC_API_KEY=your_anthropic_api_key
TOGETHER_API_KEY=your_together_api_key
FIREWORKS_API_KEY=your_fireworks_api_key
OLLAMA_API_KEY=your_ollama_cloud_key    # Only needed for Ollama Cloud
```

### Provider Detection Priority

If `LLM_PROVIDER` is not set, the system auto-detects based on available API keys:

1. Groq (if `GROQ_API_KEY` is set)
2. OpenAI (if `OPENAI_API_KEY` is set)
3. Anthropic (if `ANTHROPIC_API_KEY` is set)
4. Together (if `TOGETHER_API_KEY` is set)
5. Fireworks (if `FIREWORKS_API_KEY` is set)

## Available Models

### Groq (OpenAI-compatible)
- `llama-3.3-70b-versatile` (default)
- `llama-3.1-70b-versatile`
- `llama-3.1-8b-instant`
- `gemma2-9b-it`
- `mixtral-8x7b-32768`
- `deepseek-r1-distill-llama-70b`
- `meta-llama/llama-4-scout-17b-16e-instruct`
- `meta-llama/llama-4-maverick-17b-128e-instruct`

### OpenAI
- `gpt-4o` (default)
- `gpt-4o-mini`
- `gpt-4-turbo`
- `gpt-4`
- `gpt-3.5-turbo`
- `o1-preview`
- `o1-mini`

### Anthropic
- `claude-sonnet-4-20250514` (default)
- `claude-3-5-sonnet-20241022`
- `claude-3-5-haiku-20241022`
- `claude-3-opus-20240229`
- `claude-3-sonnet-20240229`
- `claude-3-haiku-20240307`

### Together AI (OpenAI-compatible)
- `meta-llama/Meta-Llama-3.1-70B-Instruct-Turbo` (default)
- `meta-llama/Meta-Llama-3.1-8B-Instruct-Turbo`
- `mistralai/Mixtral-8x7B-Instruct-v0.1`
- `Qwen/Qwen2.5-72B-Instruct-Turbo`

### Fireworks AI (OpenAI-compatible)
- `accounts/fireworks/models/llama-v3p1-70b-instruct` (default)
- `accounts/fireworks/models/llama-v3p1-8b-instruct`
- `accounts/fireworks/models/mixtral-8x7b-instruct`

### Ollama (Local or Cloud)
- `llama3.3` (cloud default)
- `llama3.2` (local default)
- `llama3.1`
- `mistral`
- `mixtral`
- `phi3`
- `gemma2`
- `qwen2.5`
- `deepseek-r1`
- `codellama`

> **Note**: Ollama models depend on what you've pulled locally or your cloud tier.
> - Local: `ollama pull llama3.2` 
> - Cloud: Uses models available on your Ollama Cloud plan

## Usage

### Using UnifiedMcpClient (Recommended)

```php
use App\Core\LLM\UnifiedMcpClient;
use App\Core\LLM\LLMProviderFactory;

// Auto-detect provider from environment
$client = new UnifiedMcpClient();

// Or specify a provider
$provider = LLMProviderFactory::create('anthropic');
$client = new UnifiedMcpClient($provider);

// Send a message (handles tool calling loop automatically)
$response = $client->chat("Create a file called hello.php with phpinfo()");

// Stream response
$client->chatStream("What files are in the repo?", function($chunk, $toolCall) {
    echo $chunk;
});

// Get provider info
$info = $client->getProviderInfo();

## Error handling & provider logs

To avoid exposing provider-specific errors or internal exception details to end-users, the UnifiedMcpClient and higher-level helper classes now mask provider/internal errors in user-facing responses. Detailed provider error payloads (including HTTP responses, provider error objects, and stack traces when available) are written to the system `activity_logs` table for administrators to review.

Admins can review these entries from the admin UI (e.g. `/admin/logs`) — the logs are scoped to admin-only and are intended for debugging and post-mortem analysis.
```

### Using StandardMcpHost (Legacy, Backward Compatible)

```php
use App\Core\StandardMcpHost;

$host = new StandardMcpHost();

// Uses unified architecture internally
$response = $host->chat("List all files in the repository");

// Check current provider
$info = $host->getProviderInfo();

// Switch provider at runtime
$provider = LLMProviderFactory::create('openai');
$host->setProvider($provider);
```

### Creating Custom Providers

```php
use App\Core\LLM\AbstractLLMProvider;
use App\Core\LLM\LLMResponse;

class CustomProvider extends AbstractLLMProvider
{
    public function getName(): string { return 'custom'; }
    public function getStyle(): string { return 'openai'; } // or 'anthropic'
    
    protected function getEnvApiKey(): ?string {
        return getenv('CUSTOM_API_KEY');
    }
    
    protected function getDefaultBaseUrl(): string {
        return 'https://api.custom.ai/v1/';
    }
    
    public function getDefaultModel(): string {
        return 'custom-model-v1';
    }
    
    public function getModels(): array {
        return ['custom-model-v1', 'custom-model-v2'];
    }
    
    public function chat(array $messages, array $tools = [], array $options = []): LLMResponse
    {
        // Implement chat logic
    }
}
```

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     UnifiedMcpClient                         │
│  ┌─────────────────┐  ┌─────────────────┐                  │
│  │  Tool Discovery  │  │ Tool Execution  │                  │
│  │   (McpUnifier)   │  │  (McpInvoker)   │                  │
│  └─────────────────┘  └─────────────────┘                  │
└────────────────────────────┬────────────────────────────────┘
                             │
                             ▼
┌─────────────────────────────────────────────────────────────┐
│                  LLMProviderInterface                       │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────┐   │
│  │   chat()     │  │  chatStream  │  │   formatTools    │   │
│  │              │  │     ()       │  │   formatMessages │   │
│  └──────────────┘  └──────────────┘  └──────────────────┘   │
└────────────────────────────┬────────────────────────────────┘
                             │
         ┌───────────────────┼───────────────────┐
         ▼                   ▼                   ▼
┌─────────────────┐  ┌───────────────┐  ┌───────────────────┐
│ OpenAI-         │  │   Anthropic   │  │    Custom         │
│ Compatible      │  │   Provider    │  │    Provider       │
│ Provider        │  │               │  │                   │
│ ───────────     │  │ ───────────── │  │ ───────────────── │
│ • OpenAI        │  │ • Claude      │  │ • Your API        │
│ • Groq          │  │               │  │                   │
│ • Together      │  │               │  │                   │
│ • Fireworks     │  │               │  │                   │
└─────────────────┘  └───────────────┘  └───────────────────┘
```

## Tool Calling Differences

### OpenAI-compatible Format (Groq, OpenAI, etc.)

Request:
```json
{
  "tools": [
    {
      "type": "function",
      "function": {
        "name": "repo/create_or_update_file",
        "description": "Create or update a file",
        "parameters": { "type": "object", "properties": {...} }
      }
    }
  ]
}
```

Response:
```json
{
  "choices": [{
    "message": {
      "tool_calls": [
        {
          "id": "call_123",
          "type": "function",
          "function": {
            "name": "repo/create_or_update_file",
            "arguments": "{\"file_path\": \"hello.php\", \"content\": \"<?php\\nphpinfo();\"}"
          }
        }
      ]
    }
  }]
}
```

### Anthropic Format

Request:
```json
{
  "tools": [
    {
      "name": "repo/create_or_update_file",
      "description": "Create or update a file",
      "input_schema": { "type": "object", "properties": {...} }
    }
  ]
}
```

Response:
```json
{
  "content": [
    {
      "type": "tool_use",
      "id": "tu_123",
      "name": "repo/create_or_update_file",
      "input": { "file_path": "hello.php", "content": "<?php\nphpinfo();" }
    }
  ]
}
```

## Files

### Core Classes

- `src/Core/LLM/LLMProviderInterface.php` - Provider interface
- `src/Core/LLM/LLMResponse.php` - Normalized response object
- `src/Core/LLM/AbstractLLMProvider.php` - Base provider class
- `src/Core/LLM/LLMProviderFactory.php` - Provider factory
- `src/Core/LLM/UnifiedMcpClient.php` - Unified MCP client

### Tool Standardization Classes

- `src/Core/LLM/ToolDefinition.php` - Provider-agnostic tool definition
- `src/Core/LLM/ToolCall.php` - Normalized tool call from LLM response
- `src/Core/LLM/ToolResult.php` - Normalized tool execution result

### Provider Implementations

- `src/Core/LLM/Providers/OpenAICompatibleProvider.php` - OpenAI/Groq/Together/Fireworks
- `src/Core/LLM/Providers/AnthropicProvider.php` - Anthropic Claude

## Tool Standardization

The system uses standardized classes to handle tool definitions, calls, and results across providers.

### ToolDefinition

Represents a tool/function that the LLM can call:

```php
use App\Core\LLM\ToolDefinition;

// Create from array
$tool = ToolDefinition::fromArray([
    'name' => 'create_file',
    'description' => 'Create a file in the repository',
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'path' => ['type' => 'string', 'description' => 'File path'],
            'content' => ['type' => 'string', 'description' => 'File content'],
        ],
        'required' => ['path', 'content'],
    ],
]);

// Convert to provider-specific format
$openaiFormat = $tool->toOpenAIFormat();   // { type: 'function', function: {...} }
$anthropicFormat = $tool->toAnthropicFormat(); // { name, description, input_schema }
```

### ToolCall

Represents a tool invocation request from the LLM:

```php
use App\Core\LLM\ToolCall;

// Parse from LLM response
$toolCalls = ToolCall::parseFromResponse($response, 'openai');
$toolCalls = ToolCall::parseFromResponse($response, 'anthropic');

// Create directly
$call = ToolCall::fromProvider('call_123', 'create_file', ['path' => 'test.txt']);

// Access properties
$name = $call->getName();
$args = $call->getArguments();
$path = $call->getArgument('path');
```

### ToolResult

Represents the result of executing a tool:

```php
use App\Core\LLM\ToolResult;

// Create from execution result
$result = ToolResult::success('call_123', ['file_created' => true]);
$result = ToolResult::error('call_123', 'File already exists');

// Convert to provider-specific message format
$openaiMsg = $result->toOpenAIMessage();     // { role: 'tool', tool_call_id, content }
$anthropicMsg = $result->toAnthropicMessage(); // { role: 'user', content: [{ type: 'tool_result', ... }] }

// Or auto-detect from provider
$msg = $result->toAssistantMessage($provider);
```

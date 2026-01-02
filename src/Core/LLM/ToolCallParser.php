<?php

declare(strict_types=1);

namespace App\Core\LLM;

/**
 * ToolCallParser: Extracts tool calls from AI/user messages (JSON, XML-like, etc.)
 *
 * Usage:
 *   $toolCall = ToolCallParser::extract($message);
 *   if ($toolCall) { ... }
 */
final class ToolCallParser
{
    /**
     * Extract a tool call from a message string.
     * Supports JSON, XML-like, and function-call formats.
     * Returns [ 'name' => ..., 'arguments' => [...] ] or null.
     */
    public static function extract(string $s): ?array
    {
        $trimmed = trim($s);
        if ($trimmed === '') return null;

        $toolCall = null;
        // XML-like: <function>name</function>{...}
        if (preg_match('/<function>\s*(\w+)\s*<\/function>\s*([\{\[].*[\}\]])/is', $s, $m)) {
            $name = $m[1];
            $args = self::tryParseJsonSafe($m[2]);
            if (is_array($args)) $toolCall = [ 'name' => $name, 'arguments' => $args ];
        }
        // XML-like: <function>name{...}</function>
        if (!$toolCall && preg_match('/<function>\s*(\w+)\s*([\{\[].*[\}\]])\s*<\/function>/is', $s, $m)) {
            $name = $m[1];
            $args = self::tryParseJsonSafe($m[2]);
            if (is_array($args)) $toolCall = [ 'name' => $name, 'arguments' => $args ];
        }
        // JSON object with tool_call, tool_calls, function_call, or tool
        if (!$toolCall && $trimmed[0] === '{') {
            $j = self::tryParseJsonSafe($trimmed);
            if (is_array($j)) {
                // Direct format: {"name": "...", "arguments": {...}} (from streaming callback)
                if (isset($j['name']) && is_string($j['name']) && isset($j['arguments'])) {
                    $toolCall = [
                        'name' => $j['name'],
                        'arguments' => is_array($j['arguments']) ? $j['arguments'] : self::tryParseJsonSafe($j['arguments'])
                    ];
                }
                elseif (isset($j['tool_call'])) $toolCall = $j['tool_call'];
                elseif (isset($j['tool_calls']) && is_array($j['tool_calls']) && count($j['tool_calls'])) $toolCall = $j['tool_calls'][0];
                elseif (isset($j['function_call'])) {
                    $fc = $j['function_call'];
                    $toolCall = [ 'name' => $fc['name'] ?? null, 'arguments' => self::tryParseJsonSafe($fc['arguments'] ?? []) ];
                }
                elseif (isset($j['tool'])) {
                    $t = $j['tool'];
                    $toolCall = [ 'name' => $t['name'] ?? $t, 'arguments' => $t['arguments'] ?? [] ];
                }
            }
        }
        // Fallback: try to find a JSON object with "name" and "arguments"
        if (!$toolCall && preg_match('/\{[^}]*"name"\s*:\s*"([^"]+)"[^}]*"arguments"\s*:\s*(\{[\s\S]*\})/i', $s, $m)) {
            $name = $m[1];
            $args = self::tryParseJsonSafe($m[2]);
            if (is_array($args)) $toolCall = [ 'name' => $name, 'arguments' => $args ];
        }

        // Strict validation for repo/create_or_update_file
        if ($toolCall && $toolCall['name'] === 'repo/create_or_update_file') {
            $args = $toolCall['arguments'] ?? [];
            if (!isset($args['file_path']) || !is_string($args['file_path']) || $args['file_path'] === '' ||
                !isset($args['content']) || !is_string($args['content'])) {
                return null;
            }
        }
        // Strict validation for compose_project
        if ($toolCall && $toolCall['name'] === 'compose_project') {
            $args = $toolCall['arguments'] ?? [];
            if (!isset($args['files']) || !is_array($args['files'])) {
                return null;
            }
        }
        return $toolCall;
    }

    private static function tryParseJsonSafe($s)
    {
        if (!is_string($s)) return $s;
        $s = trim($s);
        if ($s === '') return null;
        try { return json_decode($s, true, 512, JSON_THROW_ON_ERROR); } catch (\Throwable $e) {}
        // Try to fix common issues: single quotes, trailing commas
        $fixed = preg_replace(["/'(.*?)'/", '/,\s*}/', '/,\s*]/'], ['"$1"', '}', ']'], $s);
        try { return json_decode($fixed, true, 512, JSON_THROW_ON_ERROR); } catch (\Throwable $e) {}
        return null;
    }
}

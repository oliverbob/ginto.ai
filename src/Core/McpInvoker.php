<?php

declare(strict_types=1);

namespace App\Core;

use ReflectionClass;
use ReflectionMethod;

/**
 * Simple in-process MCP invoker.
 *
 * Scans classes under `App\Handlers` for methods annotated with
 * `\PhpMcp\Server\Attributes\McpTool(name: "...")` and invokes the
 * matching method with provided args.
 */
final class McpInvoker
{
    /**
     * Invoke a named tool implemented as a PHP handler method.
     *
     * @param string $toolName e.g. "github/create_or_update_file"
     * @param mixed $args Associative array (name->value) or numeric array for positional args
     * @return mixed The raw return value from the handler
     * @throws \RuntimeException on lookup/invocation errors
     */
    public static function invoke(string $toolName, $args = [])
    {
        // Ensure handlers are loaded via autoload (composer autoload should already be active)
        $found = null;

        foreach (get_declared_classes() as $class) {
            if (str_starts_with($class, 'App\\Handlers\\') || str_starts_with($class, 'Ginto\\Handlers\\')) {
                try {
                    $rc = new ReflectionClass($class);
                } catch (\ReflectionException $e) { continue; }

                foreach ($rc->getMethods(ReflectionMethod::IS_PUBLIC) as $m) {
                    // Skip inherited methods
                    if ($m->getDeclaringClass()->getName() !== $class) continue;
                    $attrs = $m->getAttributes('PhpMcp\\Server\\Attributes\\McpTool');
                    foreach ($attrs as $a) {
                        $inst = $a->newInstance();
                        // Some attributes expose 'name' property
                        $name = $inst->name ?? null;
                        if (!$name && isset($inst->arguments) && is_array($inst->arguments)) {
                            $name = $inst->arguments['name'] ?? null;
                        }
                        if ($name === $toolName) {
                            $found = ['class' => $class, 'method' => $m->getName()];
                            break 3;
                        }
                    }
                }
            }
        }

        if ($found === null) {
            throw new \RuntimeException('Tool not found: ' . $toolName);
        }

        // Prepare invocation
        $rc = new ReflectionClass($found['class']);
        $instance = $rc->newInstance();
        $method = $rc->getMethod($found['method']);
        $params = $method->getParameters();

        $callArgs = [];
        // If args is associative (array with string keys) map by name,
        // otherwise treat as positional list.
        $isAssoc = is_array($args) && count(array_filter(array_keys($args), 'is_string')) > 0;

        foreach ($params as $i => $p) {
            $pname = $p->getName();
            if ($isAssoc) {
                if (array_key_exists($pname, $args)) {
                    $callArgs[] = $args[$pname];
                    continue;
                }
                // fallback to default value if available
                if ($p->isDefaultValueAvailable()) {
                    $callArgs[] = $p->getDefaultValue();
                    continue;
                }
                // missing named param
                throw new \RuntimeException('Missing parameter ' . $pname . ' for tool ' . $toolName);
            } else {
                // positional: if provided, use it; else default if available
                if (is_array($args) && array_key_exists($i, $args)) {
                    $callArgs[] = $args[$i];
                    continue;
                }
                if ($p->isDefaultValueAvailable()) {
                    $callArgs[] = $p->getDefaultValue();
                    continue;
                }
                throw new \RuntimeException('Missing positional parameter ' . $i . ' (' . $pname . ') for tool ' . $toolName);
            }
        }

        // Call the method
        try {
            return $method->invokeArgs($instance, $callArgs);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Tool invocation failed: ' . $e->getMessage(), 0, $e);
        }
    }
}

<?php

namespace Core;

class Router
{
    protected $routes = [];
    protected $middlewares = [];      // To store registered middleware callbacks
    protected $currentGroupMiddleware = []; // To track middleware for the current group

    private function normalizeUri($uri)
    {
        $uri = trim($uri, '/');
        return '/' . ($uri ?: '');
    }

    /**
     * Registers a named middleware.
     * @param string $name The short name for the middleware (e.g., 'csrf').
     * @param callable $callback The middleware logic (e.g., ['App\Middleware\CsrfMiddleware', 'handle']).
     */
    public function filter($name, $callback)
    {
        $this->middlewares[$name] = $callback;
    }

    /**
     * Creates a route group with shared attributes, like middleware.
     * @param array $attributes An array containing group properties (e.g., ['before' => 'csrf']).
     * @param callable $callback A function that defines the routes within the group.
     */
    public function group(array $attributes, callable $callback)
    {
        // Get middleware from the 'before' key and push it onto a stack
        $middleware = $attributes['before'] ?? [];
        $this->currentGroupMiddleware[] = $middleware;

        // Run the callback to define the routes inside the group
        $callback($this);

        // Pop the middleware off the stack once the group is finished
        array_pop($this->currentGroupMiddleware);
    }

    public function get($uri, $callback)
    {
        $this->addRoute('GET', $uri, $callback);
    }

    public function post($uri, $callback)
    {
        $this->addRoute('POST', $uri, $callback);
    }

    protected function addRoute($method, $uri, $callback)
    {
        // Get middleware from the current group, if any
        $middleware = end($this->currentGroupMiddleware) ?: [];

        // We store the route as an array containing the action and its middleware
        $this->routes[$method][$this->normalizeUri($uri)] = [
            'action' => $callback,
            'middleware' => (array) $middleware // Ensure it's always an array
        ];
    }

    protected function compileRoute($routeUri)
    {
        $pattern = str_replace('/', '\/', $routeUri);
        $pattern = preg_replace_callback(
            '/\{(\w+)(?::([^}]+))?\}/',
            function ($matches) {
                $paramName = $matches[1];
                $customRegex = isset($matches[2]) ? $matches[2] : '[^/]+';
                return '(?P<' . $paramName . '>' . $customRegex . ')';
            },
            $pattern
        );
        return "#^" . $pattern . "$#";
    }

    public function dispatch($requestUri)
    {
        $parsedUri = parse_url($requestUri, PHP_URL_PATH);
        $currentUri = $this->normalizeUri($parsedUri ?: '/');
        $method = $_SERVER['REQUEST_METHOD'];

        if (!isset($this->routes[$method])) {
            $this->handleError(405, "Method Not Allowed: No routes for {$method}.");
            return;
        }

        foreach ($this->routes[$method] as $definedRouteUri => $routeData) {
            $routePattern = $this->compileRoute($definedRouteUri);

            if (preg_match($routePattern, $currentUri, $matches)) {
                // --- NEW: Run middleware before dispatching to the controller ---
                foreach ($routeData['middleware'] as $middlewareName) {
                    if (isset($this->middlewares[$middlewareName])) {
                        $middlewareCallback = $this->middlewares[$middlewareName];
                        if (is_array($middlewareCallback) && class_exists($middlewareCallback[0])) {
                            $instance = new $middlewareCallback[0]();
                            call_user_func([$instance, $middlewareCallback[1]]);
                        }
                    }
                }
                // --- End of middleware logic ---

                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $callback = $routeData['action'];

                if (is_string($callback) && strpos($callback, '@') !== false) {
                    $this->invokeControllerAction($callback, $params);
                } elseif (is_callable($callback)) {
                    call_user_func_array($callback, array_values($params));
                } else {
                    $this->handleError(500, "Invalid callback for route {$definedRouteUri}.");
                }
                return;
            }
        }
        $this->handleError(404, "Page Not Found: No route matches {$currentUri}.");
    }

    protected function invokeControllerAction($callbackString, $params)
    {
        list($controllerName, $methodName) = explode('@', $callbackString, 2);
        $controllerClass = "App\\Controllers\\" . $controllerName;

        if (!class_exists($controllerClass)) {
            $this->handleError(500, "Controller class {$controllerClass} not found.");
            return;
        }

        $controllerInstance = new $controllerClass();

        if (!method_exists($controllerInstance, $methodName)) {
            $this->handleError(500, "Method {$methodName} not found in controller {$controllerClass}.");
            return;
        }
        call_user_func_array([$controllerInstance, $methodName], array_values($params));
    }

    protected function handleError($statusCode, $message)
    {
        http_response_code($statusCode);
        echo htmlspecialchars($message);
    }
}
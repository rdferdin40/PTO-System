<?php
/**
 * Router Class
 * Handles URL routing and middleware
 */

class Router
{
    private array $routes = [];
    private array $middleware = [];
    private array $groupMiddleware = [];

    /**
     * Add GET route
     */
    public function get(string $path, $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    /**
     * Add POST route
     */
    public function post(string $path, $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    /**
     * Add PUT route
     */
    public function put(string $path, $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    /**
     * Add DELETE route
     */
    public function delete(string $path, $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    /**
     * Add route for any method
     */
    public function any(string $path, $handler, array $middleware = []): void
    {
        foreach (['GET', 'POST', 'PUT', 'DELETE', 'PATCH'] as $method) {
            $this->addRoute($method, $path, $handler, $middleware);
        }
    }

    /**
     * Add route
     */
    private function addRoute(string $method, string $path, $handler, array $middleware = []): void
    {
        // Merge group middleware with route middleware
        $allMiddleware = array_merge($this->groupMiddleware, $middleware);

        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler,
            'middleware' => $allMiddleware,
            'regex' => $this->pathToRegex($path)
        ];
    }

    /**
     * Convert path to regex pattern
     */
    private function pathToRegex(string $path): string
    {
        // Replace :param with named capture groups
        $pattern = preg_replace('/\/:([a-zA-Z0-9_]+)/', '/(?P<$1>[^/]+)', $path);

        // Escape forward slashes and add delimiters
        $pattern = '#^' . $pattern . '$#';

        return $pattern;
    }

    /**
     * Route group with middleware
     */
    public function group(array $middleware, callable $callback): void
    {
        $previousMiddleware = $this->groupMiddleware;
        $this->groupMiddleware = array_merge($this->groupMiddleware, $middleware);

        $callback($this);

        $this->groupMiddleware = $previousMiddleware;
    }

    /**
     * Dispatch request
     */
    public function dispatch(Request $request): Response
    {
        foreach ($this->routes as $route) {
            // Check if method matches
            if ($route['method'] !== $request->getMethod()) {
                continue;
            }

            // Check if path matches
            if (preg_match($route['regex'], $request->getPath(), $matches)) {
                // Extract URL parameters
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $request->setParams($params);

                // Run middleware
                foreach ($route['middleware'] as $middlewareClass) {
                    $middleware = new $middlewareClass();
                    $middlewareResponse = $middleware->handle($request);

                    // If middleware returns a response, return it immediately
                    if ($middlewareResponse instanceof Response) {
                        return $middlewareResponse;
                    }
                }

                // Call handler
                return $this->callHandler($route['handler'], $request);
            }
        }

        // No route found
        return Response::notFound();
    }

    /**
     * Call route handler
     */
    private function callHandler($handler, Request $request): Response
    {
        if (is_callable($handler)) {
            // Handler is a closure
            $result = $handler($request);
        } elseif (is_string($handler) && strpos($handler, '@') !== false) {
            // Handler is "ControllerClass@method"
            [$controllerClass, $method] = explode('@', $handler);

            if (!class_exists($controllerClass)) {
                throw new Exception("Controller $controllerClass not found");
            }

            $controller = new $controllerClass();

            if (!method_exists($controller, $method)) {
                throw new Exception("Method $method not found in controller $controllerClass");
            }

            $result = $controller->$method($request);
        } else {
            throw new Exception("Invalid route handler");
        }

        // Convert result to Response if needed
        if ($result instanceof Response) {
            return $result;
        } elseif (is_array($result)) {
            return Response::json($result);
        } elseif (is_string($result)) {
            return new Response($result);
        } else {
            return new Response('');
        }
    }

    /**
     * Get all routes (for debugging)
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}

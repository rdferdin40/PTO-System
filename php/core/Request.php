<?php
/**
 * HTTP Request Class
 * Handles incoming HTTP requests
 */

class Request
{
    public string $method;
    public string $uri;
    public string $path;
    public array $query;
    public array $post;
    public array $files;
    public array $cookies;
    public array $server;
    public array $headers;
    public ?string $body;
    public array $params = []; // URL parameters from router

    private function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->uri = $_SERVER['REQUEST_URI'] ?? '/';
        $this->query = $_GET;
        $this->post = $_POST;
        $this->files = $_FILES;
        $this->cookies = $_COOKIE;
        $this->server = $_SERVER;
        $this->headers = $this->parseHeaders();
        $this->body = file_get_contents('php://input');

        // Parse path from URI (remove query string)
        $this->path = parse_url($this->uri, PHP_URL_PATH) ?? '/';

        // Remove script name from path if present
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $scriptDir = dirname($scriptName);
        if ($scriptDir !== '/' && strpos($this->path, $scriptDir) === 0) {
            $this->path = substr($this->path, strlen($scriptDir));
        }

        // Ensure path starts with /
        if (empty($this->path) || $this->path[0] !== '/') {
            $this->path = '/' . $this->path;
        }
    }

    /**
     * Create request from PHP globals
     */
    public static function createFromGlobals(): self
    {
        return new self();
    }

    /**
     * Parse HTTP headers
     */
    private function parseHeaders(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $header = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$header] = $value;
            }
        }

        return $headers;
    }

    /**
     * Get request method
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Get request path
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get query parameter
     */
    public function query(string $key, $default = null)
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * Get POST parameter
     */
    public function post(string $key, $default = null)
    {
        return $this->post[$key] ?? $default;
    }

    /**
     * Get input (POST or query)
     */
    public function input(string $key, $default = null)
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    /**
     * Get all input data
     */
    public function all(): array
    {
        return array_merge($this->query, $this->post);
    }

    /**
     * Check if request has input
     */
    public function has(string $key): bool
    {
        return isset($this->post[$key]) || isset($this->query[$key]);
    }

    /**
     * Get URL parameter (from router)
     */
    public function param(string $key, $default = null)
    {
        return $this->params[$key] ?? $default;
    }

    /**
     * Set URL parameters (called by router)
     */
    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    /**
     * Get header
     */
    public function header(string $key, $default = null)
    {
        return $this->headers[$key] ?? $default;
    }

    /**
     * Get cookie
     */
    public function cookie(string $key, $default = null)
    {
        return $this->cookies[$key] ?? $default;
    }

    /**
     * Check if request is AJAX
     */
    public function isAjax(): bool
    {
        return strtolower($this->header('X-Requested-With', '')) === 'xmlhttprequest';
    }

    /**
     * Check if request is JSON
     */
    public function isJson(): bool
    {
        return strpos($this->header('Content-Type', ''), 'application/json') !== false;
    }

    /**
     * Get JSON body
     */
    public function json(): ?array
    {
        if ($this->isJson()) {
            return json_decode($this->body, true);
        }
        return null;
    }

    /**
     * Get client IP address
     */
    public function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Get user agent
     */
    public function userAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    /**
     * Check if request method matches
     */
    public function isMethod(string $method): bool
    {
        return strtoupper($this->method) === strtoupper($method);
    }

    /**
     * Validate input
     */
    public function validate(array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $rule) {
            $value = $this->input($field);
            $ruleArray = is_array($rule) ? $rule : explode('|', $rule);

            foreach ($ruleArray as $r) {
                if ($r === 'required' && empty($value)) {
                    $errors[$field][] = "$field is required";
                }
                if ($r === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field][] = "$field must be a valid email";
                }
                if (strpos($r, 'min:') === 0) {
                    $min = (int)substr($r, 4);
                    if (strlen($value) < $min) {
                        $errors[$field][] = "$field must be at least $min characters";
                    }
                }
                if (strpos($r, 'max:') === 0) {
                    $max = (int)substr($r, 4);
                    if (strlen($value) > $max) {
                        $errors[$field][] = "$field must not exceed $max characters";
                    }
                }
            }
        }

        return $errors;
    }
}

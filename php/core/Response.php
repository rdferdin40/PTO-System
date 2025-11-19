<?php
/**
 * HTTP Response Class
 * Handles HTTP responses
 */

class Response
{
    private string $content = '';
    private int $statusCode = 200;
    private array $headers = [];

    public function __construct(string $content = '', int $statusCode = 200, array $headers = [])
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    /**
     * Set response content
     */
    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    /**
     * Set status code
     */
    public function setStatusCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    /**
     * Set header
     */
    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Send response
     */
    public function send(): void
    {
        // Send status code
        http_response_code($this->statusCode);

        // Send headers
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }

        // Send content
        echo $this->content;
    }

    /**
     * Create JSON response
     */
    public static function json($data, int $statusCode = 200): self
    {
        $content = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return new self($content, $statusCode, [
            'Content-Type' => 'application/json; charset=utf-8'
        ]);
    }

    /**
     * Create redirect response
     */
    public static function redirect(string $url, int $statusCode = 302): self
    {
        return new self('', $statusCode, [
            'Location' => $url
        ]);
    }

    /**
     * Create view response
     */
    public static function view(string $template, array $data = [], int $statusCode = 200): self
    {
        $view = new View();
        $content = $view->render($template, $data);

        return new self($content, $statusCode, [
            'Content-Type' => 'text/html; charset=utf-8'
        ]);
    }

    /**
     * Create download response
     */
    public static function download(string $content, string $filename, string $contentType = 'application/octet-stream'): self
    {
        return new self($content, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => (string)strlen($content)
        ]);
    }

    /**
     * Create error response
     */
    public static function error(string $message, int $statusCode = 500): self
    {
        if (CONFIG['app']['debug']) {
            $content = "<h1>Error $statusCode</h1><p>" . htmlspecialchars($message) . "</p>";
        } else {
            $content = "<h1>Error $statusCode</h1>";
        }

        return new self($content, $statusCode, [
            'Content-Type' => 'text/html; charset=utf-8'
        ]);
    }

    /**
     * Create 404 response
     */
    public static function notFound(string $message = 'Page not found'): self
    {
        return self::error($message, 404);
    }

    /**
     * Create 403 response
     */
    public static function forbidden(string $message = 'Forbidden'): self
    {
        return self::error($message, 403);
    }

    /**
     * Create 401 response
     */
    public static function unauthorized(string $message = 'Unauthorized'): self
    {
        return self::error($message, 401);
    }
}

<?php
/**
 * View Rendering Class
 * Simple PHP template engine
 */

class View
{
    private array $data = [];
    private string $layout = 'layout';

    /**
     * Render template
     */
    public function render(string $template, array $data = []): string
    {
        $this->data = array_merge($this->data, $data);

        // Add global view data
        $this->data['_csrf_token'] = Session::getCsrfToken();
        $this->data['_flashes'] = Session::getFlashes();
        $this->data['_user'] = $this->getCurrentUser();
        $this->data['_session'] = $_SESSION ?? [];

        // Get template content
        $content = $this->renderTemplate($template, $this->data);

        // Wrap in layout if specified
        if (isset($this->data['layout']) && $this->data['layout'] === false) {
            return $content;
        }

        $layoutName = $this->data['layout'] ?? $this->layout;
        return $this->renderLayout($layoutName, ['content' => $content] + $this->data);
    }

    /**
     * Render template file
     */
    private function renderTemplate(string $template, array $data): string
    {
        $templateFile = VIEW_PATH . '/' . $template . '.php';

        if (!file_exists($templateFile)) {
            throw new Exception("Template not found: $template");
        }

        extract($data);
        ob_start();
        include $templateFile;
        return ob_get_clean();
    }

    /**
     * Render layout
     */
    private function renderLayout(string $layout, array $data): string
    {
        $layoutFile = VIEW_PATH . '/layouts/' . $layout . '.php';

        if (!file_exists($layoutFile)) {
            // No layout, return content as-is
            return $data['content'];
        }

        extract($data);
        ob_start();
        include $layoutFile;
        return ob_get_clean();
    }

    /**
     * Get current authenticated user
     */
    private function getCurrentUser(): ?array
    {
        $userId = Session::getUserId();

        if ($userId) {
            try {
                require_once APP_PATH . '/Models/User.php';
                return User::find($userId);
            } catch (Exception $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * Escape HTML
     */
    public static function e($value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }

    /**
     * Include partial
     */
    public static function partial(string $name, array $data = []): void
    {
        $file = VIEW_PATH . '/partials/' . $name . '.php';

        if (file_exists($file)) {
            extract($data);
            include $file;
        }
    }

    /**
     * Format date
     */
    public static function formatDate($date, string $format = 'Y-m-d'): string
    {
        if ($date instanceof DateTime) {
            return $date->format($format);
        }

        if (is_string($date)) {
            try {
                return (new DateTime($date))->format($format);
            } catch (Exception $e) {
                return $date;
            }
        }

        return '';
    }

    /**
     * Format date for humans
     */
    public static function humanDate($date): string
    {
        if ($date instanceof DateTime) {
            $dt = $date;
        } elseif (is_string($date)) {
            try {
                $dt = new DateTime($date);
            } catch (Exception $e) {
                return $date;
            }
        } else {
            return '';
        }

        return $dt->format('F j, Y');
    }

    /**
     * Old input (for form repopulation after validation errors)
     */
    public static function old(string $key, $default = '')
    {
        $oldInput = Session::get('old_input', []);
        return $oldInput[$key] ?? $default;
    }

    /**
     * Get validation errors
     */
    public static function errors(string $key = null)
    {
        $errors = Session::flash('errors') ?? [];

        if ($key) {
            return $errors[$key] ?? [];
        }

        return $errors;
    }

    /**
     * Check if field has error
     */
    public static function hasError(string $key): bool
    {
        $errors = Session::flash('errors') ?? [];
        return isset($errors[$key]);
    }

    /**
     * Generate URL
     */
    public static function url(string $path): string
    {
        $baseUrl = CONFIG['app']['url'] ?? '';
        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }

    /**
     * Generate asset URL
     */
    public static function asset(string $path): string
    {
        return self::url('public/' . ltrim($path, '/'));
    }

    /**
     * CSRF field for forms
     */
    public static function csrfField(): string
    {
        $token = Session::getCsrfToken();
        return '<input type="hidden" name="_csrf_token" value="' . self::e($token) . '">';
    }
}

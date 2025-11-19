<?php
/**
 * Base Controller Class
 * All controllers extend this class
 */

class Controller
{
    /**
     * Render view
     */
    protected function view(string $template, array $data = []): Response
    {
        return Response::view($template, $data);
    }

    /**
     * Return JSON response
     */
    protected function json($data, int $statusCode = 200): Response
    {
        return Response::json($data, $statusCode);
    }

    /**
     * Redirect to URL
     */
    protected function redirect(string $url, int $statusCode = 302): Response
    {
        return Response::redirect($url, $statusCode);
    }

    /**
     * Redirect back to previous page
     */
    protected function back(): Response
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        return $this->redirect($referer);
    }

    /**
     * Get authenticated user
     */
    protected function getUser(): ?array
    {
        $userId = Session::getUserId();

        if ($userId) {
            require_once APP_PATH . '/Models/User.php';
            return User::find($userId);
        }

        return null;
    }

    /**
     * Require authentication
     */
    protected function requireAuth(): void
    {
        if (!Session::isAuthenticated()) {
            Session::flash('error', 'Please log in to continue');
            throw new Exception('Unauthorized');
        }
    }

    /**
     * Require admin
     */
    protected function requireAdmin(): void
    {
        $this->requireAuth();

        if (!Session::isAdmin()) {
            throw new Exception('Forbidden: Admin access required');
        }
    }

    /**
     * Validate CSRF token
     */
    protected function validateCsrf(Request $request): void
    {
        $token = $request->input('_csrf_token') ?? $request->header('X-CSRF-Token');

        if (!$token || !Session::verifyCsrfToken($token)) {
            throw new Exception('Invalid CSRF token');
        }
    }

    /**
     * Flash message to session
     */
    protected function flash(string $key, $value): void
    {
        Session::flash($key, $value);
    }

    /**
     * Validate request input
     */
    protected function validate(Request $request, array $rules): array
    {
        $errors = $request->validate($rules);

        if (!empty($errors)) {
            Session::flash('errors', $errors);
            Session::flash('old_input', $request->all());
            throw new Exception('Validation failed');
        }

        return $request->all();
    }
}

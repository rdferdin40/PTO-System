<?php
/**
 * Session Management Class
 * Database-backed sessions matching Node.js implementation
 */

class Session
{
    private static bool $started = false;

    /**
     * Start session
     */
    public static function start(): void
    {
        if (self::$started) {
            return;
        }

        // Configure session settings
        ini_set('session.cookie_httponly', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_secure', '0'); // Set to 1 for HTTPS only
        ini_set('session.cookie_samesite', 'Lax');

        // Use database-backed sessions (implement custom handler if needed)
        session_name('timeoff.sid'); // Match Node.js session cookie name
        session_start();

        self::$started = true;

        // Regenerate session ID periodically for security
        if (!self::has('_regenerated')) {
            session_regenerate_id(true);
            self::set('_regenerated', time());
        } elseif (time() - self::get('_regenerated') > 3600) {
            // Regenerate every hour
            session_regenerate_id(true);
            self::set('_regenerated', time());
        }
    }

    /**
     * Set session value
     */
    public static function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Get session value
     */
    public static function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Check if session has key
     */
    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    /**
     * Remove session value
     */
    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Clear all session data
     */
    public static function clear(): void
    {
        $_SESSION = [];
    }

    /**
     * Destroy session
     */
    public static function destroy(): void
    {
        self::clear();
        session_destroy();
        self::$started = false;
    }

    /**
     * Flash message (available for one request)
     */
    public static function flash(string $key, $value = null)
    {
        if ($value === null) {
            // Get flash message
            $message = self::get('_flash_' . $key);
            self::remove('_flash_' . $key);
            return $message;
        } else {
            // Set flash message
            self::set('_flash_' . $key, $value);
        }
    }

    /**
     * Get and clear all flash messages
     */
    public static function getFlashes(): array
    {
        $flashes = [];

        foreach ($_SESSION as $key => $value) {
            if (strpos($key, '_flash_') === 0) {
                $flashKey = substr($key, 7);
                $flashes[$flashKey] = $value;
                self::remove($key);
            }
        }

        return $flashes;
    }

    /**
     * Get session ID
     */
    public static function id(): string
    {
        return session_id();
    }

    /**
     * Set user as authenticated
     */
    public static function setUser($user): void
    {
        self::set('user_id', $user['id']);
        self::set('user_email', $user['email']);
        self::set('user_name', $user['name']);
        self::set('is_admin', $user['admin'] ?? false);
        self::set('company_id', $user['company_id']);
        self::regenerate(); // Regenerate session ID after login
    }

    /**
     * Get authenticated user ID
     */
    public static function getUserId(): ?int
    {
        return self::get('user_id');
    }

    /**
     * Check if user is authenticated
     */
    public static function isAuthenticated(): bool
    {
        return self::has('user_id');
    }

    /**
     * Check if user is admin
     */
    public static function isAdmin(): bool
    {
        return self::get('is_admin', false) === true;
    }

    /**
     * Logout user
     */
    public static function logout(): void
    {
        self::remove('user_id');
        self::remove('user_email');
        self::remove('user_name');
        self::remove('is_admin');
        self::remove('company_id');
        self::regenerate();
    }

    /**
     * Regenerate session ID
     */
    public static function regenerate(): void
    {
        session_regenerate_id(true);
        self::set('_regenerated', time());
    }

    /**
     * Get CSRF token
     */
    public static function getCsrfToken(): string
    {
        if (!self::has('_csrf_token')) {
            self::set('_csrf_token', bin2hex(random_bytes(32)));
        }

        return self::get('_csrf_token');
    }

    /**
     * Verify CSRF token
     */
    public static function verifyCsrfToken(string $token): bool
    {
        return hash_equals(self::getCsrfToken(), $token);
    }
}

<?php
/**
 * TimeOff Management System - Configuration
 * PHP 8.2 / XAMPP 8.2 Compatible
 */

// Prevent direct access
defined('BASEPATH') OR exit('No direct script access allowed');

return [
    // Application
    'app' => [
        'name' => getenv('HEADER_TITLE') ?: 'TimeOff Management',
        'url' => getenv('BRANDING_URL') ?: 'http://localhost',
        'environment' => getenv('APP_ENV') ?: 'production',
        'debug' => getenv('APP_DEBUG') === 'true',
        'timezone' => getenv('APP_TIMEZONE') ?: 'America/New_York',
    ],

    // Database
    'database' => [
        'driver' => 'mysql',
        'host' => getenv('DB_HOST') ?: 'localhost',
        'port' => getenv('DB_PORT') ?: '3306',
        'database' => getenv('DB_DATABASE') ?: 'timeoff_db',
        'username' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    ],

    // Session
    'session' => [
        'driver' => 'database', // 'file' or 'database'
        'lifetime' => 120, // minutes
        'name' => 'timeoff_session',
        'secure' => false, // Set to true in production with HTTPS
        'httponly' => true,
        'samesite' => 'Lax',
        'table' => 'sessions',
    ],

    // Security
    'security' => [
        'session_secret' => getenv('SESSION_SECRET') ?: 'CHANGE_THIS_IN_PRODUCTION',
        'password_cost' => 12, // bcrypt cost
        'csrf_protection' => true,
        'xss_protection' => true,
    ],

    // Email
    'email' => [
        'enabled' => getenv('SEND_EMAIL') === 'true',
        'driver' => 'smtp', // 'smtp' or 'mail'
        'from' => [
            'address' => getenv('SMTP_FROM') ?: 'noreply@example.com',
            'name' => getenv('HEADER_TITLE') ?: 'TimeOff Management',
        ],
        'smtp' => [
            'host' => getenv('SMTP_HOST') ?: 'localhost',
            'port' => getenv('SMTP_PORT') ?: 587,
            'username' => getenv('SMTP_AUTH_USER') ?: '',
            'password' => getenv('SMTP_AUTH_PASS') ?: '',
            'encryption' => getenv('SMTP_ENCRYPTION') ?: 'tls', // 'tls' or 'ssl'
            'auth' => getenv('SMTP_AUTH_REQUIRED') !== 'false',
        ],
    ],

    // Registration
    'registration' => [
        'enabled' => getenv('OPTION_ALLOW_NEW_REGISTRATIONS') === 'true',
    ],

    // Google OAuth
    'google' => [
        'enabled' => !empty(getenv('GOOGLE_CLIENT_ID')),
        'client_id' => getenv('GOOGLE_CLIENT_ID') ?: '',
        'client_secret' => getenv('GOOGLE_CLIENT_SECRET') ?: '',
        'redirect_uri' => (getenv('BRANDING_URL') ?: 'http://localhost') . '/auth/google/callback',
        'allowed_domains' => array_filter(explode(',', getenv('GOOGLE_AUTH_DOMAINS') ?: '')),
    ],

    // Turnstile (Cloudflare CAPTCHA)
    'turnstile' => [
        'site_key' => getenv('TURNSTILE_SITE_KEY') ?: '',
        'secret_key' => getenv('TURNSTILE_SECRET_KEY') ?: '',
    ],

    // Paths
    'paths' => [
        'root' => dirname(__DIR__),
        'app' => dirname(__DIR__) . '/app',
        'config' => __DIR__,
        'public' => dirname(__DIR__) . '/public',
        'storage' => dirname(__DIR__) . '/storage',
        'views' => dirname(__DIR__) . '/app/Views',
        'logs' => dirname(__DIR__) . '/storage/logs',
    ],

    // URLs
    'urls' => [
        'base' => getenv('BRANDING_URL') ?: 'http://localhost',
        'assets' => (getenv('BRANDING_URL') ?: 'http://localhost') . '/assets',
    ],

    // Locale
    'locale' => [
        'default' => 'en',
        'timezone' => getenv('APP_TIMEZONE') ?: 'America/New_York',
    ],

    // Password Requirements (NIST SP 800-63B compliant)
    'password' => [
        'min_length' => 12,
        'max_length' => 128,
        'require_uppercase' => false,
        'require_lowercase' => false,
        'require_numbers' => false,
        'require_special' => false,
        'check_common' => true, // Check against common passwords list
    ],

    // Countries
    'countries' => [
        'US' => 'United States',
        'GB' => 'United Kingdom',
        'CA' => 'Canada',
        'DE' => 'Germany',
        'FR' => 'France',
        'ES' => 'Spain',
        'IT' => 'Italy',
        'MX' => 'Mexico',
        'CN' => 'China',
        'JP' => 'Japan',
        'BR' => 'Brazil',
        'IN' => 'India',
        'AU' => 'Australia',
    ],

    // Date Formats
    'date_formats' => [
        'YYYY-MM-DD' => 'YYYY-MM-DD',
        'DD/MM/YYYY' => 'DD/MM/YYYY',
        'MM/DD/YYYY' => 'MM/DD/YYYY',
        'DD.MM.YYYY' => 'DD.MM.YYYY',
        'DD-MM-YYYY' => 'DD-MM-YYYY',
    ],

    // Pagination
    'pagination' => [
        'per_page' => 10,
    ],
];

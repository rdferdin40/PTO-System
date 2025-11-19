<?php
/**
 * TimeOff Management System - Front Controller
 * XAMPP 8.2 / PHP 8.2 Compatible
 *
 * This is the main entry point for all requests.
 * All URLs are routed through this file via .htaccess
 */

// Define base path constant (required by config.php security check)
define('BASEPATH', true);

// Display errors in development (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Define base paths
define('BASE_PATH', __DIR__);
define('APP_PATH', BASE_PATH . '/app');
define('CORE_PATH', BASE_PATH . '/core');
define('CONFIG_PATH', BASE_PATH . '/config');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('VIEW_PATH', BASE_PATH . '/views');
define('PUBLIC_PATH', BASE_PATH . '/public');

// Load configuration
$config = require CONFIG_PATH . '/config.php';
define('CONFIG', $config);

// Autoloader for classes
spl_autoload_register(function ($class) {
    // Convert namespace to file path
    $class = str_replace('\\', '/', $class);

    $paths = [
        CORE_PATH . '/' . $class . '.php',
        APP_PATH . '/Models/' . $class . '.php',
        APP_PATH . '/Controllers/' . $class . '.php',
        APP_PATH . '/Middleware/' . $class . '.php',
        APP_PATH . '/Libraries/' . $class . '.php',
        APP_PATH . '/Helpers/' . $class . '.php',
    ];

    foreach ($paths as $file) {
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Start session
require_once CORE_PATH . '/Session.php';
Session::start();

// Database connection
require_once CORE_PATH . '/Database.php';
Database::connect($config['database']);

// Initialize router
require_once CORE_PATH . '/Router.php';
require_once CORE_PATH . '/Request.php';
require_once CORE_PATH . '/Response.php';

$router = new Router();

// Load routes
require_once BASE_PATH . '/routes.php';

// Get current request
$request = Request::createFromGlobals();

// Dispatch request
try {
    $response = $router->dispatch($request);
    $response->send();
} catch (Exception $e) {
    // Error handling
    http_response_code(500);

    if ($config['app']['debug']) {
        echo '<h1>Error</h1>';
        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    } else {
        echo '<h1>An error occurred</h1>';
        error_log($e->getMessage());
    }
}

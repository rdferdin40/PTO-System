<?php
/**
 * Application Routes
 * All routes for the TimeOff Management System
 */

// Public routes (no authentication required)
$router->get('/', 'HomeController@index');
$router->get('/login', 'AuthController@showLogin');
$router->post('/login', 'AuthController@login');
$router->get('/register', 'AuthController@showRegister');
$router->post('/register', 'AuthController@register');
$router->get('/logout', 'AuthController@logout');
$router->post('/logout', 'AuthController@logout');

// Password reset
$router->get('/forgot-password', 'AuthController@showForgotPassword');
$router->post('/forgot-password', 'AuthController@forgotPassword');
$router->get('/reset-password', 'AuthController@showResetPassword');
$router->post('/reset-password', 'AuthController@resetPassword');

// OAuth routes
$router->get('/auth/google', 'OAuthController@redirectToGoogle');
$router->get('/auth/google/callback', 'OAuthController@handleGoogleCallback');

// Protected routes (authentication required)
$router->group(['AuthMiddleware'], function($router) {

    // Calendar
    $router->get('/calendar', 'CalendarController@index');
    $router->get('/calendar/teamview', 'CalendarController@teamView');
    $router->get('/calendar/feeds', 'CalendarController@feeds');
    $router->get('/calendar/ical', 'CalendarController@icalFeed');

    // Leave requests
    $router->get('/requests', 'LeaveController@index');
    $router->get('/requests/new', 'LeaveController@create');
    $router->post('/requests/new', 'LeaveController@store');
    $router->get('/requests/:id', 'LeaveController@show');
    $router->post('/requests/:id/approve', 'LeaveController@approve');
    $router->post('/requests/:id/reject', 'LeaveController@reject');
    $router->post('/requests/:id/cancel', 'LeaveController@cancel');
    $router->post('/requests/:id/revoke', 'LeaveController@revoke');

    // User settings
    $router->get('/settings/general', 'SettingsController@general');
    $router->post('/settings/general', 'SettingsController@updateGeneral');
    $router->get('/settings/absences', 'SettingsController@absences');
    $router->get('/settings/schedule', 'SettingsController@schedule');
    $router->post('/settings/schedule', 'SettingsController@updateSchedule');

    // Team
    $router->get('/users', 'UserController@index');
    $router->get('/users/:id', 'UserController@show');

    // Reports (managers only)
    $router->get('/reports/allowance', 'ReportController@allowance');
    $router->get('/reports/audit', 'ReportController@audit');

});

// Admin routes
$router->group(['AuthMiddleware', 'AdminMiddleware'], function($router) {

    // Company settings
    $router->get('/settings/company', 'CompanyController@edit');
    $router->post('/settings/company', 'CompanyController@update');

    // Departments
    $router->get('/settings/departments', 'DepartmentController@index');
    $router->post('/settings/departments', 'DepartmentController@store');
    $router->post('/settings/departments/:id', 'DepartmentController@update');
    $router->post('/settings/departments/:id/delete', 'DepartmentController@delete');

    // Leave types
    $router->get('/settings/leave-types', 'LeaveTypeController@index');
    $router->post('/settings/leave-types', 'LeaveTypeController@store');
    $router->post('/settings/leave-types/:id', 'LeaveTypeController@update');
    $router->post('/settings/leave-types/:id/delete', 'LeaveTypeController@delete');

    // Bank holidays
    $router->get('/settings/bank-holidays', 'BankHolidayController@index');
    $router->post('/settings/bank-holidays', 'BankHolidayController@import');
    $router->post('/settings/bank-holidays/add', 'BankHolidayController@store');
    $router->post('/settings/bank-holidays/:id/delete', 'BankHolidayController@delete');

    // Users management
    $router->get('/users/add', 'UserController@create');
    $router->post('/users/add', 'UserController@store');
    $router->get('/users/:id/edit', 'UserController@edit');
    $router->post('/users/:id/edit', 'UserController@update');
    $router->post('/users/:id/delete', 'UserController@delete');

    // Email audit
    $router->get('/audit', 'AuditController@index');

    // Integration API
    $router->get('/settings/integration', 'IntegrationController@index');
    $router->post('/settings/integration/regenerate-token', 'IntegrationController@regenerateToken');

    // LDAP settings
    $router->get('/settings/ldap', 'LdapController@index');
    $router->post('/settings/ldap', 'LdapController@update');
    $router->post('/settings/ldap/test', 'LdapController@test');
});

// API routes
$router->group(['ApiAuthMiddleware'], function($router) {
    // API endpoints for integration
    $router->get('/api/v1/users', 'ApiController@users');
    $router->get('/api/v1/leaves', 'ApiController@leaves');
    $router->get('/api/v1/absences', 'ApiController@absences');
    $router->post('/api/v1/leaves', 'ApiController@createLeave');
});

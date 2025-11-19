<?php
/**
 * Admin Middleware
 * Ensures user is an administrator
 */

class AdminMiddleware
{
    /**
     * Handle request
     */
    public function handle(Request $request): ?Response
    {
        if (!Session::isAuthenticated()) {
            Session::flash('error', 'Please log in to continue');
            return Response::redirect('/login');
        }

        if (!Session::isAdmin()) {
            Session::flash('error', 'Access denied. Administrator privileges required.');
            return Response::redirect('/calendar');
        }

        return null; // Continue
    }
}

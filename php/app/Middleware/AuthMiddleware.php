<?php
/**
 * Authentication Middleware
 * Ensures user is logged in
 */

class AuthMiddleware
{
    /**
     * Handle request
     * Returns null to continue, or Response to stop and return response
     */
    public function handle(Request $request): ?Response
    {
        if (!Session::isAuthenticated()) {
            // Store intended URL
            Session::set('intended_url', $request->getPath());

            // Redirect to login
            Session::flash('error', 'Please log in to continue');
            return Response::redirect('/login');
        }

        return null; // Continue to route handler
    }
}

<?php
/**
 * API Authentication Middleware
 * Validates API token for integration endpoints
 */

class ApiAuthMiddleware
{
    /**
     * Handle request
     */
    public function handle(Request $request): ?Response
    {
        // Get token from header or query
        $token = $request->header('Authorization');

        if ($token && strpos($token, 'Bearer ') === 0) {
            $token = substr($token, 7);
        } else {
            $token = $request->query('token');
        }

        if (!$token) {
            return Response::json([
                'error' => 'Unauthorized',
                'message' => 'API token required'
            ], 401);
        }

        // Validate token
        require_once APP_PATH . '/Models/Company.php';

        $sql = 'SELECT * FROM companies WHERE integration_api_enabled = 1 AND integration_api_token = :token LIMIT 1';
        $company = Database::fetchOne($sql, ['token' => $token]);

        if (!$company) {
            return Response::json([
                'error' => 'Unauthorized',
                'message' => 'Invalid API token'
            ], 401);
        }

        // Store company ID in request for use in controller
        $request->api_company_id = $company['id'];

        return null; // Continue
    }
}

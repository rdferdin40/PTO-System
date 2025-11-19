<?php
/**
 * Authentication Controller
 * Handles login, logout, registration
 */

class AuthController extends Controller
{
    /**
     * Show login form
     */
    public function showLogin(Request $request): Response
    {
        if (Session::isAuthenticated()) {
            return $this->redirect('/calendar');
        }

        return $this->view('auth/login', [
            'title' => 'Log In',
        ]);
    }

    /**
     * Process login
     */
    public function login(Request $request): Response
    {
        $this->validateCsrf($request);

        $email = $request->input('email');
        $password = $request->input('password');

        if (!$email || !$password) {
            Session::flash('error', 'Email and password are required');
            return $this->back();
        }

        require_once APP_PATH . '/Models/User.php';
        $user = User::findByEmail($email);

        if (!$user || !User::verifyPassword($password, $user['password'])) {
            Session::flash('error', 'Invalid email or password');
            return $this->back();
        }

        // Set session
        Session::setUser($user);

        // Redirect to intended URL or calendar
        $intendedUrl = Session::get('intended_url', '/calendar');
        Session::remove('intended_url');

        Session::flash('success', 'Welcome back, ' . $user['name'] . '!');
        return $this->redirect($intendedUrl);
    }

    /**
     * Show registration form
     */
    public function showRegister(Request $request): Response
    {
        // Check if registration is enabled
        if (!CONFIG['app']['allow_self_registration']) {
            Session::flash('error', 'Self-registration is disabled');
            return $this->redirect('/login');
        }

        return $this->view('auth/register', [
            'title' => 'Register',
        ]);
    }

    /**
     * Process registration
     */
    public function register(Request $request): Response
    {
        if (!CONFIG['app']['allow_self_registration']) {
            return Response::forbidden('Registration disabled');
        }

        $this->validateCsrf($request);

        // Validate input
        $data = $this->validate($request, [
            'email' => 'required|email',
            'password' => 'required|min:12|max:128',
            'name' => 'required',
            'lastname' => 'required',
        ]);

        require_once APP_PATH . '/Models/User.php';

        // Check if email already exists
        if (User::findByEmail($data['email'])) {
            Session::flash('error', 'Email already registered');
            return $this->back();
        }

        // Get default company (first company)
        require_once APP_PATH . '/Models/Company.php';
        require_once APP_PATH . '/Models/Department.php';

        $companies = Company::all();
        if (empty($companies)) {
            Session::flash('error', 'No company available for registration');
            return $this->back();
        }

        $company = $companies[0];
        $departments = Department::getAllForCompany($company['id']);

        if (empty($departments)) {
            Session::flash('error', 'No department available');
            return $this->back();
        }

        $department = $departments[0];

        // Create user
        $userId = User::create([
            'email' => $data['email'],
            'password' => $data['password'],
            'name' => $data['name'],
            'lastname' => $data['lastname'],
            'admin' => 0,
            'auto_approve' => 0,
            'company_id' => $company['id'],
            'department_id' => $department['id'],
            'start_date' => date('Y-m-d'),
        ]);

        // Log in the user
        $user = User::find($userId);
        Session::setUser($user);

        Session::flash('success', 'Registration successful! Welcome to TimeOff.');
        return $this->redirect('/calendar');
    }

    /**
     * Logout
     */
    public function logout(Request $request): Response
    {
        Session::logout();
        Session::flash('success', 'You have been logged out');
        return $this->redirect('/login');
    }

    /**
     * Show forgot password form
     */
    public function showForgotPassword(Request $request): Response
    {
        return $this->view('auth/forgot-password', [
            'title' => 'Forgot Password',
        ]);
    }

    /**
     * Process forgot password
     */
    public function forgotPassword(Request $request): Response
    {
        $this->validateCsrf($request);

        $email = $request->input('email');

        require_once APP_PATH . '/Models/User.php';
        $user = User::findByEmail($email);

        if ($user) {
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Store token (would need a password_resets table)
            Database::insert('password_resets', [
                'email' => $email,
                'token' => $token,
                'expires_at' => $expiry,
            ]);

            // Send email
            require_once APP_PATH . '/Libraries/EmailService.php';
            EmailService::sendPasswordReset($email, $token);
        }

        // Always show success to prevent email enumeration
        Session::flash('success', 'If an account exists with that email, a password reset link has been sent.');
        return $this->redirect('/login');
    }

    /**
     * Show reset password form
     */
    public function showResetPassword(Request $request): Response
    {
        $token = $request->query('token');

        if (!$token) {
            return $this->redirect('/login');
        }

        return $this->view('auth/reset-password', [
            'title' => 'Reset Password',
            'token' => $token,
        ]);
    }

    /**
     * Process password reset
     */
    public function resetPassword(Request $request): Response
    {
        $this->validateCsrf($request);

        $token = $request->input('token');
        $password = $request->input('password');

        // Validate token
        $sql = 'SELECT * FROM password_resets WHERE token = :token AND expires_at > NOW() LIMIT 1';
        $reset = Database::fetchOne($sql, ['token' => $token]);

        if (!$reset) {
            Session::flash('error', 'Invalid or expired reset token');
            return $this->redirect('/login');
        }

        // Update password
        require_once APP_PATH . '/Models/User.php';
        $user = User::findByEmail($reset['email']);

        User::updateById($user['id'], [
            'password' => User::hashPassword($password),
        ]);

        // Delete reset token
        Database::delete('password_resets', 'token = :token', ['token' => $token]);

        Session::flash('success', 'Password reset successful. Please log in.');
        return $this->redirect('/login');
    }
}

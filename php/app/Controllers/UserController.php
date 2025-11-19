<?php
/**
 * User Controller
 * User management
 */

class UserController extends Controller
{
    /**
     * List all users (admin or supervisor view)
     */
    public function index(Request $request): Response
    {
        $this->requireAuth();

        $user = $this->getUser();

        require_once APP_PATH . '/Models/User.php';
        require_once APP_PATH . '/Models/Company.php';

        if ($user['admin']) {
            // Admin sees all users in company
            $users = User::getAllInCompany($user['company_id']);
        } else {
            // Supervisor sees supervised users
            $users = User::getSupervisedUsers($user['id']);
        }

        return $this->view('user/index', [
            'title' => 'Team Members',
            'users' => $users,
        ]);
    }

    /**
     * Show user profile
     */
    public function show(Request $request): Response
    {
        $this->requireAuth();

        $userId = $request->param('id');
        $currentUser = $this->getUser();

        require_once APP_PATH . '/Models/User.php';
        $user = User::find($userId);

        if (!$user) {
            Session::flash('error', 'User not found');
            return $this->redirect('/users');
        }

        // Check authorization
        $isOwn = $user['id'] == $currentUser['id'];
        $isSupervisor = User::isSupervisor($currentUser['id']);
        $isAdmin = $currentUser['admin'];

        if (!$isOwn && !$isSupervisor && !$isAdmin) {
            return Response::forbidden('You cannot view this user');
        }

        // Get allowance info
        $year = (int)date('Y');
        $allowance = User::calculateAllowance($user, $year);
        $used = User::getUsedAllowance($user['id'], $year);

        // Get recent leaves
        require_once APP_PATH . '/Models/Leave.php';
        $leaves = Leave::getAllForUser($user['id']);
        $leaves = array_slice($leaves, 0, 10); // Last 10

        return $this->view('user/show', [
            'title' => User::getFullName($user),
            'user' => $user,
            'allowance' => $allowance,
            'used' => $used,
            'remaining' => max(0, $allowance - $used),
            'leaves' => $leaves,
        ]);
    }

    /**
     * Show create user form (admin only)
     */
    public function create(Request $request): Response
    {
        $this->requireAdmin();

        $user = $this->getUser();

        require_once APP_PATH . '/Models/Department.php';
        $departments = Department::getAllForCompany($user['company_id']);

        return $this->view('user/create', [
            'title' => 'Add User',
            'departments' => $departments,
        ]);
    }

    /**
     * Store new user (admin only)
     */
    public function store(Request $request): Response
    {
        $this->requireAdmin();
        $this->validateCsrf($request);

        $currentUser = $this->getUser();

        // Validate
        $data = $this->validate($request, [
            'email' => 'required|email',
            'name' => 'required',
            'lastname' => 'required',
            'department_id' => 'required',
        ]);

        require_once APP_PATH . '/Models/User.php';

        // Check if email exists
        if (User::findByEmail($data['email'])) {
            Session::flash('error', 'Email already exists');
            return $this->back();
        }

        // Generate temporary password
        $tempPassword = bin2hex(random_bytes(8));

        // Create user
        $userId = User::create([
            'email' => $data['email'],
            'password' => $tempPassword,
            'name' => $data['name'],
            'lastname' => $data['lastname'],
            'department_id' => $data['department_id'],
            'company_id' => $currentUser['company_id'],
            'admin' => $request->input('admin') ? 1 : 0,
            'auto_approve' => $request->input('auto_approve') ? 1 : 0,
            'start_date' => $request->input('start_date') ?? date('Y-m-d'),
        ]);

        // Send welcome email
        require_once APP_PATH . '/Libraries/EmailService.php';
        $user = User::find($userId);
        EmailService::sendWelcome($user, $tempPassword);

        Session::flash('success', 'User created successfully. Welcome email sent.');
        return $this->redirect('/users');
    }

    /**
     * Show edit user form
     */
    public function edit(Request $request): Response
    {
        $this->requireAdmin();

        $userId = $request->param('id');
        $currentUser = $this->getUser();

        require_once APP_PATH . '/Models/User.php';
        require_once APP_PATH . '/Models/Department.php';

        $user = User::find($userId);
        if (!$user) {
            Session::flash('error', 'User not found');
            return $this->redirect('/users');
        }

        $departments = Department::getAllForCompany($currentUser['company_id']);

        return $this->view('user/edit', [
            'title' => 'Edit User',
            'user' => $user,
            'departments' => $departments,
        ]);
    }

    /**
     * Update user
     */
    public function update(Request $request): Response
    {
        $this->requireAdmin();
        $this->validateCsrf($request);

        $userId = $request->param('id');

        require_once APP_PATH . '/Models/User.php';

        // Update user
        User::updateById($userId, [
            'name' => $request->input('name'),
            'lastname' => $request->input('lastname'),
            'department_id' => $request->input('department_id'),
            'admin' => $request->input('admin') ? 1 : 0,
            'auto_approve' => $request->input('auto_approve') ? 1 : 0,
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
        ]);

        Session::flash('success', 'User updated successfully');
        return $this->redirect('/users/' . $userId . '/edit');
    }

    /**
     * Delete user
     */
    public function delete(Request $request): Response
    {
        $this->requireAdmin();
        $this->validateCsrf($request);

        $userId = $request->param('id');
        $currentUser = $this->getUser();

        // Cannot delete self
        if ($userId == $currentUser['id']) {
            Session::flash('error', 'Cannot delete your own account');
            return $this->back();
        }

        require_once APP_PATH . '/Models/User.php';
        User::deleteById($userId);

        Session::flash('success', 'User deleted successfully');
        return $this->redirect('/users');
    }
}

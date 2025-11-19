<?php
/**
 * Leave Controller
 * Handles leave requests and approvals
 */

class LeaveController extends Controller
{
    /**
     * List all leaves for current user
     */
    public function index(Request $request): Response
    {
        $this->requireAuth();

        $user = $this->getUser();

        require_once APP_PATH . '/Models/Leave.php';
        $leaves = Leave::getAllForUser($user['id']);

        // Get pending approvals if user is supervisor
        $pendingApprovals = [];
        if (User::isSupervisor($user['id'])) {
            $pendingApprovals = Leave::getPendingForApprover($user['id']);
        }

        return $this->view('leave/index', [
            'title' => 'Leave Requests',
            'leaves' => $leaves,
            'pending_approvals' => $pendingApprovals,
        ]);
    }

    /**
     * Show create leave form
     */
    public function create(Request $request): Response
    {
        $this->requireAuth();

        $user = $this->getUser();

        require_once APP_PATH . '/Models/LeaveType.php';
        require_once APP_PATH . '/Models/Company.php';

        $leaveTypes = LeaveType::getAllForCompany($user['company_id']);
        $year = (int)date('Y');

        return $this->view('leave/create', [
            'title' => 'Request Leave',
            'leave_types' => $leaveTypes,
            'allowance' => User::calculateAllowance($user, $year),
            'used' => User::getUsedAllowance($user['id'], $year),
        ]);
    }

    /**
     * Store new leave request
     */
    public function store(Request $request): Response
    {
        $this->requireAuth();
        $this->validateCsrf($request);

        $user = $this->getUser();

        // Validate input
        $data = $this->validate($request, [
            'leave_type_id' => 'required',
            'date_start' => 'required',
            'date_end' => 'required',
            'day_part_start' => 'required',
            'day_part_end' => 'required',
        ]);

        // Build leave data
        $leaveData = [
            'user_id' => $user['id'],
            'leave_type_id' => $data['leave_type_id'],
            'date_start' => $data['date_start'],
            'date_end' => $data['date_end'],
            'day_part_start' => $data['day_part_start'],
            'day_part_end' => $data['day_part_end'],
            'employee_comment' => $request->input('employee_comment', ''),
            'status' => Leave::STATUS_NEW,
        ];

        // Validate leave
        require_once APP_PATH . '/Models/Leave.php';
        $errors = Leave::validate($leaveData, $user);

        if (!empty($errors)) {
            Session::flash('error', implode('. ', $errors));
            return $this->back();
        }

        // Check if user has auto-approve
        if ($user['auto_approve']) {
            $leaveData['status'] = Leave::STATUS_APPROVED;
            $leaveData['approver_id'] = $user['id'];
            $leaveData['decided_at'] = date('Y-m-d H:i:s');
        }

        // Create leave
        $leaveId = Leave::create($leaveData);

        // Send notification email
        if (!$user['auto_approve']) {
            require_once APP_PATH . '/Libraries/EmailService.php';
            $leave = Leave::find($leaveId);
            EmailService::sendLeaveRequest($leave);
        }

        Session::flash('success', 'Leave request submitted successfully');
        return $this->redirect('/requests');
    }

    /**
     * Show leave details
     */
    public function show(Request $request): Response
    {
        $this->requireAuth();

        $leaveId = $request->param('id');
        $user = $this->getUser();

        require_once APP_PATH . '/Models/Leave.php';
        $leave = Leave::find($leaveId);

        if (!$leave) {
            Session::flash('error', 'Leave request not found');
            return $this->redirect('/requests');
        }

        // Check authorization
        $isOwner = $leave['user_id'] == $user['id'];
        $canApprove = User::canApprove($user, $leave);

        if (!$isOwner && !$canApprove && !$user['admin']) {
            return Response::forbidden('You cannot view this leave request');
        }

        // Get related data
        require_once APP_PATH . '/Models/User.php';
        require_once APP_PATH . '/Models/LeaveType.php';

        $leaveUser = User::find($leave['user_id']);
        $leaveType = LeaveType::find($leave['leave_type_id']);
        $deductedDays = Leave::getDeductedDays($leave);

        return $this->view('leave/show', [
            'title' => 'Leave Request Details',
            'leave' => $leave,
            'leave_user' => $leaveUser,
            'leave_type' => $leaveType,
            'deducted_days' => $deductedDays,
            'is_owner' => $isOwner,
            'can_approve' => $canApprove,
        ]);
    }

    /**
     * Approve leave request
     */
    public function approve(Request $request): Response
    {
        $this->requireAuth();
        $this->validateCsrf($request);

        $leaveId = $request->param('id');
        $user = $this->getUser();

        require_once APP_PATH . '/Models/Leave.php';
        $leave = Leave::find($leaveId);

        if (!$leave) {
            return $this->json(['error' => 'Leave not found'], 404);
        }

        // Check authorization
        if (!User::canApprove($user, $leave)) {
            return $this->json(['error' => 'Unauthorized'], 403);
        }

        // Approve
        $comment = $request->input('comment');
        Leave::approve($leaveId, $user['id'], $comment);

        Session::flash('success', 'Leave request approved');
        return $this->redirect('/requests/' . $leaveId);
    }

    /**
     * Reject leave request
     */
    public function reject(Request $request): Response
    {
        $this->requireAuth();
        $this->validateCsrf($request);

        $leaveId = $request->param('id');
        $user = $this->getUser();

        require_once APP_PATH . '/Models/Leave.php';
        $leave = Leave::find($leaveId);

        if (!$leave) {
            return $this->json(['error' => 'Leave not found'], 404);
        }

        // Check authorization
        if (!User::canApprove($user, $leave)) {
            return $this->json(['error' => 'Unauthorized'], 403);
        }

        // Reject
        $comment = $request->input('comment');
        Leave::reject($leaveId, $user['id'], $comment);

        Session::flash('success', 'Leave request rejected');
        return $this->redirect('/requests/' . $leaveId);
    }

    /**
     * Cancel own leave request
     */
    public function cancel(Request $request): Response
    {
        $this->requireAuth();
        $this->validateCsrf($request);

        $leaveId = $request->param('id');
        $user = $this->getUser();

        require_once APP_PATH . '/Models/Leave.php';
        $leave = Leave::find($leaveId);

        if (!$leave || $leave['user_id'] != $user['id']) {
            return $this->json(['error' => 'Unauthorized'], 403);
        }

        // Can only cancel pending or approved leaves
        if (!in_array($leave['status'], [Leave::STATUS_NEW, Leave::STATUS_APPROVED])) {
            Session::flash('error', 'Cannot cancel this leave request');
            return $this->back();
        }

        // If approved, request revoke
        if ($leave['status'] == Leave::STATUS_APPROVED) {
            Leave::requestRevoke($leaveId);
            Session::flash('success', 'Cancellation request submitted for approval');
        } else {
            Leave::cancel($leaveId);
            Session::flash('success', 'Leave request canceled');
        }

        return $this->redirect('/requests');
    }

    /**
     * Revoke (approve cancellation of approved leave)
     */
    public function revoke(Request $request): Response
    {
        $this->requireAuth();
        $this->validateCsrf($request);

        $leaveId = $request->param('id');
        $user = $this->getUser();

        require_once APP_PATH . '/Models/Leave.php';
        $leave = Leave::find($leaveId);

        if (!$leave) {
            return $this->json(['error' => 'Leave not found'], 404);
        }

        // Check authorization
        if (!User::canApprove($user, $leave)) {
            return $this->json(['error' => 'Unauthorized'], 403);
        }

        // Cancel the leave
        Leave::cancel($leaveId);

        Session::flash('success', 'Leave cancellation approved');
        return $this->redirect('/requests/' . $leaveId);
    }
}

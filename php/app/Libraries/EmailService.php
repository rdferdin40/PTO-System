<?php
/**
 * Email Service
 * Handles sending emails using SMTP
 */

class EmailService
{
    private static ?PHPMailer\PHPMailer\PHPMailer $mailer = null;

    /**
     * Get configured mailer instance
     */
    private static function getMailer(): PHPMailer\PHPMailer\PHPMailer
    {
        if (self::$mailer === null) {
            require_once BASE_PATH . '/vendor/phpmailer/phpmailer/src/Exception.php';
            require_once BASE_PATH . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
            require_once BASE_PATH . '/vendor/phpmailer/phpmailer/src/SMTP.php';

            $config = CONFIG['email'];

            $mailer = new PHPMailer\PHPMailer\PHPMailer(true);

            if ($config['enabled']) {
                $mailer->isSMTP();
                $mailer->Host = $config['smtp']['host'];
                $mailer->SMTPAuth = true;
                $mailer->Username = $config['smtp']['user'];
                $mailer->Password = $config['smtp']['password'];
                $mailer->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mailer->Port = $config['smtp']['port'];
            }

            $mailer->setFrom($config['from']['address'], $config['from']['name']);
            $mailer->isHTML(true);

            self::$mailer = $mailer;
        }

        return self::$mailer;
    }

    /**
     * Send email
     */
    public static function send(string $to, string $subject, string $body, ?int $userId = null): bool
    {
        $config = CONFIG['email'];

        // Log email
        require_once APP_PATH . '/Models/EmailAudit.php';
        EmailAudit::log($to, $subject, $body, $userId);

        // If email disabled, just log and return
        if (!$config['enabled']) {
            return true;
        }

        try {
            $mailer = self::getMailer();
            $mailer->clearAddresses();
            $mailer->addAddress($to);
            $mailer->Subject = $subject;
            $mailer->Body = $body;

            return $mailer->send();
        } catch (Exception $e) {
            error_log("Email send failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send leave request notification
     */
    public static function sendLeaveRequest(array $leave): void
    {
        require_once APP_PATH . '/Models/User.php';
        require_once APP_PATH . '/Models/LeaveType.php';

        $user = User::find($leave['user_id']);
        $leaveType = LeaveType::find($leave['leave_type_id']);
        $supervisor = User::getSupervisor($user['id']);

        if (!$supervisor) {
            return;
        }

        $subject = "New leave request from " . User::getFullName($user);

        $body = "
            <h2>New Leave Request</h2>
            <p><strong>Employee:</strong> " . User::getFullName($user) . "</p>
            <p><strong>Leave Type:</strong> {$leaveType['name']}</p>
            <p><strong>Dates:</strong> {$leave['date_start']} to {$leave['date_end']}</p>
            <p><strong>Comment:</strong> {$leave['employee_comment']}</p>
            <p><a href=\"" . CONFIG['app']['url'] . "/requests/{$leave['id']}\">View Request</a></p>
        ";

        self::send($supervisor['email'], $subject, $body, $supervisor['id']);
    }

    /**
     * Send leave approved notification
     */
    public static function sendLeaveApproved(array $leave): void
    {
        require_once APP_PATH . '/Models/User.php';
        require_once APP_PATH . '/Models/LeaveType.php';

        $user = User::find($leave['user_id']);
        $leaveType = LeaveType::find($leave['leave_type_id']);

        $subject = "Your leave request has been approved";

        $body = "
            <h2>Leave Request Approved</h2>
            <p>Your leave request has been approved.</p>
            <p><strong>Leave Type:</strong> {$leaveType['name']}</p>
            <p><strong>Dates:</strong> {$leave['date_start']} to {$leave['date_end']}</p>
            <p><strong>Approver Comment:</strong> {$leave['approver_comment']}</p>
            <p><a href=\"" . CONFIG['app']['url'] . "/requests/{$leave['id']}\">View Request</a></p>
        ";

        self::send($user['email'], $subject, $body, $user['id']);
    }

    /**
     * Send leave rejected notification
     */
    public static function sendLeaveRejected(array $leave): void
    {
        require_once APP_PATH . '/Models/User.php';
        require_once APP_PATH . '/Models/LeaveType.php';

        $user = User::find($leave['user_id']);
        $leaveType = LeaveType::find($leave['leave_type_id']);

        $subject = "Your leave request has been rejected";

        $body = "
            <h2>Leave Request Rejected</h2>
            <p>Unfortunately, your leave request has been rejected.</p>
            <p><strong>Leave Type:</strong> {$leaveType['name']}</p>
            <p><strong>Dates:</strong> {$leave['date_start']} to {$leave['date_end']}</p>
            <p><strong>Reason:</strong> {$leave['approver_comment']}</p>
            <p><a href=\"" . CONFIG['app']['url'] . "/requests/{$leave['id']}\">View Request</a></p>
        ";

        self::send($user['email'], $subject, $body, $user['id']);
    }

    /**
     * Send password reset email
     */
    public static function sendPasswordReset(string $email, string $token): void
    {
        $subject = "Password Reset Request";

        $resetUrl = CONFIG['app']['url'] . "/reset-password?token=$token";

        $body = "
            <h2>Password Reset</h2>
            <p>You requested a password reset for your account.</p>
            <p>Click the link below to reset your password:</p>
            <p><a href=\"$resetUrl\">Reset Password</a></p>
            <p>This link will expire in 1 hour.</p>
            <p>If you did not request this, please ignore this email.</p>
        ";

        self::send($email, $subject, $body);
    }

    /**
     * Send welcome email to new user
     */
    public static function sendWelcome(array $user, string $tempPassword): void
    {
        $subject = "Welcome to " . CONFIG['app']['name'];

        $body = "
            <h2>Welcome!</h2>
            <p>Your account has been created for " . CONFIG['app']['name'] . ".</p>
            <p><strong>Email:</strong> {$user['email']}</p>
            <p><strong>Temporary Password:</strong> $tempPassword</p>
            <p>Please log in and change your password immediately.</p>
            <p><a href=\"" . CONFIG['app']['url'] . "/login\">Log In</a></p>
        ";

        self::send($user['email'], $subject, $body, $user['id']);
    }
}

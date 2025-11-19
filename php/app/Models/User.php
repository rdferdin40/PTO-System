<?php
/**
 * User Model
 * Handles all user-related operations including allowance calculation
 */

class User extends Model
{
    protected static string $table = 'users';
    protected static array $fillable = [
        'email', 'password', 'name', 'lastname', 'admin', 'auto_approve',
        'start_date', 'end_date', 'company_id', 'department_id', 'created_at', 'updated_at'
    ];
    protected static array $hidden = ['password'];
    protected static array $casts = [
        'id' => 'int',
        'admin' => 'bool',
        'auto_approve' => 'bool',
        'company_id' => 'int',
        'department_id' => 'int',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    /**
     * Hash password before saving
     */
    public static function create(array $data): int
    {
        if (isset($data['password'])) {
            $data['password'] = self::hashPassword($data['password']);
        }

        return parent::create($data);
    }

    /**
     * Hash password
     */
    public static function hashPassword(string $password): string
    {
        // Use bcrypt with cost 12 (matching Node.js)
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Verify password
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Get user by email
     */
    public static function findByEmail(string $email): ?array
    {
        return self::findBy('email', $email);
    }

    /**
     * Get user's full name
     */
    public static function getFullName(array $user): string
    {
        return trim(($user['name'] ?? '') . ' ' . ($user['lastname'] ?? ''));
    }

    /**
     * Get user's company
     */
    public static function getCompany(int $userId): ?array
    {
        $sql = 'SELECT c.* FROM companies c
                INNER JOIN users u ON u.company_id = c.id
                WHERE u.id = :user_id';

        return Database::fetchOne($sql, ['user_id' => $userId]);
    }

    /**
     * Get user's department
     */
    public static function getDepartment(int $userId): ?array
    {
        $sql = 'SELECT d.* FROM departments d
                INNER JOIN users u ON u.department_id = d.id
                WHERE u.id = :user_id';

        return Database::fetchOne($sql, ['user_id' => $userId]);
    }

    /**
     * Get supervised departments (where user is manager)
     */
    public static function getSupervisedDepartments(int $userId): array
    {
        $sql = 'SELECT * FROM departments WHERE boss_id = :user_id ORDER BY name';
        return Database::fetchAll($sql, ['user_id' => $userId]);
    }

    /**
     * Check if user is supervisor
     */
    public static function isSupervisor(int $userId): bool
    {
        return !empty(self::getSupervisedDepartments($userId));
    }

    /**
     * Get supervised users
     */
    public static function getSupervisedUsers(int $userId): array
    {
        $sql = 'SELECT u.* FROM users u
                INNER JOIN departments d ON u.department_id = d.id
                WHERE d.boss_id = :user_id
                ORDER BY u.name, u.lastname';

        return Database::fetchAll($sql, ['user_id' => $userId]);
    }

    /**
     * Get user's supervisor
     */
    public static function getSupervisor(int $userId): ?array
    {
        $sql = 'SELECT u.* FROM users u
                INNER JOIN departments d ON d.boss_id = u.id
                INNER JOIN users me ON me.department_id = d.id
                WHERE me.id = :user_id
                LIMIT 1';

        return Database::fetchOne($sql, ['user_id' => $userId]);
    }

    /**
     * Calculate user's allowance for a given year
     * This is the CRITICAL business logic
     *
     * Allowance = nominal_allowance + carry_over + adjustment + employment_prorate + accrual
     */
    public static function calculateAllowance(array $user, int $year): float
    {
        require_once APP_PATH . '/Libraries/AllowanceCalculator.php';
        $calculator = new AllowanceCalculator();
        return $calculator->calculateAllowance($user, $year);
    }

    /**
     * Get nominal allowance (base allowance from department)
     */
    public static function getNominalAllowance(array $user): float
    {
        $department = self::getDepartment($user['id']);
        return (float) ($department['allowance'] ?? 20);
    }

    /**
     * Get carry over allowance from previous year
     */
    public static function getCarryOverAllowance(array $user, int $year): float
    {
        $company = self::getCompany($user['id']);
        $maxCarryOver = (int) ($company['carry_over'] ?? 0);

        if ($maxCarryOver === 0) {
            return 0;
        }

        $previousYear = $year - 1;

        // Get unused allowance from previous year
        $used = self::getUsedAllowance($user['id'], $previousYear);
        $total = self::calculateAllowance($user, $previousYear);
        $unused = $total - $used;

        // Cap at max carry over
        return min($unused, $maxCarryOver);
    }

    /**
     * Get manual allowance adjustment
     */
    public static function getManualAdjustment(int $userId, int $year): float
    {
        $sql = 'SELECT COALESCE(SUM(adjustment), 0) as total
                FROM user_allowance_adjustments
                WHERE user_id = :user_id AND year = :year AND carried_over_allowance = 0';

        $result = Database::fetchOne($sql, [
            'user_id' => $userId,
            'year' => $year
        ]);

        return (float) ($result['total'] ?? 0);
    }

    /**
     * Get employment range adjustment (prorating for partial year)
     */
    public static function getEmploymentRangeAdjustment(array $user, float $nominalAllowance, int $year): float
    {
        $startDate = $user['start_date'] ? new DateTime($user['start_date']) : null;
        $endDate = $user['end_date'] ? new DateTime($user['end_date']) : null;

        $startYear = $startDate ? (int)$startDate->format('Y') : null;
        $endYear = $endDate ? (int)$endDate->format('Y') : null;

        // Only prorate if started OR ended in current year
        if ($year != $startYear && (!$endYear || $endYear > $year)) {
            return 0;
        }

        // Determine period
        $periodStart = ($startYear == $year)
            ? $startDate
            : new DateTime("$year-01-01");

        $periodEnd = ($endYear && $endYear <= $year)
            ? $endDate
            : new DateTime("$year-12-31");

        // Calculate prorated allowance
        $daysDiff = $periodStart->diff($periodEnd)->days;
        $proratedAllowance = round(($nominalAllowance * $daysDiff) / 365);

        // Return negative adjustment (reduction from nominal)
        return -1 * ($nominalAllowance - $proratedAllowance);
    }

    /**
     * Get accrued allowance (for locking future allowance)
     */
    public static function getAccruedAdjustment(array $user, int $year): float
    {
        $company = self::getCompany($user['id']);

        if (!($company['accrued_adjustment_enabled'] ?? false)) {
            return 0;
        }

        $now = new DateTime();
        $currentYear = (int)$now->format('Y');

        // Only apply to current year
        if ($year !== $currentYear) {
            return 0;
        }

        // Calculate how many months have passed
        $currentMonth = (int)$now->format('m');
        $monthsPassed = $currentMonth;

        // Calculate how much allowance should be accrued
        $totalAllowance = self::getNominalAllowance($user);
        $accruedSoFar = ($totalAllowance / 12) * $monthsPassed;

        // Get used allowance
        $usedAllowance = self::getUsedAllowance($user['id'], $year);

        // If used more than accrued, lock the difference
        if ($usedAllowance > $accruedSoFar) {
            return -1 * ($usedAllowance - $accruedSoFar);
        }

        return 0;
    }

    /**
     * Get used allowance for a year
     */
    public static function getUsedAllowance(int $userId, int $year): float
    {
        require_once APP_PATH . '/Models/Leave.php';
        return Leave::getUsedAllowanceForUser($userId, $year);
    }

    /**
     * Get remaining allowance
     */
    public static function getRemainingAllowance(array $user, int $year): float
    {
        $total = self::calculateAllowance($user, $year);
        $used = self::getUsedAllowance($user['id'], $year);
        return $total - $used;
    }

    /**
     * Get all users in company
     */
    public static function getAllInCompany(int $companyId): array
    {
        return self::where('company_id', $companyId)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get all users in department
     */
    public static function getAllInDepartment(int $departmentId): array
    {
        return self::where('department_id', $departmentId)
            ->orderBy('name')
            ->get();
    }

    /**
     * Check if user can approve leave requests
     */
    public static function canApprove(array $user, array $leave): bool
    {
        // Admins can approve everything
        if ($user['admin']) {
            return true;
        }

        // Can't approve own requests
        if ($leave['user_id'] == $user['id']) {
            return false;
        }

        // Check if supervisor of the leave requester
        $leaveUser = self::find($leave['user_id']);
        if (!$leaveUser) {
            return false;
        }

        $supervisedDepartments = self::getSupervisedDepartments($user['id']);
        $supervisedDepartmentIds = array_column($supervisedDepartments, 'id');

        return in_array($leaveUser['department_id'], $supervisedDepartmentIds);
    }

    /**
     * Reload user account (reload from database)
     */
    public static function reload(int $userId): ?array
    {
        return self::find($userId);
    }
}

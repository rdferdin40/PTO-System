<?php
/**
 * Department Model
 * Handles departments and team management
 */

class Department extends Model
{
    protected static string $table = 'departments';
    protected static array $fillable = [
        'name', 'allowance', 'include_public_holidays', 'is_accrued_allowance',
        'boss_id', 'company_id', 'created_at', 'updated_at'
    ];
    protected static array $casts = [
        'id' => 'int',
        'allowance' => 'float',
        'include_public_holidays' => 'bool',
        'is_accrued_allowance' => 'bool',
        'boss_id' => 'int',
        'company_id' => 'int',
    ];

    /**
     * Get department manager/boss
     */
    public static function getBoss(int $departmentId): ?array
    {
        $sql = 'SELECT u.* FROM users u
                INNER JOIN departments d ON d.boss_id = u.id
                WHERE d.id = :department_id
                LIMIT 1';

        return Database::fetchOne($sql, ['department_id' => $departmentId]);
    }

    /**
     * Get all users in department
     */
    public static function getUsers(int $departmentId): array
    {
        require_once APP_PATH . '/Models/User.php';
        return User::getAllInDepartment($departmentId);
    }

    /**
     * Get subordinate departments (where members report to this dept's boss)
     */
    public static function getSubordinateDepartments(int $departmentId): array
    {
        $dept = self::find($departmentId);
        if (!$dept || !$dept['boss_id']) {
            return [];
        }

        // Get all departments managed by this department's boss
        return self::where('boss_id', $dept['boss_id'])
            ->get();
    }

    /**
     * Generate team view data for calendar
     */
    public static function generateTeamView(int $departmentId, DateTime $startDate, DateTime $endDate): array
    {
        $users = self::getUsers($departmentId);
        $teamView = [];

        foreach ($users as $user) {
            require_once APP_PATH . '/Models/Leave.php';

            // Get all leaves for this user in date range
            $leaves = Leave::getForUserInRange($user['id'], $startDate, $endDate);

            $teamView[] = [
                'user' => $user,
                'leaves' => $leaves,
                'allowance' => User::calculateAllowance($user, (int)$startDate->format('Y')),
                'used' => User::getUsedAllowance($user['id'], (int)$startDate->format('Y')),
            ];
        }

        return $teamView;
    }

    /**
     * Check if user is member of department
     */
    public static function hasMember(int $departmentId, int $userId): bool
    {
        $sql = 'SELECT COUNT(*) as count FROM users
                WHERE department_id = :department_id AND id = :user_id';

        $result = Database::fetchOne($sql, [
            'department_id' => $departmentId,
            'user_id' => $userId
        ]);

        return (int) $result['count'] > 0;
    }

    /**
     * Get all departments for company
     */
    public static function getAllForCompany(int $companyId): array
    {
        return self::where('company_id', $companyId)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get departments supervised by user
     */
    public static function getSupervisedBy(int $userId): array
    {
        return self::where('boss_id', $userId)
            ->orderBy('name')
            ->get();
    }
}

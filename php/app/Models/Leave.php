<?php
/**
 * Leave Model
 * Handles leave requests with complex workflow and validation
 */

class Leave extends Model
{
    protected static string $table = 'leaves';
    protected static array $fillable = [
        'status', 'employee_comment', 'approver_comment', 'decided_at',
        'date_start', 'date_end', 'day_part_start', 'day_part_end',
        'user_id', 'approver_id', 'leave_type_id', 'created_at', 'updated_at'
    ];
    protected static array $casts = [
        'id' => 'int',
        'status' => 'int',
        'day_part_start' => 'int',
        'day_part_end' => 'int',
        'user_id' => 'int',
        'approver_id' => 'int',
        'leave_type_id' => 'int',
        'date_start' => 'datetime',
        'date_end' => 'datetime',
        'decided_at' => 'datetime',
    ];

    // Status constants
    const STATUS_NEW = 1;
    const STATUS_APPROVED = 2;
    const STATUS_REJECTED = 3;
    const STATUS_PENDED_REVOKE = 4;
    const STATUS_CANCELED = 5;

    // Day part constants
    const DAY_PART_ALL = 1;
    const DAY_PART_MORNING = 2;
    const DAY_PART_AFTERNOON = 3;

    /**
     * Get all days covered by this leave
     * Returns array of ['date' => 'Y-m-d', 'day_part' => 1|2|3]
     */
    public static function getDays(array $leave): array
    {
        $start = new DateTime($leave['date_start']);
        $end = new DateTime($leave['date_end']);
        $days = [];

        $interval = new DateInterval('P1D');
        $dateRange = new DatePeriod($start, $interval, $end->modify('+1 day'));

        foreach ($dateRange as $date) {
            $dateStr = $date->format('Y-m-d');
            $startStr = (new DateTime($leave['date_start']))->format('Y-m-d');
            $endStr = (new DateTime($leave['date_end']))->format('Y-m-d');

            // Determine day part
            if ($dateStr === $startStr && $dateStr === $endStr) {
                // Single day leave - use start day part
                $dayPart = $leave['day_part_start'];
            } elseif ($dateStr === $startStr) {
                $dayPart = $leave['day_part_start'];
            } elseif ($dateStr === $endStr) {
                $dayPart = $leave['day_part_end'];
            } else {
                $dayPart = self::DAY_PART_ALL;
            }

            $days[] = [
                'date' => $dateStr,
                'day_part' => $dayPart
            ];
        }

        return $days;
    }

    /**
     * Calculate deducted days (excluding weekends and bank holidays)
     */
    public static function getDeductedDays(array $leave): float
    {
        require_once APP_PATH . '/Models/User.php';
        require_once APP_PATH . '/Models/Company.php';
        require_once APP_PATH . '/Models/Department.php';

        $user = User::find($leave['user_id']);
        $department = Department::find($user['department_id']);
        $includePublicHolidays = (bool) ($department['include_public_holidays'] ?? true);

        $days = self::getDays($leave);
        $deductedDays = 0;

        foreach ($days as $day) {
            $date = new DateTime($day['date']);

            // Skip if not a working day (weekend)
            if (!Company::isWorkingDay($user['company_id'], $date)) {
                continue;
            }

            // Skip if bank holiday (if configured)
            if ($includePublicHolidays && Company::isBankHoliday($user['company_id'], $date)) {
                continue;
            }

            // Count the day
            if ($day['day_part'] === self::DAY_PART_ALL) {
                $deductedDays += 1;
            } else {
                // Half day (morning or afternoon)
                $deductedDays += 0.5;
            }
        }

        return $deductedDays;
    }

    /**
     * Check if leave overlaps with another leave
     */
    public static function hasOverlap(array $leave, int $userId): bool
    {
        // Get all leaves for user that overlap date range
        $sql = 'SELECT * FROM leaves
                WHERE user_id = :user_id
                AND id != :leave_id
                AND status IN (:status_new, :status_approved, :status_pended_revoke)
                AND NOT (date_end < :date_start OR date_start > :date_end)';

        $params = [
            'user_id' => $userId,
            'leave_id' => $leave['id'] ?? 0,
            'status_new' => self::STATUS_NEW,
            'status_approved' => self::STATUS_APPROVED,
            'status_pended_revoke' => self::STATUS_PENDED_REVOKE,
            'date_start' => $leave['date_start'],
            'date_end' => $leave['date_end']
        ];

        $overlappingLeaves = Database::fetchAll($sql, $params);

        // Check each overlapping leave for day-part compatibility
        foreach ($overlappingLeaves as $otherLeave) {
            if (!self::canCoexist($leave, $otherLeave)) {
                return true; // Found overlap
            }
        }

        return false; // No overlap
    }

    /**
     * Check if two leaves can coexist (half-day compatibility)
     */
    public static function canCoexist(array $leave1, array $leave2): bool
    {
        $days1 = self::getDays($leave1);
        $days2 = self::getDays($leave2);

        // Check first and last days of each leave
        $first1 = $days1[0] ?? null;
        $last1 = end($days1) ?: null;
        $first2 = $days2[0] ?? null;
        $last2 = end($days2) ?: null;

        $checkDays = [$first1, $last1];
        foreach ($checkDays as $day1) {
            if (!$day1) continue;

            foreach ([$first2, $last2] as $day2) {
                if (!$day2) continue;

                // Same date?
                if ($day1['date'] === $day2['date']) {
                    // Both half days?
                    if ($day1['day_part'] != self::DAY_PART_ALL &&
                        $day2['day_part'] != self::DAY_PART_ALL) {

                        // Different parts (AM vs PM)?
                        if ($day1['day_part'] != $day2['day_part']) {
                            return true; // Can coexist (AM + PM on same day)
                        }
                    }

                    // Otherwise overlapping
                    return false;
                }
            }
        }

        return true; // No overlap on same days
    }

    /**
     * Validate leave request
     */
    public static function validate(array $leave, array $user): array
    {
        $errors = [];

        // Check dates
        $startDate = new DateTime($leave['date_start']);
        $endDate = new DateTime($leave['date_end']);

        if ($endDate < $startDate) {
            $errors[] = 'End date must be after start date';
        }

        // Check overlap
        if (self::hasOverlap($leave, $user['id'])) {
            $errors[] = 'This leave request overlaps with an existing leave';
        }

        // Check allowance
        $year = (int) $startDate->format('Y');
        $remaining = User::getRemainingAllowance($user, $year);
        $deductedDays = self::getDeductedDays($leave);

        if ($deductedDays > $remaining) {
            $errors[] = "Insufficient allowance. You have $remaining days remaining but requested $deductedDays days";
        }

        // Check payroll close
        require_once APP_PATH . '/Models/Company.php';
        if (Company::isPayrollClosed($user['company_id'])) {
            $closeDate = Company::getPayrollCloseDate($user['company_id']);
            if ($startDate < $closeDate) {
                $errors[] = 'Cannot create leave in past week after payroll close';
            }
        }

        return $errors;
    }

    /**
     * Approve leave
     */
    public static function approve(int $leaveId, int $approverId, ?string $comment = null): void
    {
        $data = [
            'status' => self::STATUS_APPROVED,
            'approver_id' => $approverId,
            'decided_at' => date('Y-m-d H:i:s'),
        ];

        if ($comment) {
            $data['approver_comment'] = $comment;
        }

        self::updateById($leaveId, $data);

        // Send email notification
        $leave = self::find($leaveId);
        require_once APP_PATH . '/Libraries/EmailService.php';
        EmailService::sendLeaveApproved($leave);
    }

    /**
     * Reject leave
     */
    public static function reject(int $leaveId, int $approverId, ?string $comment = null): void
    {
        $data = [
            'status' => self::STATUS_REJECTED,
            'approver_id' => $approverId,
            'decided_at' => date('Y-m-d H:i:s'),
        ];

        if ($comment) {
            $data['approver_comment'] = $comment;
        }

        self::updateById($leaveId, $data);

        // Send email notification
        $leave = self::find($leaveId);
        require_once APP_PATH . '/Libraries/EmailService.php';
        EmailService::sendLeaveRejected($leave);
    }

    /**
     * Cancel leave
     */
    public static function cancel(int $leaveId): void
    {
        self::updateById($leaveId, [
            'status' => self::STATUS_CANCELED,
        ]);
    }

    /**
     * Request revoke (for approved leaves)
     */
    public static function requestRevoke(int $leaveId): void
    {
        self::updateById($leaveId, [
            'status' => self::STATUS_PENDED_REVOKE,
        ]);
    }

    /**
     * Get leaves for user in date range
     */
    public static function getForUserInRange(int $userId, DateTime $startDate, DateTime $endDate): array
    {
        $sql = 'SELECT l.*, lt.name as leave_type_name, lt.color
                FROM leaves l
                INNER JOIN leave_types lt ON l.leave_type_id = lt.id
                WHERE l.user_id = :user_id
                AND l.status IN (:status_approved, :status_pended_revoke)
                AND NOT (l.date_end < :start_date OR l.date_start > :end_date)
                ORDER BY l.date_start';

        return Database::fetchAll($sql, [
            'user_id' => $userId,
            'status_approved' => self::STATUS_APPROVED,
            'status_pended_revoke' => self::STATUS_PENDED_REVOKE,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d')
        ]);
    }

    /**
     * Get used allowance for user in year
     */
    public static function getUsedAllowanceForUser(int $userId, int $year): float
    {
        $sql = 'SELECT l.* FROM leaves l
                WHERE l.user_id = :user_id
                AND l.status IN (:status_approved, :status_pended_revoke)
                AND YEAR(l.date_start) = :year';

        $leaves = Database::fetchAll($sql, [
            'user_id' => $userId,
            'status_approved' => self::STATUS_APPROVED,
            'status_pended_revoke' => self::STATUS_PENDED_REVOKE,
            'year' => $year
        ]);

        $total = 0;
        foreach ($leaves as $leave) {
            $total += self::getDeductedDays($leave);
        }

        return $total;
    }

    /**
     * Get pending leaves for approver
     */
    public static function getPendingForApprover(int $approverId): array
    {
        require_once APP_PATH . '/Models/User.php';

        $supervisedDepartments = User::getSupervisedDepartments($approverId);
        $departmentIds = array_column($supervisedDepartments, 'id');

        if (empty($departmentIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($departmentIds), '?'));

        $sql = "SELECT l.*, u.name as user_name, u.lastname as user_lastname,
                lt.name as leave_type_name
                FROM leaves l
                INNER JOIN users u ON l.user_id = u.id
                INNER JOIN leave_types lt ON l.leave_type_id = lt.id
                WHERE u.department_id IN ($placeholders)
                AND l.status = ?
                ORDER BY l.created_at DESC";

        $params = array_merge($departmentIds, [self::STATUS_NEW]);

        return Database::fetchAll($sql, $params);
    }

    /**
     * Get all leaves for user
     */
    public static function getAllForUser(int $userId): array
    {
        return self::where('user_id', $userId)
            ->orderBy('date_start', 'DESC')
            ->get();
    }

    /**
     * Get status name
     */
    public static function getStatusName(int $status): string
    {
        return match($status) {
            self::STATUS_NEW => 'Pending',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_PENDED_REVOKE => 'Pending Cancellation',
            self::STATUS_CANCELED => 'Canceled',
            default => 'Unknown'
        };
    }
}

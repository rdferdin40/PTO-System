<?php
/**
 * Company Model
 * Handles company settings and configuration
 */

class Company extends Model
{
    protected static string $table = 'companies';
    protected static array $fillable = [
        'name', 'country', 'timezone', 'date_format', 'carry_over',
        'share_all_absences', 'ldap_auth_enabled', 'ldap_auth_config',
        'is_team_view_hidden', 'accrued_adjustment_enabled', 'payroll_close_time',
        'google_auth_enabled', 'google_client_id', 'google_client_secret',
        'integration_api_enabled', 'integration_api_token',
        'created_at', 'updated_at'
    ];
    protected static array $casts = [
        'id' => 'int',
        'carry_over' => 'int',
        'share_all_absences' => 'bool',
        'ldap_auth_enabled' => 'bool',
        'is_team_view_hidden' => 'bool',
        'accrued_adjustment_enabled' => 'bool',
        'payroll_close_time' => 'int',
        'google_auth_enabled' => 'bool',
        'integration_api_enabled' => 'bool',
        'ldap_auth_config' => 'json',
    ];

    /**
     * Get all departments for company
     */
    public static function getDepartments(int $companyId): array
    {
        require_once APP_PATH . '/Models/Department.php';
        return Department::where('company_id', $companyId)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get all users for company
     */
    public static function getUsers(int $companyId): array
    {
        require_once APP_PATH . '/Models/User.php';
        return User::getAllInCompany($companyId);
    }

    /**
     * Get all leave types for company
     */
    public static function getLeaveTypes(int $companyId): array
    {
        require_once APP_PATH . '/Models/LeaveType.php';
        return LeaveType::where('company_id', $companyId)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get all bank holidays for company
     */
    public static function getBankHolidays(int $companyId, ?int $year = null): array
    {
        require_once APP_PATH . '/Models/BankHoliday.php';

        if ($year) {
            $sql = 'SELECT * FROM bank_holidays
                    WHERE company_id = :company_id
                    AND YEAR(date) = :year
                    ORDER BY date';

            return Database::fetchAll($sql, [
                'company_id' => $companyId,
                'year' => $year
            ]);
        }

        return BankHoliday::where('company_id', $companyId)
            ->orderBy('date')
            ->get();
    }

    /**
     * Get schedule for company
     */
    public static function getSchedule(int $companyId): array
    {
        $sql = 'SELECT * FROM schedules WHERE company_id = :company_id LIMIT 1';
        $schedule = Database::fetchOne($sql, ['company_id' => $companyId]);

        // Return default schedule if none exists
        if (!$schedule) {
            return [
                'company_id' => $companyId,
                'monday' => 1,
                'tuesday' => 1,
                'wednesday' => 1,
                'thursday' => 1,
                'friday' => 1,
                'saturday' => 0,
                'sunday' => 0,
            ];
        }

        return $schedule;
    }

    /**
     * Check if date is working day
     */
    public static function isWorkingDay(int $companyId, DateTime $date): bool
    {
        $schedule = self::getSchedule($companyId);
        $dayName = strtolower($date->format('l')); // monday, tuesday, etc.

        return (bool) ($schedule[$dayName] ?? false);
    }

    /**
     * Check if date is bank holiday
     */
    public static function isBankHoliday(int $companyId, DateTime $date): bool
    {
        $sql = 'SELECT COUNT(*) as count FROM bank_holidays
                WHERE company_id = :company_id AND date = :date';

        $result = Database::fetchOne($sql, [
            'company_id' => $companyId,
            'date' => $date->format('Y-m-d')
        ]);

        return (int) $result['count'] > 0;
    }

    /**
     * Get working days between two dates
     */
    public static function getWorkingDaysBetween(int $companyId, DateTime $start, DateTime $end): int
    {
        $count = 0;
        $current = clone $start;

        while ($current <= $end) {
            if (self::isWorkingDay($companyId, $current) &&
                !self::isBankHoliday($companyId, $current)) {
                $count++;
            }
            $current->modify('+1 day');
        }

        return $count;
    }

    /**
     * Get payroll close date for current week
     */
    public static function getPayrollCloseDate(int $companyId): DateTime
    {
        $company = self::find($companyId);
        $closeHour = $company['payroll_close_time'] ?? 10;

        // Find last Monday
        $now = new DateTime();
        $dayOfWeek = (int)$now->format('N'); // 1=Monday, 7=Sunday

        $monday = clone $now;
        if ($dayOfWeek > 1) {
            $monday->modify('-' . ($dayOfWeek - 1) . ' days');
        }

        $monday->setTime($closeHour, 0, 0);

        return $monday;
    }

    /**
     * Check if payroll is closed
     */
    public static function isPayrollClosed(int $companyId): bool
    {
        $closeDate = self::getPayrollCloseDate($companyId);
        $now = new DateTime();

        return $now >= $closeDate;
    }

    /**
     * Get company timezone
     */
    public static function getTimezone(int $companyId): DateTimeZone
    {
        $company = self::find($companyId);
        $timezone = $company['timezone'] ?? 'America/New_York';

        return new DateTimeZone($timezone);
    }
}

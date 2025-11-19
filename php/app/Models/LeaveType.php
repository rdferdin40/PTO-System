<?php
/**
 * LeaveType Model
 * Handles different types of leave (vacation, sick, etc)
 */

class LeaveType extends Model
{
    protected static string $table = 'leave_types';
    protected static array $fillable = [
        'name', 'color', 'use_allowance', 'limit', 'sort_order',
        'company_id', 'created_at', 'updated_at'
    ];
    protected static array $casts = [
        'id' => 'int',
        'use_allowance' => 'bool',
        'limit' => 'int',
        'sort_order' => 'int',
        'company_id' => 'int',
    ];

    /**
     * Get all leave types for company
     */
    public static function getAllForCompany(int $companyId): array
    {
        return self::where('company_id', $companyId)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Get default leave type (usually vacation)
     */
    public static function getDefault(int $companyId): ?array
    {
        return self::where('company_id', $companyId)
            ->where('use_allowance', 1)
            ->orderBy('sort_order')
            ->first();
    }

    /**
     * Check if leave type is limited
     */
    public static function isLimited(array $leaveType): bool
    {
        return ($leaveType['limit'] ?? 0) > 0;
    }

    /**
     * Get remaining limit for user
     */
    public static function getRemainingLimit(int $leaveTypeId, int $userId, int $year): ?int
    {
        $leaveType = self::find($leaveTypeId);

        if (!self::isLimited($leaveType)) {
            return null; // No limit
        }

        // Count used days for this leave type
        $sql = 'SELECT COALESCE(SUM(
                    CASE
                        WHEN day_part_start = 1 AND day_part_end = 1 THEN DATEDIFF(date_end, date_start) + 1
                        WHEN day_part_start != 1 OR day_part_end != 1 THEN 0.5
                        ELSE DATEDIFF(date_end, date_start) + 1
                    END
                ), 0) as used
                FROM leaves
                WHERE user_id = :user_id
                AND leave_type_id = :leave_type_id
                AND status IN (2, 4)
                AND YEAR(date_start) = :year';

        $result = Database::fetchOne($sql, [
            'user_id' => $userId,
            'leave_type_id' => $leaveTypeId,
            'year' => $year
        ]);

        $used = (float) $result['used'];
        $limit = (int) $leaveType['limit'];

        return max(0, $limit - $used);
    }
}

<?php
/**
 * UserAllowanceAdjustment Model
 * Manual adjustments to user allowances
 */

class UserAllowanceAdjustment extends Model
{
    protected static string $table = 'user_allowance_adjustments';
    protected static array $fillable = [
        'user_id', 'year', 'adjustment', 'carried_over_allowance', 'comment', 'created_at'
    ];
    protected static array $casts = [
        'id' => 'int',
        'user_id' => 'int',
        'year' => 'int',
        'adjustment' => 'float',
        'carried_over_allowance' => 'int',
    ];

    /**
     * Get adjustments for user in year
     */
    public static function getForUserInYear(int $userId, int $year): array
    {
        $sql = 'SELECT * FROM user_allowance_adjustments
                WHERE user_id = :user_id AND year = :year
                ORDER BY created_at DESC';

        return Database::fetchAll($sql, [
            'user_id' => $userId,
            'year' => $year
        ]);
    }

    /**
     * Get total adjustment for user in year
     */
    public static function getTotalAdjustment(int $userId, int $year, bool $includeCarryOver = false): float
    {
        $sql = 'SELECT COALESCE(SUM(adjustment), 0) as total
                FROM user_allowance_adjustments
                WHERE user_id = :user_id AND year = :year';

        if (!$includeCarryOver) {
            $sql .= ' AND carried_over_allowance = 0';
        }

        $result = Database::fetchOne($sql, [
            'user_id' => $userId,
            'year' => $year
        ]);

        return (float) ($result['total'] ?? 0);
    }

    /**
     * Add adjustment
     */
    public static function addAdjustment(int $userId, int $year, float $adjustment, ?string $comment = null): int
    {
        return self::create([
            'user_id' => $userId,
            'year' => $year,
            'adjustment' => $adjustment,
            'carried_over_allowance' => 0,
            'comment' => $comment,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}

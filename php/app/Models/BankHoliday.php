<?php
/**
 * BankHoliday Model
 * Handles public/bank holidays
 */

class BankHoliday extends Model
{
    protected static string $table = 'bank_holidays';
    protected static array $fillable = [
        'name', 'date', 'company_id', 'created_at', 'updated_at'
    ];
    protected static array $casts = [
        'id' => 'int',
        'company_id' => 'int',
        'date' => 'datetime',
    ];

    /**
     * Get all bank holidays for company in year
     */
    public static function getAllForCompanyInYear(int $companyId, int $year): array
    {
        $sql = 'SELECT * FROM bank_holidays
                WHERE company_id = :company_id
                AND YEAR(date) = :year
                ORDER BY date';

        return Database::fetchAll($sql, [
            'company_id' => $companyId,
            'year' => $year
        ]);
    }

    /**
     * Import bank holidays from array
     */
    public static function import(int $companyId, array $holidays): int
    {
        $count = 0;

        foreach ($holidays as $holiday) {
            // Check if already exists
            $existing = self::findBy('date', $holiday['date']);

            if (!$existing) {
                self::create([
                    'name' => $holiday['name'],
                    'date' => $holiday['date'],
                    'company_id' => $companyId,
                ]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get upcoming bank holidays
     */
    public static function getUpcoming(int $companyId, int $limit = 5): array
    {
        $sql = 'SELECT * FROM bank_holidays
                WHERE company_id = :company_id
                AND date >= CURDATE()
                ORDER BY date
                LIMIT :limit';

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->bindValue(':company_id', $companyId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}

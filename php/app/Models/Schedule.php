<?php
/**
 * Schedule Model
 * Company work schedule (which days are working days)
 */

class Schedule extends Model
{
    protected static string $table = 'schedules';
    protected static array $fillable = [
        'company_id', 'monday', 'tuesday', 'wednesday', 'thursday',
        'friday', 'saturday', 'sunday', 'created_at', 'updated_at'
    ];
    protected static array $casts = [
        'id' => 'int',
        'company_id' => 'int',
        'monday' => 'bool',
        'tuesday' => 'bool',
        'wednesday' => 'bool',
        'thursday' => 'bool',
        'friday' => 'bool',
        'saturday' => 'bool',
        'sunday' => 'bool',
    ];

    /**
     * Get schedule for company
     */
    public static function getForCompany(int $companyId): ?array
    {
        return self::findBy('company_id', $companyId);
    }

    /**
     * Get or create schedule for company
     */
    public static function getOrCreateForCompany(int $companyId): array
    {
        $schedule = self::getForCompany($companyId);

        if (!$schedule) {
            // Create default schedule (Mon-Fri)
            $id = self::create([
                'company_id' => $companyId,
                'monday' => 1,
                'tuesday' => 1,
                'wednesday' => 1,
                'thursday' => 1,
                'friday' => 1,
                'saturday' => 0,
                'sunday' => 0,
            ]);

            $schedule = self::find($id);
        }

        return $schedule;
    }

    /**
     * Update schedule for company
     */
    public static function updateForCompany(int $companyId, array $schedule): void
    {
        $existing = self::getForCompany($companyId);

        if ($existing) {
            self::updateById($existing['id'], $schedule);
        } else {
            self::create(array_merge($schedule, ['company_id' => $companyId]));
        }
    }

    /**
     * Check if date is working day
     */
    public static function isWorkingDay(array $schedule, DateTime $date): bool
    {
        $dayName = strtolower($date->format('l')); // monday, tuesday, etc.
        return (bool) ($schedule[$dayName] ?? false);
    }

    /**
     * Get working days in week
     */
    public static function getWorkingDaysInWeek(array $schedule): int
    {
        $count = 0;
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        foreach ($days as $day) {
            if ($schedule[$day]) {
                $count++;
            }
        }

        return $count;
    }
}

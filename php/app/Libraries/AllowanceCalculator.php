<?php
/**
 * Allowance Calculator
 * CRITICAL BUSINESS LOGIC - Calculate user's total allowance
 *
 * Allowance = nominal + carry_over + manual_adjustment + employment_prorate + accrual
 */

class AllowanceCalculator
{
    /**
     * Calculate total allowance for user in year
     *
     * This is the main entry point for allowance calculation
     */
    public function calculateAllowance(array $user, int $year): float
    {
        // Get all components
        $nominal = $this->getNominalAllowance($user);
        $carryOver = $this->getCarryOverAllowance($user, $year);
        $manualAdjustment = $this->getManualAdjustment($user['id'], $year);
        $employmentProrate = $this->getEmploymentRangeAdjustment($user, $nominal, $year);
        $accruedAdjustment = $this->getAccruedAdjustment($user, $year);

        // Total allowance
        $total = $nominal + $carryOver + $manualAdjustment + $employmentProrate + $accruedAdjustment;

        return max(0, $total); // Never negative
    }

    /**
     * Get nominal allowance (base from department)
     */
    private function getNominalAllowance(array $user): float
    {
        require_once APP_PATH . '/Models/Department.php';
        $department = Department::find($user['department_id']);

        return (float) ($department['allowance'] ?? 20);
    }

    /**
     * Get carry over from previous year
     */
    private function getCarryOverAllowance(array $user, int $year): float
    {
        require_once APP_PATH . '/Models/Company.php';
        $company = Company::find($user['company_id']);
        $maxCarryOver = (int) ($company['carry_over'] ?? 0);

        if ($maxCarryOver === 0) {
            return 0;
        }

        $previousYear = $year - 1;

        // Get total and used allowance from previous year
        $previousTotal = $this->calculateAllowance($user, $previousYear);
        $previousUsed = $this->getUsedAllowance($user['id'], $previousYear);

        $unused = $previousTotal - $previousUsed;

        // Cap at max carry over
        return min(max(0, $unused), $maxCarryOver);
    }

    /**
     * Get manual adjustment
     */
    private function getManualAdjustment(int $userId, int $year): float
    {
        require_once APP_PATH . '/Models/UserAllowanceAdjustment.php';
        return UserAllowanceAdjustment::getTotalAdjustment($userId, $year, false);
    }

    /**
     * Get employment range prorating
     * Reduces allowance for partial year employment
     */
    private function getEmploymentRangeAdjustment(array $user, float $nominalAllowance, int $year): float
    {
        $startDate = isset($user['start_date']) ? new DateTime($user['start_date']) : null;
        $endDate = isset($user['end_date']) ? new DateTime($user['end_date']) : null;

        if (!$startDate) {
            return 0; // No start date, no adjustment
        }

        $startYear = (int) $startDate->format('Y');
        $endYear = $endDate ? (int) $endDate->format('Y') : null;

        // Only apply if started OR ended in current year
        if ($year != $startYear && (!$endYear || $endYear > $year)) {
            return 0;
        }

        // Determine employment period for this year
        $periodStart = ($startYear == $year)
            ? $startDate
            : new DateTime("$year-01-01");

        $periodEnd = ($endYear && $endYear <= $year)
            ? $endDate
            : new DateTime("$year-12-31");

        // Calculate days in employment
        $daysDiff = $periodStart->diff($periodEnd)->days;

        // Prorate allowance
        $proratedAllowance = round(($nominalAllowance * $daysDiff) / 365);

        // Return negative adjustment (reduction from nominal)
        return -1 * ($nominalAllowance - $proratedAllowance);
    }

    /**
     * Get accrued adjustment
     * Locks future allowance if accrual is enabled
     */
    private function getAccruedAdjustment(array $user, int $year): float
    {
        require_once APP_PATH . '/Models/Company.php';
        $company = Company::find($user['company_id']);

        if (!($company['accrued_adjustment_enabled'] ?? false)) {
            return 0;
        }

        $now = new DateTime();
        $currentYear = (int) $now->format('Y');

        // Only apply to current year
        if ($year !== $currentYear) {
            return 0;
        }

        // Calculate accrued allowance based on months passed
        $currentMonth = (int) $now->format('m');
        $monthsPassed = $currentMonth;

        $totalAllowance = $this->getNominalAllowance($user);
        $accruedSoFar = ($totalAllowance / 12) * $monthsPassed;

        // Get used allowance
        $usedAllowance = $this->getUsedAllowance($user['id'], $year);

        // If used more than accrued, reduce available allowance
        if ($usedAllowance > $accruedSoFar) {
            return -1 * ($usedAllowance - $accruedSoFar);
        }

        return 0;
    }

    /**
     * Get used allowance for year
     */
    private function getUsedAllowance(int $userId, int $year): float
    {
        require_once APP_PATH . '/Models/Leave.php';

        $sql = 'SELECT l.* FROM leaves l
                WHERE l.user_id = :user_id
                AND l.status IN (2, 4)
                AND YEAR(l.date_start) = :year';

        $leaves = Database::fetchAll($sql, [
            'user_id' => $userId,
            'year' => $year
        ]);

        $total = 0;
        foreach ($leaves as $leave) {
            $total += Leave::getDeductedDays($leave);
        }

        return $total;
    }

    /**
     * Get allowance breakdown (for debugging/display)
     */
    public function getAllowanceBreakdown(array $user, int $year): array
    {
        $nominal = $this->getNominalAllowance($user);
        $carryOver = $this->getCarryOverAllowance($user, $year);
        $manualAdjustment = $this->getManualAdjustment($user['id'], $year);
        $employmentProrate = $this->getEmploymentRangeAdjustment($user, $nominal, $year);
        $accruedAdjustment = $this->getAccruedAdjustment($user, $year);

        return [
            'nominal' => $nominal,
            'carry_over' => $carryOver,
            'manual_adjustment' => $manualAdjustment,
            'employment_prorate' => $employmentProrate,
            'accrued_adjustment' => $accruedAdjustment,
            'total' => max(0, $nominal + $carryOver + $manualAdjustment + $employmentProrate + $accruedAdjustment),
        ];
    }
}

<?php
/**
 * Calendar Generator
 * Generates calendar data for views and iCal feeds
 */

class CalendarGenerator
{
    /**
     * Generate calendar data for month
     */
    public function generateMonth(int $year, int $month, int $userId): array
    {
        require_once APP_PATH . '/Models/Leave.php';
        require_once APP_PATH . '/Models/BankHoliday.php';
        require_once APP_PATH . '/Models/User.php';

        $user = User::find($userId);
        $startDate = new DateTime("$year-$month-01");
        $endDate = clone $startDate;
        $endDate->modify('last day of this month');

        // Get leaves for user in this month
        $leaves = Leave::getForUserInRange($userId, $startDate, $endDate);

        // Get bank holidays
        $bankHolidays = BankHoliday::getAllForCompanyInYear($user['company_id'], $year);

        // Generate days array
        $days = [];
        $current = clone $startDate;

        while ($current <= $endDate) {
            $dateStr = $current->format('Y-m-d');

            $dayData = [
                'date' => $dateStr,
                'day' => (int)$current->format('j'),
                'day_of_week' => $current->format('l'),
                'is_weekend' => in_array((int)$current->format('N'), [6, 7]),
                'is_bank_holiday' => false,
                'bank_holiday_name' => null,
                'leaves' => [],
            ];

            // Check for bank holidays
            foreach ($bankHolidays as $holiday) {
                if ($holiday['date'] === $dateStr) {
                    $dayData['is_bank_holiday'] = true;
                    $dayData['bank_holiday_name'] = $holiday['name'];
                    break;
                }
            }

            // Check for leaves
            foreach ($leaves as $leave) {
                $leaveDays = Leave::getDays($leave);
                foreach ($leaveDays as $leaveDay) {
                    if ($leaveDay['date'] === $dateStr) {
                        $dayData['leaves'][] = [
                            'id' => $leave['id'],
                            'leave_type_name' => $leave['leave_type_name'],
                            'color' => $leave['color'] ?? '#0066cc',
                            'day_part' => $leaveDay['day_part'],
                            'status' => $leave['status'],
                        ];
                    }
                }
            }

            $days[] = $dayData;
            $current->modify('+1 day');
        }

        return [
            'year' => $year,
            'month' => $month,
            'month_name' => $startDate->format('F'),
            'days' => $days,
        ];
    }

    /**
     * Generate iCal feed for user
     */
    public function generateICalFeed(int $userId): string
    {
        require_once APP_PATH . '/Models/User.php';
        require_once APP_PATH . '/Models/Leave.php';

        $user = User::find($userId);
        $currentYear = (int)date('Y');

        // Get leaves for current year +/- 1 year
        $startDate = new DateTime(($currentYear - 1) . '-01-01');
        $endDate = new DateTime(($currentYear + 1) . '-12-31');

        $leaves = Leave::getForUserInRange($userId, $startDate, $endDate);

        // Generate iCal
        $ical = "BEGIN:VCALENDAR\r\n";
        $ical .= "VERSION:2.0\r\n";
        $ical .= "PRODID:-//TimeOff Management//EN\r\n";
        $ical .= "CALSCALE:GREGORIAN\r\n";
        $ical .= "METHOD:PUBLISH\r\n";
        $ical .= "X-WR-CALNAME:TimeOff - " . User::getFullName($user) . "\r\n";
        $ical .= "X-WR-TIMEZONE:UTC\r\n";

        foreach ($leaves as $leave) {
            $ical .= $this->generateICalEvent($leave);
        }

        $ical .= "END:VCALENDAR\r\n";

        return $ical;
    }

    /**
     * Generate single iCal event
     */
    private function generateICalEvent(array $leave): string
    {
        $start = new DateTime($leave['date_start']);
        $end = new DateTime($leave['date_end']);
        $end->modify('+1 day'); // iCal end date is exclusive

        $summary = $leave['leave_type_name'];
        $description = $leave['employee_comment'] ?? '';
        $status = Leave::getStatusName($leave['status']);

        $event = "BEGIN:VEVENT\r\n";
        $event .= "UID:leave-{$leave['id']}@timeoff\r\n";
        $event .= "DTSTART;VALUE=DATE:" . $start->format('Ymd') . "\r\n";
        $event .= "DTEND;VALUE=DATE:" . $end->format('Ymd') . "\r\n";
        $event .= "SUMMARY:$summary ($status)\r\n";
        $event .= "DESCRIPTION:$description\r\n";
        $event .= "STATUS:CONFIRMED\r\n";
        $event .= "TRANSP:OPAQUE\r\n";
        $event .= "END:VEVENT\r\n";

        return $event;
    }

    /**
     * Generate team view data
     */
    public function generateTeamView(int $userId, DateTime $startDate, DateTime $endDate): array
    {
        require_once APP_PATH . '/Models/User.php';
        require_once APP_PATH . '/Models/Department.php';

        $user = User::find($userId);
        $department = Department::find($user['department_id']);

        return Department::generateTeamView($department['id'], $startDate, $endDate);
    }
}

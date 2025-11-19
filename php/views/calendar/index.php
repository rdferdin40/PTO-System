<div class="calendar-page">
    <div class="page-header">
        <h1><?= View::e($calendar['month_name']) ?> <?= $calendar['year'] ?></h1>

        <div class="calendar-nav">
            <?php
            $prevMonth = $month - 1;
            $prevYear = $year;
            if ($prevMonth < 1) {
                $prevMonth = 12;
                $prevYear--;
            }

            $nextMonth = $month + 1;
            $nextYear = $year;
            if ($nextMonth > 12) {
                $nextMonth = 1;
                $nextYear++;
            }
            ?>
            <a href="/calendar?year=<?= $prevYear ?>&month=<?= $prevMonth ?>" class="btn">Previous</a>
            <a href="/calendar" class="btn">Today</a>
            <a href="/calendar?year=<?= $nextYear ?>&month=<?= $nextMonth ?>" class="btn">Next</a>
        </div>
    </div>

    <div class="allowance-summary">
        <h2>Your Allowance</h2>
        <div class="stats">
            <div class="stat">
                <span class="label">Total:</span>
                <span class="value"><?= number_format($allowance['total'], 1) ?> days</span>
            </div>
            <div class="stat">
                <span class="label">Used:</span>
                <span class="value"><?= number_format($used, 1) ?> days</span>
            </div>
            <div class="stat">
                <span class="label">Remaining:</span>
                <span class="value"><?= number_format($remaining, 1) ?> days</span>
            </div>
        </div>

        <details>
            <summary>Breakdown</summary>
            <ul>
                <li>Nominal: <?= number_format($allowance['nominal'], 1) ?> days</li>
                <?php if ($allowance['carry_over'] != 0): ?>
                    <li>Carry Over: <?= number_format($allowance['carry_over'], 1) ?> days</li>
                <?php endif; ?>
                <?php if ($allowance['manual_adjustment'] != 0): ?>
                    <li>Manual Adjustment: <?= number_format($allowance['manual_adjustment'], 1) ?> days</li>
                <?php endif; ?>
                <?php if ($allowance['employment_prorate'] != 0): ?>
                    <li>Employment Prorate: <?= number_format($allowance['employment_prorate'], 1) ?> days</li>
                <?php endif; ?>
                <?php if ($allowance['accrued_adjustment'] != 0): ?>
                    <li>Accrued Adjustment: <?= number_format($allowance['accrued_adjustment'], 1) ?> days</li>
                <?php endif; ?>
            </ul>
        </details>
    </div>

    <div class="calendar-grid">
        <div class="calendar-header">
            <div class="day-header">Sun</div>
            <div class="day-header">Mon</div>
            <div class="day-header">Tue</div>
            <div class="day-header">Wed</div>
            <div class="day-header">Thu</div>
            <div class="day-header">Fri</div>
            <div class="day-header">Sat</div>
        </div>

        <div class="calendar-body">
            <?php
            $firstDay = (new DateTime($calendar['year'] . '-' . $calendar['month'] . '-01'))->format('w');

            // Empty cells before first day
            for ($i = 0; $i < $firstDay; $i++): ?>
                <div class="calendar-day empty"></div>
            <?php endfor; ?>

            <?php foreach ($calendar['days'] as $day): ?>
                <div class="calendar-day <?= $day['is_weekend'] ? 'weekend' : '' ?> <?= $day['is_bank_holiday'] ? 'bank-holiday' : '' ?>">
                    <div class="day-number"><?= $day['day'] ?></div>

                    <?php if ($day['is_bank_holiday']): ?>
                        <div class="holiday-label"><?= View::e($day['bank_holiday_name']) ?></div>
                    <?php endif; ?>

                    <?php foreach ($day['leaves'] as $leave): ?>
                        <div class="leave-marker" style="background-color: <?= View::e($leave['color']) ?>">
                            <?= View::e($leave['leave_type_name']) ?>
                            <?php if ($leave['day_part'] == 2): ?>
                                (AM)
                            <?php elseif ($leave['day_part'] == 3): ?>
                                (PM)
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="actions">
        <a href="/requests/new" class="btn btn-primary">Request Leave</a>
        <a href="/calendar/teamview" class="btn">Team Calendar</a>
    </div>
</div>

<style>
.calendar-page {
    max-width: 1200px;
    margin: 0 auto;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.calendar-nav {
    display: flex;
    gap: 0.5rem;
}

.allowance-summary {
    background: white;
    padding: 1.5rem;
    border-radius: 8px;
    margin-bottom: 2rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.stats {
    display: flex;
    gap: 2rem;
    margin: 1rem 0;
}

.stat {
    display: flex;
    flex-direction: column;
}

.stat .label {
    font-size: 0.9rem;
    color: #666;
}

.stat .value {
    font-size: 1.5rem;
    font-weight: 600;
    color: #0066cc;
}

.calendar-grid {
    background: white;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.calendar-header {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    background: #f5f5f5;
    border-bottom: 2px solid #ddd;
}

.day-header {
    padding: 1rem;
    text-align: center;
    font-weight: 600;
}

.calendar-body {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
}

.calendar-day {
    min-height: 100px;
    padding: 0.5rem;
    border: 1px solid #e0e0e0;
}

.calendar-day.weekend {
    background: #f9f9f9;
}

.calendar-day.bank-holiday {
    background: #fff3cd;
}

.day-number {
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.leave-marker {
    font-size: 0.75rem;
    padding: 0.25rem;
    margin: 0.25rem 0;
    border-radius: 3px;
    color: white;
}

.holiday-label {
    font-size: 0.75rem;
    color: #856404;
    font-style: italic;
}

.actions {
    margin-top: 2rem;
    display: flex;
    gap: 1rem;
}

.btn {
    padding: 0.75rem 1.5rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    text-decoration: none;
    color: #333;
    background: white;
    cursor: pointer;
}

.btn-primary {
    background: #0066cc;
    color: white;
    border-color: #0066cc;
}
</style>

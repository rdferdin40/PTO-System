<?php
/**
 * Calendar Controller
 * Displays calendar views and team calendar
 */

class CalendarController extends Controller
{
    /**
     * Show user's calendar
     */
    public function index(Request $request): Response
    {
        $this->requireAuth();

        $user = $this->getUser();

        // Get year and month from query or use current
        $year = (int)($request->query('year') ?? date('Y'));
        $month = (int)($request->query('month') ?? date('m'));

        // Generate calendar data
        require_once APP_PATH . '/Libraries/CalendarGenerator.php';
        $generator = new CalendarGenerator();
        $calendar = $generator->generateMonth($year, $month, $user['id']);

        // Get allowance info
        require_once APP_PATH . '/Models/User.php';
        require_once APP_PATH . '/Libraries/AllowanceCalculator.php';

        $calculator = new AllowanceCalculator();
        $allowance = $calculator->getAllowanceBreakdown($user, $year);
        $used = User::getUsedAllowance($user['id'], $year);

        return $this->view('calendar/index', [
            'title' => 'Calendar',
            'calendar' => $calendar,
            'allowance' => $allowance,
            'used' => $used,
            'remaining' => max(0, $allowance['total'] - $used),
            'year' => $year,
            'month' => $month,
        ]);
    }

    /**
     * Show team view calendar
     */
    public function teamView(Request $request): Response
    {
        $this->requireAuth();

        $user = $this->getUser();

        // Get date range from query or use current week
        $startDate = $request->query('start')
            ? new DateTime($request->query('start'))
            : new DateTime('monday this week');

        $endDate = $request->query('end')
            ? new DateTime($request->query('end'))
            : (clone $startDate)->modify('+7 days');

        // Generate team view
        require_once APP_PATH . '/Libraries/CalendarGenerator.php';
        $generator = new CalendarGenerator();
        $teamData = $generator->generateTeamView($user['id'], $startDate, $endDate);

        return $this->view('calendar/team-view', [
            'title' => 'Team Calendar',
            'team_data' => $teamData,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);
    }

    /**
     * Show calendar feeds page
     */
    public function feeds(Request $request): Response
    {
        $this->requireAuth();

        $user = $this->getUser();

        // Generate feed URL
        $feedToken = bin2hex(random_bytes(16));
        // Would need to store this token in user_feeds table

        $feedUrl = CONFIG['app']['url'] . '/calendar/ical?token=' . $feedToken;

        return $this->view('calendar/feeds', [
            'title' => 'Calendar Feeds',
            'feed_url' => $feedUrl,
        ]);
    }

    /**
     * iCal feed
     */
    public function icalFeed(Request $request): Response
    {
        $token = $request->query('token');

        if (!$token) {
            return Response::error('Invalid token', 400);
        }

        // Validate token and get user (would query user_feeds table)
        $sql = 'SELECT user_id FROM user_feeds WHERE feed_token = :token LIMIT 1';
        $feed = Database::fetchOne($sql, ['token' => $token]);

        if (!$feed) {
            return Response::error('Invalid feed token', 403);
        }

        // Generate iCal
        require_once APP_PATH . '/Libraries/CalendarGenerator.php';
        $generator = new CalendarGenerator();
        $ical = $generator->generateICalFeed($feed['user_id']);

        return new Response($ical, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="timeoff.ics"',
        ]);
    }
}

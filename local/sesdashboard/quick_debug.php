<?php
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');

// SECURITY: Add proper authentication
require_login();
$context = context_system::instance();
require_capability('local/sesdashboard:view', $context);

global $DB;

echo "<h1>Quick Status Debug</h1>";

// Check what statuses exist in the database
$sql = "SELECT DISTINCT status FROM {local_sesdashboard_mail} ORDER BY status";
$statuses = $DB->get_records_sql($sql);

echo "<h2>All Status Values in Database:</h2>";
echo "<ul>";
foreach ($statuses as $status) {
    echo "<li>'" . htmlspecialchars($status->status) . "'</li>";
}
echo "</ul>";

// Check last 7 days data
$today = strtotime('today');
$timestart = $today - (6 * DAYSECS);
$timeend = time();

$sql2 = "SELECT status, COUNT(*) as count FROM {local_sesdashboard_mail} WHERE timecreated >= ? AND timecreated <= ? GROUP BY status ORDER BY status";
$results = $DB->get_records_sql($sql2, [$timestart, $timeend]);

echo "<h2>Last 7 Days Status Counts:</h2>";
echo "<table border='1'>";
echo "<tr><th>Status</th><th>Count</th></tr>";
foreach ($results as $result) {
    echo "<tr><td>'" . htmlspecialchars($result->status) . "'</td><td>" . $result->count . "</td></tr>";
}
echo "</table>";

// Check by date breakdown
$sql3 = "SELECT FROM_UNIXTIME(timecreated, '%Y-%m-%d') AS event_date,
                status,
                COUNT(*) AS count
         FROM {local_sesdashboard_mail}
         WHERE timecreated >= ? AND timecreated <= ?
         GROUP BY FROM_UNIXTIME(timecreated, '%Y-%m-%d'), status
         ORDER BY event_date DESC, status ASC";

$daily_results = $DB->get_records_sql($sql3, [$timestart, $timeend]);

echo "<h2>Daily Breakdown:</h2>";
echo "<table border='1'>";
echo "<tr><th>Date</th><th>Status</th><th>Count</th></tr>";
foreach ($daily_results as $result) {
    echo "<tr><td>" . $result->event_date . "</td><td>'" . htmlspecialchars($result->status) . "'</td><td>" . $result->count . "</td></tr>";
}
echo "</table>"; 
<?php
/**
 * Web-accessible debug script to check data consistency
 * Access this at: https://target.betterworklearning.com/local/sesdashboard/web_debug.php
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');

// SECURITY: Add proper authentication
require_login();
$context = context_system::instance();
require_capability('local/sesdashboard:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/sesdashboard/web_debug.php'));
$PAGE->set_title('SES Dashboard Debug');
$PAGE->set_heading('SES Dashboard Debug');

echo $OUTPUT->header();

global $DB;

echo "<h2>SES Dashboard Data Consistency Debug</h2>";
echo "<div class='alert alert-info'><strong>Access URL:</strong> https://target.betterworklearning.com/local/sesdashboard/web_debug.php</div>";

$repository = new \local_sesdashboard\repositories\email_repository();
$timeframe = 7;

echo "<div class='alert alert-info'>";
echo "<strong>Current Time:</strong> " . date('Y-m-d H:i:s') . " (Timestamp: " . time() . ")<br>";
echo "<strong>Timeframe:</strong> $timeframe days<br>";
echo "</div>";

// Method 1: Dashboard stats (pie chart) - EXACT method from index.php
echo "<h3>1. Dashboard Pie Chart Method (get_dashboard_stats)</h3>";
$timestart1 = time() - (($timeframe - 1) * DAYSECS);
echo "<p><strong>Timestart:</strong> $timestart1 (" . date('Y-m-d H:i:s', $timestart1) . ")</p>";

$dashboard_stats = $repository->get_dashboard_stats($timeframe);
$pie_total = 0;
$pie_status_counts = [];
echo "<table class='table table-bordered'>";
echo "<tr><th>Status</th><th>Count</th></tr>";
foreach ($dashboard_stats as $status => $data) {
    $count = (int)$data->count;
    echo "<tr><td>$status</td><td>{$count}</td></tr>";
    $pie_total += $count;
    $pie_status_counts[$status] = $count;
}
echo "<tr><td><strong>TOTAL</strong></td><td><strong>$pie_total</strong></td></tr>";
echo "</table>";

// Method 2: Daily stats (line chart) - EXACT method from index.php
echo "<h3>2. Dashboard Line Chart Method (get_daily_stats) - EFFICIENT VERSION</h3>";
$daily_stats = $repository->get_daily_stats($timeframe);
$line_totals = [
    'sent' => array_sum($daily_stats['sent']),
    'delivered' => array_sum($daily_stats['delivered']),
    'bounced' => array_sum($daily_stats['bounced']),
    'opened' => array_sum($daily_stats['opened'])
];
$line_total = array_sum($line_totals);

echo "<table class='table table-bordered'>";
echo "<tr><th>Status</th><th>Count</th><th>Source Breakdown</th></tr>";

// Calculate source breakdown for display
$opened_from_opens = ($pie_status_counts['Open'] ?? 0);
$opened_from_clicks = ($pie_status_counts['Click'] ?? 0);
$delivered_from_delivery = ($pie_status_counts['Delivery'] ?? 0);
$delivered_from_delay = ($pie_status_counts['DeliveryDelay'] ?? 0);

echo "<tr><td>Send</td><td>{$line_totals['sent']}</td><td>Send: {$pie_status_counts['Send']}</td></tr>";
echo "<tr><td>Delivery</td><td>{$line_totals['delivered']}</td><td>Delivery: $delivered_from_delivery + DeliveryDelay: $delivered_from_delay</td></tr>";
echo "<tr><td>Bounce</td><td>{$line_totals['bounced']}</td><td>Bounce: {$pie_status_counts['Bounce']}</td></tr>";
echo "<tr><td>Open</td><td>{$line_totals['opened']}</td><td>Open: $opened_from_opens + Click: $opened_from_clicks</td></tr>";
echo "<tr><td><strong>TOTAL</strong></td><td><strong>$line_total</strong></td><td>-</td></tr>";
echo "</table>";

// Summary
echo "<h3>3. Summary</h3>";
echo "<table class='table table-bordered'>";
echo "<tr><th>Method</th><th>Total Count</th><th>Difference from Pie Chart</th><th>Status</th></tr>";
echo "<tr><td>Pie Chart (Dashboard)</td><td><strong>$pie_total</strong></td><td>0</td><td>✅ Reference</td></tr>";
echo "<tr><td>Line Chart (Dashboard)</td><td><strong>$line_total</strong></td><td>" . ($line_total - $pie_total) . "</td><td>" . ($line_total == $pie_total ? '✅' : '❌') . "</td></tr>";
echo "</table>";

if ($pie_total == $line_total) {
    echo "<div class='alert alert-success'><strong>✅ ALL METHODS ARE NOW CONSISTENT!</strong><br>The new efficient SQL query in <code>get_daily_stats</code> has resolved the data mismatch caused by PHP memory limits.</div>";
} else {
    echo "<div class='alert alert-danger'><strong>❌ INCONSISTENCY STILL EXISTS!</strong><br>The line chart total ($line_total) does not match the pie chart total ($pie_total).</div>";
}

echo "<h3>4. SQL Comparison</h3>";
$pie_sql = "<code>SELECT status, COUNT(*) ... WHERE timecreated >= $timestart1 GROUP BY status</code>";
$line_sql = "<code>SELECT DATE(FROM_UNIXTIME(timecreated)), status, COUNT(*) ... WHERE timecreated >= $timestart1 GROUP BY DATE, status</code>";

echo "<table class='table table-bordered'>";
echo "<tr><th>Chart</th><th>SQL Method</th></tr>";
echo "<tr><td>Pie Chart</td><td>$pie_sql</td></tr>";
echo "<tr><td>Line Chart</td><td>$line_sql</td></tr>";
echo "</table>";

echo "<div class='alert alert-info'>Both queries now perform aggregation in the database, which is efficient and scalable. The Line Chart processes the pre-aggregated daily data to build its totals.</div>";


echo $OUTPUT->footer();
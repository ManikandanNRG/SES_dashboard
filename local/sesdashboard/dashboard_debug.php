<?php
/**
 * Dashboard Debug Script
 * Compare dashboard stats vs report stats to find discrepancy
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');

// SECURITY: Add proper authentication
require_login();
$context = context_system::instance();
require_capability('local/sesdashboard:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/sesdashboard/dashboard_debug.php'));
$PAGE->set_title('SES Dashboard Debug');
$PAGE->set_heading('SES Dashboard Debug');

echo $OUTPUT->header();

global $DB;

echo "<h1>🔍 Dashboard vs Report Data Comparison</h1>";
echo "<div class='alert alert-info'><strong>Current Time:</strong> " . date('Y-m-d H:i:s') . " (Timestamp: " . time() . ")</div>";

$repository = new \local_sesdashboard\repositories\email_repository();
$timeframe = optional_param('timeframe', 7, PARAM_INT);

// Validate timeframe
if (!in_array($timeframe, [3, 5, 7])) {
    $timeframe = 7;
}

echo "<h2>Testing Timeframe: $timeframe days</h2>";

// 1. DASHBOARD CALCULATION
echo "<h3>1. Dashboard Statistics Method</h3>";

$dashboard_stats = $repository->get_dashboard_stats($timeframe);
$dashboard_total = 0;

echo "<table class='table table-bordered'>";
echo "<tr><th>Status</th><th>Count</th></tr>";
foreach ($dashboard_stats as $status => $data) {
    echo "<tr><td>$status</td><td>{$data->count}</td></tr>";
    $dashboard_total += $data->count;
}
echo "<tr><td><strong>TOTAL</strong></td><td><strong>$dashboard_total</strong></td></tr>";
echo "</table>";

// Extract specific counts for dashboard display
$send_count = isset($dashboard_stats['Send']) ? $dashboard_stats['Send']->count : 0;
$delivery_count = isset($dashboard_stats['Delivery']) ? $dashboard_stats['Delivery']->count : 0;
$bounce_count = isset($dashboard_stats['Bounce']) ? $dashboard_stats['Bounce']->count : 0;
$open_count = isset($dashboard_stats['Open']) ? $dashboard_stats['Open']->count : 0;

echo "<h4>Dashboard Display Values:</h4>";
echo "<p><strong>Send Count:</strong> $send_count</p>";
echo "<p><strong>Delivery Count:</strong> $delivery_count</p>";
echo "<p><strong>Bounce Count:</strong> $bounce_count</p>";
echo "<p><strong>Open Count:</strong> $open_count</p>";

// 2. REPORT PAGE CALCULATION
echo "<h3>2. Report Page Statistics Method</h3>";

// Calculate timestart same as dashboard
$today = strtotime('today');
$timestart = $today - (($timeframe - 1) * DAYSECS);

echo "<p><strong>Today:</strong> " . date('Y-m-d H:i:s', $today) . "</p>";
echo "<p><strong>Timestart:</strong> " . date('Y-m-d H:i:s', $timestart) . "</p>";

// Use the same method as report page
$report_total = $repository->get_filtered_count_by_timestamp('', $timestart, time(), '');
echo "<p><strong>Report Total:</strong> $report_total</p>";

// Get status breakdown for report
$report_status_breakdown = [];
foreach (['Send', 'Delivery', 'Bounce', 'Open', 'Click', 'DeliveryDelay'] as $status) {
    $count = $repository->get_filtered_count_by_timestamp($status, $timestart, time(), '');
    if ($count > 0) {
        $report_status_breakdown[$status] = $count;
    }
}

echo "<h4>Report Status Breakdown:</h4>";
echo "<table class='table table-bordered'>";
echo "<tr><th>Status</th><th>Count</th></tr>";
$report_breakdown_total = 0;
foreach ($report_status_breakdown as $status => $count) {
    echo "<tr><td>$status</td><td>$count</td></tr>";
    $report_breakdown_total += $count;
}
echo "<tr><td><strong>TOTAL</strong></td><td><strong>$report_breakdown_total</strong></td></tr>";
echo "</table>";

// 3. DIRECT DATABASE QUERY
echo "<h3>3. Direct Database Query</h3>";

$direct_query = "SELECT status, COUNT(*) as count FROM {local_sesdashboard_mail} WHERE timecreated >= ? GROUP BY status ORDER BY status";
$direct_results = $DB->get_records_sql($direct_query, [$timestart]);

echo "<table class='table table-bordered'>";
echo "<tr><th>Status</th><th>Count</th></tr>";
$direct_total = 0;
foreach ($direct_results as $result) {
    echo "<tr><td>{$result->status}</td><td>{$result->count}</td></tr>";
    $direct_total += $result->count;
}
echo "<tr><td><strong>TOTAL</strong></td><td><strong>$direct_total</strong></td></tr>";
echo "</table>";

// 4. ALL RECORDS (NO TIME FILTER)
echo "<h3>4. All Records (No Time Filter)</h3>";

$all_query = "SELECT status, COUNT(*) as count FROM {local_sesdashboard_mail} GROUP BY status ORDER BY status";
$all_results = $DB->get_records_sql($all_query);

echo "<table class='table table-bordered'>";
echo "<tr><th>Status</th><th>Count</th></tr>";
$all_total = 0;
foreach ($all_results as $result) {
    echo "<tr><td>{$result->status}</td><td>{$result->count}</td></tr>";
    $all_total += $result->count;
}
echo "<tr><td><strong>TOTAL</strong></td><td><strong>$all_total</strong></td></tr>";
echo "</table>";

// 5. TODAY'S RECORDS ONLY
echo "<h3>5. Today's Records Only</h3>";

$today_start = strtotime('today');
$today_query = "SELECT status, COUNT(*) as count FROM {local_sesdashboard_mail} WHERE timecreated >= ? GROUP BY status ORDER BY status";
$today_results = $DB->get_records_sql($today_query, [$today_start]);

echo "<p><strong>Today Start:</strong> " . date('Y-m-d H:i:s', $today_start) . "</p>";

if (empty($today_results)) {
    echo "<div class='alert alert-warning'><strong>NO RECORDS FOR TODAY!</strong> This explains why dashboard shows low numbers.</div>";
} else {
    echo "<table class='table table-bordered'>";
    echo "<tr><th>Status</th><th>Count</th></tr>";
    $today_total = 0;
    foreach ($today_results as $result) {
        echo "<tr><td>{$result->status}</td><td>{$result->count}</td></tr>";
        $today_total += $result->count;
    }
    echo "<tr><td><strong>TOTAL</strong></td><td><strong>$today_total</strong></td></tr>";
    echo "</table>";
}

// 6. SAMPLE RECENT RECORDS
echo "<h3>6. Sample Recent Records</h3>";

$recent_sample = $DB->get_records_sql("
    SELECT id, email, subject, status, timecreated 
    FROM {local_sesdashboard_mail} 
    ORDER BY timecreated DESC 
    LIMIT 20
");

echo "<table class='table table-bordered'>";
echo "<tr><th>ID</th><th>Email</th><th>Status</th><th>Time</th><th>Date</th><th>Within Timeframe?</th></tr>";

foreach ($recent_sample as $record) {
    $within_timeframe = ($record->timecreated >= $timestart) ? '✅ YES' : '❌ NO';
    $formatted_time = date('Y-m-d H:i:s', $record->timecreated);
    $formatted_date = date('Y-m-d', $record->timecreated);
    $email_short = substr($record->email, 0, 30) . '...';
    
    echo "<tr>";
    echo "<td>{$record->id}</td>";
    echo "<td>$email_short</td>";
    echo "<td>{$record->status}</td>";
    echo "<td>$formatted_time</td>";
    echo "<td>$formatted_date</td>";
    echo "<td>$within_timeframe</td>";
    echo "</tr>";
}
echo "</table>";

// 7. SUMMARY AND DIAGNOSIS
echo "<h2>🔍 Summary & Diagnosis</h2>";

echo "<table class='table table-bordered'>";
echo "<tr><th>Method</th><th>Total Records</th><th>Match?</th></tr>";
echo "<tr><td>Dashboard Stats</td><td>$dashboard_total</td><td>-</td></tr>";
echo "<tr><td>Report Count</td><td>$report_total</td><td>" . ($dashboard_total == $report_total ? '✅' : '❌') . "</td></tr>";
echo "<tr><td>Direct Query</td><td>$direct_total</td><td>" . ($dashboard_total == $direct_total ? '✅' : '❌') . "</td></tr>";
echo "<tr><td>All Records</td><td>$all_total</td><td>-</td></tr>";
echo "</table>";

if ($dashboard_total == $report_total && $dashboard_total == $direct_total) {
    echo "<div class='alert alert-success'>";
    echo "<h4>✅ All Methods Consistent</h4>";
    echo "<p>Dashboard and report are retrieving the same data. The issue might be in the display logic or timeframe selection.</p>";
    echo "</div>";
} else {
    echo "<div class='alert alert-danger'>";
    echo "<h4>❌ Data Inconsistency Found</h4>";
    echo "<p>Different methods are returning different results. This indicates a bug in one of the calculation methods.</p>";
    echo "</div>";
}

// Specific diagnosis
if ($dashboard_total < $all_total && $dashboard_total > 0) {
    echo "<div class='alert alert-info'>";
    echo "<h4>📊 Timeframe Filtering Active</h4>";
    echo "<p>Dashboard is showing $dashboard_total out of $all_total total records, filtering by $timeframe days.</p>";
    echo "<p>If you expect to see more recent data, check if:</p>";
    echo "<ul>";
    echo "<li>The emails were sent within the last $timeframe days</li>";
    echo "<li>The webhook is using correct timestamps</li>";
    echo "<li>Your system timezone is configured correctly</li>";
    echo "</ul>";
    echo "</div>";
}

if ($dashboard_total == 0 && $all_total > 0) {
    echo "<div class='alert alert-warning'>";
    echo "<h4>⚠️ No Recent Data</h4>";
    echo "<p>You have $all_total total records but none in the last $timeframe days.</p>";
    echo "<p>Check the 'Sample Recent Records' section above to see when your most recent data was recorded.</p>";
    echo "</div>";
}

// 8. TEST DIFFERENT TIMEFRAMES
echo "<h2>🕐 Test Different Timeframes</h2>";

echo "<div class='alert alert-info'>";
echo "<p>Test different timeframes to see how data changes:</p>";
echo "<ul>";
foreach ([3, 5, 7] as $tf) {
    $url = new moodle_url('/local/sesdashboard/dashboard_debug.php', ['timeframe' => $tf]);
    $current = ($tf == $timeframe) ? ' (current)' : '';
    echo "<li><a href='" . $url->out() . "'>$tf days$current</a></li>";
}
echo "</ul>";
echo "</div>";

echo $OUTPUT->footer();
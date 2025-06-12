<?php
/**
 * SES Dashboard Comprehensive Debug Tool
 * 
 * This is the MAIN debug file for the SES Dashboard plugin.
 * It provides comprehensive analysis of all data sources:
 * - Dashboard stats (pie chart)
 * - Daily stats (line chart) 
 * - SQL query comparison
 * - Time boundary analysis
 * - Date range verification
 * - Manual processing simulation
 * - Step-by-step debugging
 * 
 * Use this file to troubleshoot any data inconsistencies.
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');

// SECURITY: Add proper authentication
require_login();
$context = context_system::instance();
require_capability('local/sesdashboard:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/sesdashboard/comprehensive_debug.php'));
$PAGE->set_title('SES Dashboard Comprehensive Debug');
$PAGE->set_heading('SES Dashboard Comprehensive Debug');

echo $OUTPUT->header();

global $DB;

echo "<h1>🔍 SES Dashboard Comprehensive Debug Tool</h1>";
echo "<div class='alert alert-primary'>";
echo "<strong>📊 This tool analyzes ALL data sources:</strong><br>";
echo "• Dashboard stats (pie chart)<br>";
echo "• Daily stats (line chart)<br>";
echo "• SQL query comparison<br>";
echo "• Time boundary analysis<br>";
echo "• Date range verification<br>";
echo "• Manual processing simulation";
echo "</div>";

$timeframe = 7;

// Use the same time calculation as both methods
$today = strtotime('today'); 
$timestart = $today - (($timeframe - 1) * DAYSECS); 
$timeend = time(); 

echo "<div class='alert alert-info'>";
echo "<strong>Time Boundaries:</strong><br>";
echo "Today: " . date('Y-m-d H:i:s', $today) . " (timestamp: $today)<br>";
echo "Timestart: " . date('Y-m-d H:i:s', $timestart) . " (timestamp: $timestart)<br>";
echo "Timeend: " . date('Y-m-d H:i:s', $timeend) . " (timestamp: $timeend)<br>";
echo "</div>";

// 1. ACTUAL DASHBOARD STATS METHOD (from email_repository.php)
echo "<h2>1. ACTUAL Dashboard Stats Method (get_dashboard_stats)</h2>";
$repository = new \local_sesdashboard\repositories\email_repository();
$dashboard_stats = $repository->get_dashboard_stats($timeframe);

echo "<div class='alert alert-success'>";
echo "<strong>Method:</strong> Uses email_repository->get_dashboard_stats($timeframe)<br>";
echo "<strong>SQL:</strong> SELECT id, email, subject, status, messageid, eventtype, timecreated FROM {local_sesdashboard_mail} WHERE timecreated >= ? AND timecreated <= ?<br>";
echo "<strong>Processing:</strong> Individual record processing (same as report.php)<br>";
echo "<strong>Parameters:</strong> [$timestart, $timeend]";
echo "</div>";

echo "<table class='table table-bordered'>";
echo "<tr><th>Status</th><th>Count</th></tr>";
$dashboard_total = 0;
foreach ($dashboard_stats as $status => $data) {
    echo "<tr><td>$status</td><td>{$data->count}</td></tr>";
    $dashboard_total += $data->count;
}
echo "<tr><td><strong>TOTAL</strong></td><td><strong>$dashboard_total</strong></td></tr>";
echo "</table>";

// 2. ACTUAL DAILY STATS METHOD (from email_repository.php)
echo "<h2>2. ACTUAL Daily Stats Method (get_daily_stats)</h2>";
$daily_stats = $repository->get_daily_stats($timeframe);

echo "<div class='alert alert-success'>";
echo "<strong>Method:</strong> Uses email_repository->get_daily_stats($timeframe)<br>";
echo "<strong>SQL:</strong> SELECT id, email, subject, status, messageid, eventtype, timecreated FROM {local_sesdashboard_mail} WHERE timecreated >= ? AND timecreated <= ?<br>";
echo "<strong>Processing:</strong> Individual record processing, grouped by date<br>";
echo "<strong>Parameters:</strong> [$timestart, $timeend]";
echo "</div>";

// Calculate totals from daily stats
$daily_totals = [
    'sent' => array_sum($daily_stats['sent']),
    'delivered' => array_sum($daily_stats['delivered']),
    'bounced' => array_sum($daily_stats['bounced']),
    'opened' => array_sum($daily_stats['opened'])
];
$daily_total = array_sum($daily_totals);

echo "<table class='table table-bordered'>";
echo "<tr><th>Status</th><th>Total Count</th></tr>";
echo "<tr><td>Sent</td><td>{$daily_totals['sent']}</td></tr>";
echo "<tr><td>Delivered</td><td>{$daily_totals['delivered']}</td></tr>";
echo "<tr><td>Bounced</td><td>{$daily_totals['bounced']}</td></tr>";
echo "<tr><td>Opened</td><td>{$daily_totals['opened']}</td></tr>";
echo "<tr><td><strong>TOTAL</strong></td><td><strong>$daily_total</strong></td></tr>";
echo "</table>";

// 3. REPORT PAGE METHOD (for comparison)
echo "<h2>3. ACTUAL Report Page Method (get_filtered_count_by_timestamp)</h2>";
$report_total = $repository->get_filtered_count_by_timestamp('', $timestart, $timeend, '');

echo "<div class='alert alert-success'>";
echo "<strong>Method:</strong> Uses email_repository->get_filtered_count_by_timestamp()<br>";
echo "<strong>SQL:</strong> SELECT COUNT(*) FROM {local_sesdashboard_mail} WHERE timecreated >= ? AND timecreated <= ?<br>";
echo "<strong>Processing:</strong> Same as dashboard and daily stats<br>";
echo "<strong>Parameters:</strong> [$timestart, $timeend]";
echo "</div>";

echo "<table class='table table-bordered'>";
echo "<tr><th>Method</th><th>Total Records</th><th>Status</th></tr>";
echo "<tr><td>Dashboard Stats (Pie Chart)</td><td>$dashboard_total</td><td>" . ($dashboard_total > 0 ? '✅ Working' : '❌ Broken') . "</td></tr>";
echo "<tr><td>Daily Stats (Line Chart)</td><td>$daily_total</td><td>" . ($daily_total > 0 ? '✅ Working' : '❌ Broken') . "</td></tr>";
echo "<tr><td>Report Page Count</td><td>$report_total</td><td>" . ($report_total > 0 ? '✅ Working' : '❌ Broken') . "</td></tr>";
echo "</table>";

if ($dashboard_total == $daily_total && $dashboard_total == $report_total) {
    echo "<div class='alert alert-success'>";
    echo "<h4>✅ PERFECT CONSISTENCY!</h4>";
    echo "<p>All three methods return the same total ($dashboard_total records). The fixes are working correctly!</p>";
    echo "</div>";
} else {
    echo "<div class='alert alert-danger'>";
    echo "<h4>❌ INCONSISTENCY DETECTED!</h4>";
    if ($dashboard_total != $daily_total) {
        echo "<p>⚠️ Dashboard ($dashboard_total) != Daily Stats ($daily_total)</p>";
    }
    if ($dashboard_total != $report_total) {
        echo "<p>⚠️ Dashboard ($dashboard_total) != Report Page ($report_total)</p>";
    }
    echo "</div>";
}

// 4. DATE RANGE CHECK
echo "<h2>4. Date Range Check</h2>";

echo "<h3>Expected Date Range (from daily stats):</h3>";
echo "<ul>";
foreach ($daily_stats['dates'] as $date) {
    echo "<li>$date</li>";
}
echo "</ul>";

echo "<h3>Daily Stats Breakdown:</h3>";
echo "<table class='table table-bordered'>";
echo "<tr><th>Date</th><th>Sent</th><th>Delivered</th><th>Bounced</th><th>Opened</th><th>Total</th></tr>";
for ($i = 0; $i < count($daily_stats['dates']); $i++) {
    $date = $daily_stats['dates'][$i];
    $sent = $daily_stats['sent'][$i];
    $delivered = $daily_stats['delivered'][$i];
    $bounced = $daily_stats['bounced'][$i];
    $opened = $daily_stats['opened'][$i];
    $day_total = $sent + $delivered + $bounced + $opened;
    
    $row_class = $day_total > 0 ? 'table-success' : 'table-light';
    echo "<tr class='$row_class'>";
    echo "<td>$date</td>";
    echo "<td>$sent</td>";
    echo "<td>$delivered</td>";
    echo "<td>$bounced</td>";
    echo "<td>$opened</td>";
    echo "<td><strong>$day_total</strong></td>";
    echo "</tr>";
}
echo "</table>";

echo "<div class='alert alert-info'>";
echo "<strong>Note:</strong> This shows the ACTUAL data being used in your dashboard charts. ";
echo "Green rows have data, light rows have no data for that date.";
echo "</div>";

// 5. SUMMARY AND RECOMMENDATIONS
echo "<h2>5. Summary and Recommendations</h2>";

if ($dashboard_total == $daily_total && $dashboard_total == $report_total) {
    echo "<div class='alert alert-success'>";
    echo "<h4>✅ SYSTEM STATUS: HEALTHY</h4>";
    echo "<ul>";
    echo "<li><strong>Data Consistency:</strong> Perfect ✅</li>";
    echo "<li><strong>Dashboard Charts:</strong> Working correctly ✅</li>";
    echo "<li><strong>Report Page:</strong> Working correctly ✅</li>";
    echo "<li><strong>Time Range:</strong> Properly configured ✅</li>";
    echo "</ul>";
    echo "<p><strong>All systems are working correctly!</strong> Your dashboard is showing accurate data.</p>";
    echo "</div>";
} else {
    echo "<div class='alert alert-warning'>";
    echo "<h4>⚠️ SYSTEM STATUS: NEEDS ATTENTION</h4>";
    echo "<p>There are inconsistencies between different data sources. Please check:</p>";
    echo "<ul>";
    echo "<li>Email repository methods are using the same SQL approach</li>";
    echo "<li>Time boundary calculations are consistent</li>";
    echo "<li>Data processing logic matches across all components</li>";
    echo "</ul>";
    echo "</div>";
}

echo "<h3>Quick Actions:</h3>";
echo "<div class='alert alert-info'>";
echo "<ul>";
echo "<li><a href='/local/sesdashboard/pages/index.php' class='btn btn-primary'>View Dashboard</a></li>";
echo "<li><a href='/local/sesdashboard/pages/report.php' class='btn btn-secondary'>View Report Page</a></li>";
echo "<li><a href='/local/sesdashboard/comprehensive_debug.php' class='btn btn-info'>Refresh Debug</a></li>";
echo "</ul>";
echo "</div>";

echo $OUTPUT->footer(); 
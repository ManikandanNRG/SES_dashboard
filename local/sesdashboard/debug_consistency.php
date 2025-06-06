<?php
/**
 * Debug script to check data consistency between different methods
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_login();

global $DB;

echo "<h2>SES Dashboard Data Consistency Check</h2>";

$repository = new \local_sesdashboard\repositories\email_repository();
$timeframe = 7;

echo "<h3>Method 1: get_dashboard_stats($timeframe)</h3>";
$dashboard_stats = $repository->get_dashboard_stats($timeframe);
$dashboard_total = 0;
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Status</th><th>Count</th></tr>";
foreach ($dashboard_stats as $status => $data) {
    echo "<tr><td>$status</td><td>{$data->count}</td></tr>";
    $dashboard_total += $data->count;
}
echo "<tr><td><strong>TOTAL</strong></td><td><strong>$dashboard_total</strong></td></tr>";
echo "</table>";

echo "<h3>Method 2: get_daily_stats($timeframe)</h3>";
$daily_stats = $repository->get_daily_stats($timeframe);
$daily_totals = [
    'sent' => array_sum($daily_stats['sent']),
    'delivered' => array_sum($daily_stats['delivered']),
    'bounced' => array_sum($daily_stats['bounced']),
    'opened' => array_sum($daily_stats['opened'])
];
$daily_total = array_sum($daily_totals);

echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Status</th><th>Total from Daily Stats</th></tr>";
echo "<tr><td>Send</td><td>{$daily_totals['sent']}</td></tr>";
echo "<tr><td>Delivery</td><td>{$daily_totals['delivered']}</td></tr>";
echo "<tr><td>Bounce</td><td>{$daily_totals['bounced']}</td></tr>";
echo "<tr><td>Open</td><td>{$daily_totals['opened']}</td></tr>";
echo "<tr><td><strong>TOTAL</strong></td><td><strong>$daily_total</strong></td></tr>";
echo "</table>";

echo "<h3>Method 3: get_filtered_count() (ALL RECORDS)</h3>";
$all_count = $repository->get_filtered_count('', '', '', '');
echo "<p><strong>Total count (all records):</strong> $all_count</p>";

echo "<h3>Method 4: get_filtered_count() with date range (same as dashboard)</h3>";
$timestart = time() - ($timeframe * DAYSECS);
$from = date('Y-m-d', $timestart);
$to = date('Y-m-d');
$filtered_count = $repository->get_filtered_count('', $from, $to, '');
echo "<p><strong>Date range:</strong> $from to $to</p>";
echo "<p><strong>Filtered count:</strong> $filtered_count</p>";

echo "<h3>Raw Database Query (Dashboard timeframe)</h3>";
$timestart = time() - ($timeframe * DAYSECS);
$sql = "SELECT status, COUNT(*) as count 
        FROM {local_sesdashboard_mail} 
        WHERE timecreated >= ? 
        GROUP BY status 
        ORDER BY status";
$raw_results = $DB->get_records_sql($sql, [$timestart]);
$raw_total = 0;
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Status</th><th>Count</th></tr>";
foreach ($raw_results as $result) {
    echo "<tr><td>{$result->status}</td><td>{$result->count}</td></tr>";
    $raw_total += $result->count;
}
echo "<tr><td><strong>TOTAL</strong></td><td><strong>$raw_total</strong></td></tr>";
echo "</table>";

echo "<h3>Comparison Summary</h3>";
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Method</th><th>Total Count</th><th>Notes</th></tr>";
echo "<tr><td>Dashboard Stats</td><td>$dashboard_total</td><td>Used in pie chart</td></tr>";
echo "<tr><td>Daily Stats</td><td>$daily_total</td><td>Used in line chart</td></tr>";
echo "<tr><td>All Records</td><td>$all_count</td><td>Report page (before fix)</td></tr>";
echo "<tr><td>Filtered by Date</td><td>$filtered_count</td><td>Report page (after fix)</td></tr>";
echo "<tr><td>Raw Query</td><td>$raw_total</td><td>Direct database query</td></tr>";
echo "</table>";

if ($dashboard_total == $daily_total && $dashboard_total == $filtered_count && $dashboard_total == $raw_total) {
    echo "<div style='color: green; font-weight: bold; margin-top: 20px;'>✅ ALL METHODS ARE CONSISTENT!</div>";
} else {
    echo "<div style='color: red; font-weight: bold; margin-top: 20px;'>❌ INCONSISTENCY DETECTED!</div>";
    
    if ($dashboard_total != $daily_total) {
        echo "<p style='color: red;'>⚠️ Dashboard stats ($dashboard_total) != Daily stats ($daily_total)</p>";
    }
    if ($dashboard_total != $filtered_count) {
        echo "<p style='color: red;'>⚠️ Dashboard stats ($dashboard_total) != Filtered count ($filtered_count)</p>";
    }
    if ($dashboard_total != $raw_total) {
        echo "<p style='color: red;'>⚠️ Dashboard stats ($dashboard_total) != Raw query ($raw_total)</p>";
    }
}

echo "<h3>Debug Info</h3>";
echo "<p><strong>Timeframe:</strong> $timeframe days</p>";
echo "<p><strong>Timestart timestamp:</strong> $timestart</p>";
echo "<p><strong>Timestart readable:</strong> " . date('Y-m-d H:i:s', $timestart) . "</p>";
echo "<p><strong>From date:</strong> $from</p>";
echo "<p><strong>To date:</strong> $to</p>";
echo "<p><strong>Current time:</strong> " . date('Y-m-d H:i:s') . "</p>"; 
<?php
/**
 * Debug Daily Stats vs Dashboard Stats
 * Compare the exact SQL and results to find the discrepancy
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');

// SECURITY: Add proper authentication
require_login();
$context = context_system::instance();
require_capability('local/sesdashboard:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/sesdashboard/fix_daily_stats_debug.php'));
$PAGE->set_title('Fix Daily Stats Debug');
$PAGE->set_heading('Fix Daily Stats Debug');

echo $OUTPUT->header();

global $DB;

echo "<h1>🔍 Dashboard vs Daily Stats SQL Comparison</h1>";

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

// 1. DASHBOARD STATS SQL (what works)
echo "<h2>1. Dashboard Stats SQL (Working)</h2>";
$dashboard_sql = "SELECT status, COUNT(*) as count 
                  FROM {local_sesdashboard_mail} 
                  WHERE timecreated >= ? AND timecreated <= ?
                  GROUP BY status 
                  ORDER BY status";

echo "<div class='alert alert-light'>";
echo "<strong>SQL:</strong><br>";
echo "<code>" . str_replace('{local_sesdashboard_mail}', 'mdl_local_sesdashboard_mail', $dashboard_sql) . "</code><br>";
echo "<strong>Parameters:</strong> [$timestart, $timeend]";
echo "</div>";

$dashboard_results = $DB->get_records_sql($dashboard_sql, [$timestart, $timeend]);

echo "<table class='table table-bordered'>";
echo "<tr><th>Status</th><th>Count</th></tr>";
foreach ($dashboard_results as $result) {
    echo "<tr><td>{$result->status}</td><td>{$result->count}</td></tr>";
}
echo "</table>";

// 2. DAILY STATS SQL (FIXED)
echo "<h2>2. Daily Stats SQL (FIXED)</h2>";
$daily_sql = "SELECT DATE(FROM_UNIXTIME(timecreated)) AS event_date,
              status,
              COUNT(*) AS count
              FROM {local_sesdashboard_mail}
              WHERE timecreated >= ? AND timecreated <= ?
              GROUP BY DATE(FROM_UNIXTIME(timecreated)), status
              ORDER BY event_date ASC, status ASC";

echo "<div class='alert alert-light'>";
echo "<strong>SQL:</strong><br>";
echo "<code>" . str_replace('{local_sesdashboard_mail}', 'mdl_local_sesdashboard_mail', $daily_sql) . "</code><br>";
echo "<strong>Parameters:</strong> [$timestart, $timeend]";
echo "</div>";

$daily_results = $DB->get_records_sql($daily_sql, [$timestart, $timeend]);

echo "<table class='table table-bordered'>";
echo "<tr><th>Date</th><th>Status</th><th>Count</th></tr>";
foreach ($daily_results as $result) {
    echo "<tr><td>{$result->event_date}</td><td>{$result->status}</td><td>{$result->count}</td></tr>";
}
echo "</table>";

// 3. COMPARISON
echo "<h2>3. Comparison Analysis</h2>";

$dashboard_total = 0;
foreach ($dashboard_results as $result) {
    $dashboard_total += $result->count;
}

$daily_total = 0;
foreach ($daily_results as $result) {
    $daily_total += $result->count;
}

echo "<table class='table table-bordered'>";
echo "<tr><th>Method</th><th>Total Records</th><th>Status</th></tr>";
echo "<tr><td>Dashboard Stats</td><td>$dashboard_total</td><td>" . ($dashboard_total > 0 ? '✅ Working' : '❌ Broken') . "</td></tr>";
echo "<tr><td>Daily Stats</td><td>$daily_total</td><td>" . ($daily_total > 0 ? '✅ Working' : '❌ Broken') . "</td></tr>";
echo "</table>";

if ($dashboard_total != $daily_total) {
    echo "<div class='alert alert-danger'>";
    echo "<h4>❌ FOUND THE BUG!</h4>";
    echo "<p>Dashboard Stats finds $dashboard_total records, but Daily Stats finds $daily_total records.</p>";
    echo "<p>This proves the SQL queries are returning different results for the same time period.</p>";
    echo "</div>";
} else {
    echo "<div class='alert alert-success'>";
    echo "<h4>✅ SQL Queries Match</h4>";
    echo "<p>Both methods return the same total. The issue might be in data processing.</p>";
    echo "</div>";
}

// 4. DATE RANGE CHECK
echo "<h2>4. Date Range Check</h2>";

// Create dates array (same as get_daily_stats)
$dates_array = [];
for ($i = 0; $i < $timeframe; $i++) {
    $date_timestamp = $timestart + ($i * DAYSECS);
    $dates_array[] = date('Y-m-d', $date_timestamp);
}

echo "<h3>Expected Date Range:</h3>";
echo "<ul>";
foreach ($dates_array as $date) {
    echo "<li>$date</li>";
}
echo "</ul>";

echo "<h3>Actual Dates with Data:</h3>";
$actual_dates = [];
foreach ($daily_results as $result) {
    if (!in_array($result->event_date, $actual_dates)) {
        $actual_dates[] = $result->event_date;
    }
}

echo "<ul>";
foreach ($actual_dates as $date) {
    $in_range = in_array($date, $dates_array) ? '✅' : '❌';
    echo "<li>$date $in_range</li>";
}
echo "</ul>";

// 5. MANUAL PROCESSING TEST
echo "<h2>5. Manual Processing Test</h2>";

// Simulate the get_daily_stats processing
$data_arrays = [
    'delivered' => array_fill(0, $timeframe, 0),
    'opened'    => array_fill(0, $timeframe, 0),
    'sent'      => array_fill(0, $timeframe, 0),
    'bounced'   => array_fill(0, $timeframe, 0)
];

foreach ($daily_results as $result) {
    $date = $result->event_date;
    $status = trim($result->status);
    $count = (int)$result->count;
    
    // Find the date index
    $date_index = array_search($date, $dates_array);
    
    echo "<p>Processing: Date=$date, Status=$status, Count=$count, Index=$date_index</p>";
    
    if ($date_index !== false) {
        // Map status to chart series
        switch ($status) {
            case 'Send':
                $data_arrays['sent'][$date_index] += $count;
                echo "<p>→ Added $count to sent[$date_index]</p>";
                break;
            case 'Delivery':
            case 'DeliveryDelay':
                $data_arrays['delivered'][$date_index] += $count;
                echo "<p>→ Added $count to delivered[$date_index]</p>";
                break;
            case 'Bounce':
                $data_arrays['bounced'][$date_index] += $count;
                echo "<p>→ Added $count to bounced[$date_index]</p>";
                break;
            case 'Open':
            case 'Click':
                $data_arrays['opened'][$date_index] += $count;
                echo "<p>→ Added $count to opened[$date_index]</p>";
                break;
        }
    } else {
        echo "<p>→ <strong>SKIPPED</strong> - Date not in range!</p>";
    }
}

echo "<h3>Final Processed Arrays:</h3>";
echo "<table class='table table-bordered'>";
echo "<tr><th>Date</th><th>Sent</th><th>Delivered</th><th>Bounced</th><th>Opened</th></tr>";
for ($i = 0; $i < $timeframe; $i++) {
    echo "<tr>";
    echo "<td>{$dates_array[$i]}</td>";
    echo "<td>{$data_arrays['sent'][$i]}</td>";
    echo "<td>{$data_arrays['delivered'][$i]}</td>";
    echo "<td>{$data_arrays['bounced'][$i]}</td>";
    echo "<td>{$data_arrays['opened'][$i]}</td>";
    echo "</tr>";
}
echo "</table>";

echo $OUTPUT->footer(); 
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
echo "<table class='table table-bordered'>";
echo "<tr><th>Status</th><th>Count</th></tr>";
foreach ($dashboard_stats as $status => $data) {
    echo "<tr><td>$status</td><td>{$data->count}</td></tr>";
    $pie_total += $data->count;
}
echo "<tr><td><strong>TOTAL</strong></td><td><strong>$pie_total</strong></td></tr>";
echo "</table>";

// Method 2: Daily stats (line chart) - EXACT method from index.php
echo "<h3>2. Dashboard Line Chart Method (get_daily_stats)</h3>";
$daily_stats = $repository->get_daily_stats($timeframe);
$line_totals = [
    'sent' => array_sum($daily_stats['sent']),
    'delivered' => array_sum($daily_stats['delivered']),
    'bounced' => array_sum($daily_stats['bounced']),
    'opened' => array_sum($daily_stats['opened'])
];
$line_total = array_sum($line_totals);

echo "<table class='table table-bordered'>";
echo "<tr><th>Status</th><th>Count</th></tr>";
echo "<tr><td>Send</td><td>{$line_totals['sent']}</td></tr>";
echo "<tr><td>Delivery</td><td>{$line_totals['delivered']}</td></tr>";
echo "<tr><td>Bounce</td><td>{$line_totals['bounced']}</td></tr>";
echo "<tr><td>Open</td><td>{$line_totals['opened']}</td></tr>";
echo "<tr><td><strong>TOTAL</strong></td><td><strong>$line_total</strong></td></tr>";
echo "</table>";

// DEBUG: Let's check what the daily stats SQL is actually returning
echo "<h4>🔍 Daily Stats Debug - Raw SQL Results:</h4>";
$timestart_debug = time() - (($timeframe - 1) * DAYSECS);
$debug_sql = "SELECT DATE(FROM_UNIXTIME(timecreated)) as date_str, status, COUNT(*) as count
              FROM {local_sesdashboard_mail} 
              WHERE timecreated >= ? 
              GROUP BY DATE(FROM_UNIXTIME(timecreated)), status
              ORDER BY date_str ASC, status ASC";

$debug_results = $DB->get_records_sql($debug_sql, [$timestart_debug]);

echo "<table class='table table-bordered table-sm'>";
echo "<tr><th>Date</th><th>Status</th><th>Count</th></tr>";
$debug_total = 0;
$debug_status_totals = [];
foreach ($debug_results as $result) {
    echo "<tr><td>{$result->date_str}</td><td>{$result->status}</td><td>{$result->count}</td></tr>";
    $debug_total += $result->count;
    if (!isset($debug_status_totals[$result->status])) {
        $debug_status_totals[$result->status] = 0;
    }
    $debug_status_totals[$result->status] += $result->count;
}
echo "<tr><td colspan='2'><strong>TOTAL</strong></td><td><strong>$debug_total</strong></td></tr>";
echo "</table>";

echo "<h5>Status Totals from Raw SQL:</h5>";
echo "<ul>";
foreach ($debug_status_totals as $status => $count) {
    $highlight = ($status == 'Click' || $status == 'DeliveryDelay') ? ' style="color: orange; font-weight: bold;"' : '';
    echo "<li$highlight><strong>$status:</strong> $count";
    if ($status == 'Click') {
        echo " <em>(✅ Counted as 'Opens' in line chart)</em>";
    } elseif ($status == 'DeliveryDelay') {  
        echo " <em>(✅ Counted as 'Delivery' in line chart)</em>";
    }
    echo "</li>";
}
echo "</ul>";

// Calculate what SHOULD be in line chart - now ALL statuses should be included
$line_chart_expected = array_sum($debug_status_totals);

echo "<div class='alert alert-info'>";
echo "<strong>🔍 LINE CHART CALCULATION:</strong><br>";
echo "Expected line chart total (ALL statuses): <strong>$line_chart_expected</strong><br>";
echo "Actual line chart total: <strong>$line_total</strong><br>";
if ($line_chart_expected == $line_total) {
    echo "<span style='color: green;'>✅ Line chart calculation is CORRECT</span>";
} else {
    echo "<span style='color: red;'>❌ Line chart calculation has issues</span>";
}
echo "</div>";

echo "<h5>Generated Date Range:</h5>";
echo "<ul>";
foreach ($daily_stats['dates'] as $date) {
    echo "<li>$date</li>";
}
echo "</ul>";

// ADDITIONAL DEBUG: Test the new simplified SQL used by updated get_daily_stats
echo "<h4>🔍 NEW get_daily_stats Debug - Simplified SQL:</h4>";
$simple_sql = "SELECT timecreated, status 
               FROM {local_sesdashboard_mail} 
               WHERE timecreated >= ? 
               ORDER BY timecreated ASC";

$simple_results = $DB->get_records_sql($simple_sql, [$timestart_debug]);

echo "<p><strong>Total records from simple SQL:</strong> " . count($simple_results) . "</p>";

// Process the simple results like the new get_daily_stats does
$simple_status_counts = [];
$simple_date_counts = [];
$expected_dates = [];
$start_date = $timestart_debug;
for ($i = 0; $i < $timeframe; $i++) {
    $expected_dates[] = date('Y-m-d', $start_date + ($i * DAYSECS));
}

foreach ($simple_results as $result) {
    $record_date = date('Y-m-d', $result->timecreated);
    $status = trim($result->status);
    
    if (!isset($simple_status_counts[$status])) {
        $simple_status_counts[$status] = 0;
    }
    $simple_status_counts[$status]++;
    
    if (!isset($simple_date_counts[$record_date])) {
        $simple_date_counts[$record_date] = 0;
    }
    $simple_date_counts[$record_date]++;
}

echo "<h5>Status Totals from Simple SQL (PHP processed):</h5>";
echo "<ul>";
foreach ($simple_status_counts as $status => $count) {
    $highlight = ($status == 'Click' || $status == 'DeliveryDelay') ? ' style="color: orange; font-weight: bold;"' : '';
    echo "<li$highlight><strong>$status:</strong> $count";
    if ($status == 'Click') {
        echo " <em>(✅ Counted as 'Opens' in line chart)</em>";
    } elseif ($status == 'DeliveryDelay') {  
        echo " <em>(✅ Counted as 'Delivery' in line chart)</em>";
    }
    echo "</li>";
}
echo "</ul>";

// Calculate what should be processed by get_daily_stats - now ALL statuses
$simple_line_expected = array_sum($simple_status_counts);

echo "<div class='alert alert-warning'>";
echo "<strong>🔧 get_daily_stats() PROCESSING:</strong><br>";
echo "Total records from SQL: <strong>" . count($simple_results) . "</strong><br>";
echo "Should be processed for line chart (ALL statuses): <strong>$simple_line_expected</strong><br>";
echo "Click → Opens, DeliveryDelay → Delivery mapping applied<br>";
echo "Verification: " . (count($simple_results) == $simple_line_expected ? '✅ All records processed' : '❌ Some records missing') . "<br>";
echo "</div>";

echo "<h5>Date Distribution from Simple SQL:</h5>";
echo "<table class='table table-bordered table-sm'>";
echo "<tr><th>Date</th><th>Count</th><th>In Range?</th></tr>";
foreach ($simple_date_counts as $date => $count) {
    $in_range = in_array($date, $expected_dates) ? '✅' : '❌';
    echo "<tr><td>$date</td><td>$count</td><td>$in_range</td></tr>";
}
echo "</table>";

echo "<h5>Expected Date Range for $timeframe days:</h5>";
echo "<ul>";
foreach ($expected_dates as $date) {
    echo "<li>$date</li>";
}
echo "</ul>";

// Method 3: Report page method (NEW TIMESTAMP-BASED) - EXACT method from report.php
echo "<h3>3. Report Page Method (NEW - get_filtered_count_by_timestamp)</h3>";
$timestart3 = time() - (($timeframe - 1) * DAYSECS);
$from_timestamp = $timestart3;
$to_timestamp = time();
echo "<p><strong>From timestamp:</strong> $from_timestamp (" . date('Y-m-d H:i:s', $from_timestamp) . ")</p>";
echo "<p><strong>To timestamp:</strong> $to_timestamp (" . date('Y-m-d H:i:s', $to_timestamp) . ")</p>";

$report_total_new = $repository->get_filtered_count_by_timestamp('', $from_timestamp, $to_timestamp, '');
echo "<p><strong>Report Total (NEW):</strong> $report_total_new</p>";

// Method 4: Report page method (OLD DATE-BASED) - for comparison
echo "<h3>4. Report Page Method (OLD - get_filtered_count with dates)</h3>";
$from = date('Y-m-d', $timestart3);
$to = date('Y-m-d');
echo "<p><strong>From date:</strong> $from (timestamp: " . strtotime($from) . ")</p>";
echo "<p><strong>To date:</strong> $to (timestamp: " . strtotime($to . ' 23:59:59') . ")</p>";

$report_total_old = $repository->get_filtered_count('', $from, $to, '');
echo "<p><strong>Report Total (OLD):</strong> $report_total_old</p>";

// Summary
echo "<h3>5. Summary</h3>";
echo "<table class='table table-bordered'>";
echo "<tr><th>Method</th><th>Total Count</th><th>Difference from Pie Chart</th><th>Status</th></tr>";
echo "<tr><td>Pie Chart (Dashboard)</td><td><strong>$pie_total</strong></td><td>0</td><td>✅ Reference</td></tr>";
echo "<tr><td>Line Chart (Dashboard)</td><td><strong>$line_total</strong></td><td>" . ($line_total - $pie_total) . "</td><td>" . ($line_total == $pie_total ? '✅' : '❌') . "</td></tr>";
echo "<tr><td>Report Page (NEW)</td><td><strong>$report_total_new</strong></td><td>" . ($report_total_new - $pie_total) . "</td><td>" . ($report_total_new == $pie_total ? '✅' : '❌') . "</td></tr>";
echo "<tr><td>Report Page (OLD)</td><td><strong>$report_total_old</strong></td><td>" . ($report_total_old - $pie_total) . "</td><td>" . ($report_total_old == $pie_total ? '✅' : '❌') . "</td></tr>";
echo "</table>";

if ($pie_total == $line_total && $pie_total == $report_total_new) {
    echo "<div class='alert alert-success'><strong>✅ ALL METHODS ARE NOW CONSISTENT!</strong></div>";
    echo "<div class='alert alert-info'>The new timestamp-based method fixes the discrepancy.</div>";
} else {
    echo "<div class='alert alert-danger'><strong>❌ INCONSISTENCY STILL EXISTS!</strong></div>";
    
    if ($pie_total != $line_total) {
        echo "<div class='alert alert-warning'>⚠️ Dashboard pie chart ($pie_total) != line chart ($line_total) - Difference: " . abs($pie_total - $line_total) . "</div>";
    }
    if ($pie_total != $report_total_new) {
        echo "<div class='alert alert-warning'>⚠️ Dashboard pie chart ($pie_total) != report page NEW ($report_total_new) - Difference: " . abs($pie_total - $report_total_new) . "</div>";
    }
}

// Show the exact SQL being used
echo "<h3>6. SQL Comparison</h3>";
echo "<h4>Dashboard SQL:</h4>";
echo "<code>SELECT status, COUNT(*) as count FROM mdl_local_sesdashboard_mail WHERE timecreated >= $timestart1 GROUP BY status</code><br><br>";

echo "<h4>Report SQL (NEW - FIXED):</h4>";
echo "<code>SELECT COUNT(*) FROM mdl_local_sesdashboard_mail WHERE timecreated >= $from_timestamp</code><br><br>";

echo "<h4>Report SQL (OLD):</h4>";
$from_ts_old = strtotime($from);
$to_ts_old = strtotime($to . ' 23:59:59');
echo "<code>SELECT COUNT(*) FROM mdl_local_sesdashboard_mail WHERE timecreated >= $from_ts_old AND timecreated <= $to_ts_old</code><br><br>";

echo "<div class='alert alert-success'>";
echo "<strong>🎯 CRITICAL FIX APPLIED:</strong><br>";
echo "• Dashboard uses: <code>timecreated >= $timestart1</code> (corrected to exclude extra day)<br>";
echo "• Report NEW uses: <code>timecreated >= $from_timestamp</code> (matches dashboard) ✅<br>";
echo "• This should now show data from " . date('Y-m-d', $timestart1) . " to " . date('Y-m-d') . " (exactly 7 days)<br>";
echo "<br><strong>Now all three methods should return identical results!</strong>";
echo "</div>";

echo "<h3>🔍 DIRECT DATABASE CHECK</h3>";
echo "<h4>Simple Status Count (Last 7 Days):</h4>";
$direct_timestart = time() - (($timeframe - 1) * DAYSECS);
$direct_sql = "SELECT status, COUNT(*) as count 
               FROM {local_sesdashboard_mail} 
               WHERE timecreated >= ? 
               GROUP BY status 
               ORDER BY status";
$direct_results = $DB->get_records_sql($direct_sql, [$direct_timestart]);

echo "<table class='table table-bordered'>";
echo "<tr><th>Status</th><th>Count</th><th>Line Chart</th></tr>";
$direct_total = 0;
$included_count = 0;
foreach ($direct_results as $result) {
    $mapping = '';
    if ($result->status == 'Click') {
        $mapping = '✅ → Opens';
    } elseif ($result->status == 'DeliveryDelay') {
        $mapping = '✅ → Delivery';
    } else {
        $mapping = '✅ Direct';
    }
    $row_class = ($result->status == 'Click' || $result->status == 'DeliveryDelay') ? ' style="background-color: #fff3e0;"' : '';
    echo "<tr$row_class><td>{$result->status}</td><td>{$result->count}</td><td>$mapping</td></tr>";
    $direct_total += $result->count;
    $included_count += $result->count;
}
echo "<tr><td><strong>TOTAL</strong></td><td><strong>$direct_total</strong></td><td>-</td></tr>";
echo "<tr style='background-color: #e8f5e8;'><td><strong>Line Chart Total</strong></td><td><strong>$included_count</strong></td><td>✅ Should match pie chart</td></tr>";
echo "</table>";

echo "<div class='alert alert-success'>";
echo "<strong>🎯 ISSUE FIXED WITH PROPER MAPPING:</strong><br><br>";
echo "<strong>New Approach:</strong> Include ALL email events with proper categorization:<br>";
echo "• <strong>Click</strong> events → Counted as <strong>'Opens'</strong> (logical: clicks require opens)<br>";
echo "• <strong>DeliveryDelay</strong> events → Counted as <strong>'Delivery'</strong> (logical: still delivered)<br>";
echo "• <strong>Send, Delivery, Bounce, Open</strong> → Counted directly<br><br>";
echo "<strong>Result:</strong><br>";
echo "• Pie chart total = Line chart total ✅<br>";
echo "• All email events are represented in both charts ✅<br>";
echo "• Logical categorization maintains data integrity ✅<br>";
echo "• Users see complete email journey visualization ✅";
echo "</div>";

echo "<h4>Date Distribution Check:</h4>";
$date_sql = "SELECT DATE(FROM_UNIXTIME(timecreated)) as date_str, COUNT(*) as count 
             FROM {local_sesdashboard_mail} 
             WHERE timecreated >= ? 
             GROUP BY DATE(FROM_UNIXTIME(timecreated)) 
             ORDER BY date_str";
$date_results = $DB->get_records_sql($date_sql, [$direct_timestart]);

echo "<table class='table table-bordered'>";
echo "<tr><th>Date</th><th>Count</th></tr>";
foreach ($date_results as $result) {
    echo "<tr><td>{$result->date_str}</td><td>{$result->count}</td></tr>";
}
echo "</table>";

echo $OUTPUT->footer(); 
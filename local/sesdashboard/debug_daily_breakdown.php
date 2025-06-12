<?php
/**
 * Debug Daily Breakdown Table Issue
 * Check what data is being generated for the daily breakdown
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');

// SECURITY: Add proper authentication
require_login();
$context = context_system::instance();
require_capability('local/sesdashboard:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/sesdashboard/debug_daily_breakdown.php'));
$PAGE->set_title('Daily Breakdown Debug');
$PAGE->set_heading('Daily Breakdown Debug');

echo $OUTPUT->header();

global $DB;

echo "<h1>🔍 Daily Breakdown Data Debug</h1>";
echo "<div class='alert alert-info'><strong>Current Time:</strong> " . date('Y-m-d H:i:s') . " (Timestamp: " . time() . ")</div>";

$repository = new \local_sesdashboard\repositories\email_repository();
$timeframe = 7;

// Get the daily stats (same as dashboard)
$daily_stats = $repository->get_daily_stats($timeframe);

echo "<h2>Daily Stats Data from get_daily_stats($timeframe):</h2>";

echo "<h3>Raw Data:</h3>";
echo "<pre>" . print_r($daily_stats, true) . "</pre>";

echo "<h3>Daily Breakdown Table Data:</h3>";
echo "<table class='table table-bordered'>";
echo "<tr><th>Date</th><th>Sent</th><th>Delivered</th><th>Bounced</th><th>Opened</th></tr>";

if (!empty($daily_stats['dates'])) {
    for ($i = 0; $i < count($daily_stats['dates']); $i++) {
        $date = $daily_stats['dates'][$i];
        $sent = isset($daily_stats['sent'][$i]) ? $daily_stats['sent'][$i] : 0;
        $delivered = isset($daily_stats['delivered'][$i]) ? $daily_stats['delivered'][$i] : 0;
        $bounced = isset($daily_stats['bounced'][$i]) ? $daily_stats['bounced'][$i] : 0;
        $opened = isset($daily_stats['opened'][$i]) ? $daily_stats['opened'][$i] : 0;
        
        echo "<tr>";
        echo "<td>$date</td>";
        echo "<td>$sent</td>";
        echo "<td>$delivered</td>";
        echo "<td>$bounced</td>";
        echo "<td>$opened</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='5'>No data available</td></tr>";
}

echo "</table>";

// Check what's in the database for the same timeframe
$today = strtotime('today');
$timestart = $today - (($timeframe - 1) * DAYSECS);

echo "<h2>Direct Database Query for Same Timeframe:</h2>";
echo "<p><strong>Timestart:</strong> " . date('Y-m-d H:i:s', $timestart) . "</p>";
echo "<p><strong>Today:</strong> " . date('Y-m-d H:i:s', $today) . "</p>";

$direct_query = "
    SELECT DATE(FROM_UNIXTIME(timecreated)) AS event_date,
           status,
           COUNT(*) AS count
    FROM {local_sesdashboard_mail}
    WHERE timecreated >= ? AND timecreated <= ?
    GROUP BY DATE(FROM_UNIXTIME(timecreated)), status
    ORDER BY event_date DESC, status
";

echo "<h3>Raw Database Results:</h3>";
$results = $DB->get_records_sql($direct_query, [$timestart, time()]);

echo "<table class='table table-bordered'>";
echo "<tr><th>Date</th><th>Status</th><th>Count</th></tr>";
foreach ($results as $result) {
    echo "<tr>";
    echo "<td>{$result->event_date}</td>";
    echo "<td>{$result->status}</td>";
    echo "<td>{$result->count}</td>";
    echo "</tr>";
}
echo "</table>";

// Check the get_daily_stats method step by step
echo "<h2>Step-by-Step Analysis:</h2>";

// Step 1: Check time calculation
echo "<h3>1. Time Calculation:</h3>";
echo "<p>Today (midnight): " . date('Y-m-d H:i:s', $today) . " (timestamp: $today)</p>";
echo "<p>Timestart: " . date('Y-m-d H:i:s', $timestart) . " (timestamp: $timestart)</p>";
echo "<p>Current time: " . date('Y-m-d H:i:s', time()) . " (timestamp: " . time() . ")</p>";

// Step 2: Check dates array
echo "<h3>2. Dates Array:</h3>";
$dates_array = [];
for ($i = 0; $i < $timeframe; $i++) {
    $date_timestamp = $timestart + ($i * DAYSECS);
    $dates_array[] = date('Y-m-d', $date_timestamp);
}
echo "<pre>" . print_r($dates_array, true) . "</pre>";

// Step 3: Check data mapping
echo "<h3>3. Status Mapping Check:</h3>";
echo "<p>The get_daily_stats method should map:</p>";
echo "<ul>";
echo "<li>'Send' → sent array</li>";
echo "<li>'Delivery' + 'DeliveryDelay' → delivered array</li>";
echo "<li>'Bounce' → bounced array</li>";
echo "<li>'Open' + 'Click' → opened array</li>";
echo "</ul>";

// Step 4: Check if there's any data at all
$total_records = $DB->count_records_select('local_sesdashboard_mail', 'timecreated >= ? AND timecreated <= ?', [$timestart, time()]);
echo "<h3>4. Total Records in Timeframe:</h3>";
echo "<p><strong>$total_records</strong> records found in the timeframe.</p>";

if ($total_records == 0) {
    echo "<div class='alert alert-warning'>";
    echo "<h4>⚠️ NO DATA IN TIMEFRAME!</h4>";
    echo "<p>This explains why the daily breakdown shows zeros. The timeframe calculation might be wrong, or there's genuinely no data in the last $timeframe days.</p>";
    echo "</div>";
}

echo $OUTPUT->footer(); 
<?php
/**
 * Debug script to check raw data
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_login();

global $DB;

echo "<h2>SES Dashboard Data Debug</h2>";

// Get recent records
$sql = "SELECT id, email, status, eventtype, timecreated, 
               FROM_UNIXTIME(timecreated) as readable_date,
               DATE_FORMAT(FROM_UNIXTIME(timecreated), '%Y-%m-%d') as date_only
        FROM {local_sesdashboard_mail} 
        ORDER BY timecreated DESC 
        LIMIT 20";

$records = $DB->get_records_sql($sql);

echo "<h3>Recent 20 Records:</h3>";
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>ID</th><th>Email</th><th>Status</th><th>Event Type</th><th>Timestamp</th><th>Readable Date</th><th>Date Only</th></tr>";

foreach ($records as $record) {
    echo "<tr>";
    echo "<td>{$record->id}</td>";
    echo "<td>{$record->email}</td>";
    echo "<td>{$record->status}</td>";
    echo "<td>{$record->eventtype}</td>";
    echo "<td>{$record->timecreated}</td>";
    echo "<td>{$record->readable_date}</td>";
    echo "<td>{$record->date_only}</td>";
    echo "</tr>";
}
echo "</table>";

// Get status counts
echo "<h3>Status Distribution:</h3>";
$status_sql = "SELECT status, COUNT(*) as count FROM {local_sesdashboard_mail} GROUP BY status ORDER BY count DESC";
$status_data = $DB->get_records_sql($status_sql);

echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Status</th><th>Count</th></tr>";
foreach ($status_data as $status) {
    echo "<tr><td>{$status->status}</td><td>{$status->count}</td></tr>";
}
echo "</table>";

// FIXED: Use the exact same timeframe calculation as dashboard
echo "<h3>Daily Breakdown (Last 7 Days) - USING DASHBOARD LOGIC:</h3>";

// Use EXACT same calculation as get_daily_stats method
$days = 7;
$timestart = strtotime(date('Y-m-d 00:00:00')) - ($days - 1) * 86400;

echo "<p><strong>Timestart used:</strong> {$timestart} (" . date('Y-m-d H:i:s', $timestart) . ")</p>";

// Generate expected date range
echo "<h4>Expected Date Range:</h4>";
echo "<ul>";
for ($i = 0; $i < $days; $i++) {
    $date = date('Y-m-d', $timestart + ($i * 86400));
    echo "<li>$date</li>";
}
echo "</ul>";

// Test what records exist in this timeframe
echo "<h4>Records in Timeframe:</h4>";
$timeframe_records = $DB->get_records_sql(
    "SELECT timecreated, status, FROM_UNIXTIME(timecreated) as readable_date 
     FROM {local_sesdashboard_mail} 
     WHERE timecreated >= ? 
     ORDER BY timecreated DESC", 
    [$timestart]
);

echo "<p>Found " . count($timeframe_records) . " records in timeframe</p>";
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Timestamp</th><th>Readable Date</th><th>Status</th></tr>";
$count = 0;
foreach ($timeframe_records as $record) {
    if ($count++ < 50) { // Show first 50 records
        echo "<tr><td>{$record->timecreated}</td><td>{$record->readable_date}</td><td>{$record->status}</td></tr>";
    }
}
echo "</table>";

if (count($timeframe_records) > 50) {
    echo "<p>... and " . (count($timeframe_records) - 50) . " more records</p>";
}

// Now test the actual repository method
echo "<h3>Repository get_daily_stats() Output:</h3>";
require_once($CFG->dirroot . '/local/sesdashboard/classes/repositories/email_repository.php');
$repository = new \local_sesdashboard\repositories\email_repository();
$daily_stats = $repository->get_daily_stats(7);

echo "<h4>Dates Array:</h4>";
echo "<pre>" . print_r($daily_stats['dates'], true) . "</pre>";

echo "<h4>Data Arrays:</h4>";
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Date</th><th>Sent</th><th>Delivered</th><th>Bounced</th><th>Opened</th></tr>";
for ($i = 0; $i < count($daily_stats['dates']); $i++) {
    echo "<tr>";
    echo "<td>{$daily_stats['dates'][$i]}</td>";
    echo "<td>{$daily_stats['sent'][$i]}</td>";
    echo "<td>{$daily_stats['delivered'][$i]}</td>";
    echo "<td>{$daily_stats['bounced'][$i]}</td>";
    echo "<td>{$daily_stats['opened'][$i]}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h4>Raw Data Arrays:</h4>";
echo "<pre>";
echo "Sent: " . print_r($daily_stats['sent'], true);
echo "Delivered: " . print_r($daily_stats['delivered'], true);
echo "Bounced: " . print_r($daily_stats['bounced'], true);
echo "Opened: " . print_r($daily_stats['opened'], true);
echo "</pre>"; 
<?php
// Simple debug script to check data mismatch
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');

global $DB;

echo "=== SES Dashboard Data Consistency Check ===\n";

$timeframe = 7;
$timestart = time() - ($timeframe * DAYSECS);

echo "Timeframe: $timeframe days\n";
echo "Timestart: $timestart (" . date('Y-m-d H:i:s', $timestart) . ")\n";
echo "Current time: " . date('Y-m-d H:i:s') . "\n\n";

// Method 1: Dashboard stats (pie chart)
echo "1. Dashboard Stats (Pie Chart):\n";
$sql1 = "SELECT status, COUNT(*) as count 
         FROM {local_sesdashboard_mail} 
         WHERE timecreated >= ? 
         GROUP BY status 
         ORDER BY status";
$results1 = $DB->get_records_sql($sql1, [$timestart]);
$total1 = 0;
foreach ($results1 as $result) {
    echo "   {$result->status}: {$result->count}\n";
    $total1 += $result->count;
}
echo "   TOTAL: $total1\n\n";

// Method 2: Daily stats SQL (line chart)
echo "2. Daily Stats SQL (Line Chart):\n";
$sql2 = "SELECT DATE(FROM_UNIXTIME(timecreated)) as date_str, status, COUNT(*) as count
         FROM {local_sesdashboard_mail} 
         WHERE timecreated >= ? 
         GROUP BY DATE(FROM_UNIXTIME(timecreated)), status
         ORDER BY date_str ASC";
$results2 = $DB->get_records_sql($sql2, [$timestart]);
$status_totals = [];
$total2 = 0;
foreach ($results2 as $result) {
    if (!isset($status_totals[$result->status])) {
        $status_totals[$result->status] = 0;
    }
    $status_totals[$result->status] += $result->count;
    $total2 += $result->count;
}
foreach ($status_totals as $status => $count) {
    echo "   $status: $count\n";
}
echo "   TOTAL: $total2\n\n";

// Method 3: Report page (all records)
echo "3. Report Page (All Records):\n";
$sql3 = "SELECT COUNT(*) as count FROM {local_sesdashboard_mail}";
$total3 = $DB->get_field_sql($sql3);
echo "   Total: $total3\n\n";

// Method 4: Report page with date filter
echo "4. Report Page (With Date Filter):\n";
$from = date('Y-m-d', $timestart);
$to = date('Y-m-d');
$from_timestamp = strtotime($from);
$to_timestamp = strtotime($to . ' 23:59:59');
$sql4 = "SELECT COUNT(*) as count 
         FROM {local_sesdashboard_mail} 
         WHERE timecreated >= ? AND timecreated <= ?";
$total4 = $DB->get_field_sql($sql4, [$from_timestamp, $to_timestamp]);
echo "   From: $from ($from_timestamp)\n";
echo "   To: $to 23:59:59 ($to_timestamp)\n";
echo "   Total: $total4\n\n";

echo "=== Summary ===\n";
echo "Dashboard (pie): $total1\n";
echo "Daily stats (line): $total2\n";
echo "All records: $total3\n";
echo "Date filtered: $total4\n";

if ($total1 == $total2 && $total1 == $total4) {
    echo "✅ CONSISTENT! Dashboard and line chart match.\n";
} else {
    echo "❌ MISMATCH DETECTED!\n";
    if ($total1 != $total2) {
        echo "⚠️  Dashboard ($total1) != Daily stats ($total2)\n";
    }
    if ($total1 != $total4) {
        echo "⚠️  Dashboard ($total1) != Date filtered ($total4)\n";
    }
} 
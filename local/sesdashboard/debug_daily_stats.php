<?php
/**
 * Debug Daily Stats vs Dashboard Stats
 * Find why daily stats show 181 but should show 1380 for today
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');

// SECURITY: Add proper authentication
require_login();
$context = context_system::instance();
require_capability('local/sesdashboard:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/sesdashboard/debug_daily_stats.php'));
$PAGE->set_title('Daily Stats Debug');
$PAGE->set_heading('Daily Stats Debug');

echo $OUTPUT->header();

echo "<h1>🔍 Daily Stats vs Dashboard Stats Debug</h1>";

$repository = new \local_sesdashboard\repositories\email_repository();
$timeframe = 7;

// Get both sets of data
$dashboard_stats = $repository->get_dashboard_stats($timeframe);
$daily_stats = $repository->get_daily_stats($timeframe);

echo "<h2>📊 Dashboard Stats (Summary)</h2>";
echo "<table class='table table-bordered'>";
echo "<tr><th>Status</th><th>Count</th></tr>";
$dashboard_total = 0;
foreach ($dashboard_stats as $status => $data) {
    echo "<tr><td>$status</td><td>{$data->count}</td></tr>";
    $dashboard_total += $data->count;
}
echo "<tr><td><strong>TOTAL</strong></td><td><strong>$dashboard_total</strong></td></tr>";
echo "</table>";

echo "<h2>📈 Daily Stats (Line Chart)</h2>";
echo "<table class='table table-bordered'>";
echo "<tr><th>Date</th><th>Sent</th><th>Delivered</th><th>Bounced</th><th>Opened</th><th>Total</th></tr>";

$daily_total = 0;
for ($i = 0; $i < count($daily_stats['dates']); $i++) {
    $date = $daily_stats['dates'][$i];
    $sent = $daily_stats['sent'][$i];
    $delivered = $daily_stats['delivered'][$i];
    $bounced = $daily_stats['bounced'][$i];
    $opened = $daily_stats['opened'][$i];
    $row_total = $sent + $delivered + $bounced + $opened;
    $daily_total += $row_total;
    
    $today = date('Y-m-d');
    $highlight = ($date == $today) ? "background-color: yellow;" : "";
    
    echo "<tr style='$highlight'>";
    echo "<td>$date</td>";
    echo "<td>$sent</td>";
    echo "<td>$delivered</td>";
    echo "<td>$bounced</td>";
    echo "<td>$opened</td>";
    echo "<td><strong>$row_total</strong></td>";
    echo "</tr>";
}
echo "<tr><td><strong>TOTAL</strong></td><td><strong>" . array_sum($daily_stats['sent']) . "</strong></td>";
echo "<td><strong>" . array_sum($daily_stats['delivered']) . "</strong></td>";
echo "<td><strong>" . array_sum($daily_stats['bounced']) . "</strong></td>";
echo "<td><strong>" . array_sum($daily_stats['opened']) . "</strong></td>";
echo "<td><strong>$daily_total</strong></td></tr>";
echo "</table>";

echo "<h2>⚠️ Discrepancy Analysis</h2>";
$difference = $dashboard_total - $daily_total;
echo "<div class='alert alert-" . ($difference == 0 ? "success" : "danger") . "'>";
echo "<h4>Numbers Comparison:</h4>";
echo "<ul>";
echo "<li><strong>Dashboard Total:</strong> $dashboard_total</li>";
echo "<li><strong>Daily Stats Total:</strong> $daily_total</li>";
echo "<li><strong>Difference:</strong> $difference</li>";
echo "</ul>";

if ($difference != 0) {
    echo "<h4>🚨 PROBLEM FOUND!</h4>";
    echo "<p>The daily stats method is missing <strong>$difference</strong> records compared to dashboard stats.</p>";
} else {
    echo "<h4>✅ Numbers Match!</h4>";
    echo "<p>Dashboard and daily stats show the same totals.</p>";
}
echo "</div>";

// Let's check today specifically
$today = date('Y-m-d');
$today_index = array_search($today, $daily_stats['dates']);

if ($today_index !== false) {
    $today_sent = $daily_stats['sent'][$today_index];
    $today_delivered = $daily_stats['delivered'][$today_index];
    $today_total = $today_sent + $today_delivered + $daily_stats['bounced'][$today_index] + $daily_stats['opened'][$today_index];
    
    echo "<h2>🔍 Today's Analysis ($today)</h2>";
    echo "<div class='alert alert-info'>";
    echo "<h4>Daily Stats for Today:</h4>";
    echo "<ul>";
    echo "<li><strong>Sent:</strong> $today_sent</li>";
    echo "<li><strong>Delivered:</strong> $today_delivered</li>";
    echo "<li><strong>Total:</strong> $today_total</li>";
    echo "</ul>";
    
    // Now let's get actual database count for today
    global $DB;
    $today_start = strtotime('today');
    $today_end = $today_start + DAYSECS - 1;
    
    $actual_today = $DB->get_records_sql("
        SELECT status, COUNT(*) as count 
        FROM {local_sesdashboard_mail} 
        WHERE timecreated >= ? AND timecreated <= ?
        GROUP BY status", 
        [$today_start, $today_end]
    );
    
    echo "<h4>Actual Database for Today:</h4>";
    echo "<ul>";
    $actual_total = 0;
    foreach ($actual_today as $record) {
        echo "<li><strong>{$record->status}:</strong> {$record->count}</li>";
        $actual_total += $record->count;
    }
    echo "<li><strong>TOTAL:</strong> $actual_total</li>";
    echo "</ul>";
    
    if ($today_total != $actual_total) {
        echo "<div class='alert alert-danger'>";
        echo "<h4>🚨 TODAY'S DATA MISMATCH!</h4>";
        echo "<p>Daily stats shows <strong>$today_total</strong> but database has <strong>$actual_total</strong> for today.</p>";
        echo "<p>This explains why your dashboard shows wrong numbers!</p>";
        echo "</div>";
    }
    echo "</div>";
}

echo "<h2>🔧 Raw Daily Stats Debug</h2>";
echo "<div class='alert alert-secondary'>";
echo "<h4>Daily Stats Raw Output:</h4>";
echo "<pre>" . json_encode($daily_stats, JSON_PRETTY_PRINT) . "</pre>";
echo "</div>";

echo $OUTPUT->footer(); 
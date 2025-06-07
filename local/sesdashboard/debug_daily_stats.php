<?php
/**
 * Debug page specifically for get_daily_stats method
 * Access this at: https://target.betterworklearning.com/local/sesdashboard/debug_daily_stats.php
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');

// SECURITY: Add proper authentication
require_login();
$context = context_system::instance();
require_capability('local/sesdashboard:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/sesdashboard/debug_daily_stats.php'));
$PAGE->set_title('SES Dashboard - Daily Stats Debug');
$PAGE->set_heading('SES Dashboard - Daily Stats Debug');

echo $OUTPUT->header();

echo "<h2>Daily Stats Debug - Real Time</h2>";
echo "<div class='alert alert-info'>This page will show debug logs from get_daily_stats() method</div>";

// Clear previous logs by calling the logger
\local_sesdashboard\util\logger::debug("=== NEW DEBUG SESSION STARTED ===");

$repository = new \local_sesdashboard\repositories\email_repository();
$timeframe = 7;

echo "<h3>Calling get_daily_stats($timeframe)...</h3>";

try {
    $daily_stats = $repository->get_daily_stats($timeframe);
    
    echo "<h4>Returned Results:</h4>";
    echo "<pre>";
    echo "Dates: " . json_encode($daily_stats['dates']) . "\n";
    echo "Sent totals: " . json_encode($daily_stats['sent']) . " (sum: " . array_sum($daily_stats['sent']) . ")\n";
    echo "Delivered totals: " . json_encode($daily_stats['delivered']) . " (sum: " . array_sum($daily_stats['delivered']) . ")\n";
    echo "Bounced totals: " . json_encode($daily_stats['bounced']) . " (sum: " . array_sum($daily_stats['bounced']) . ")\n";
    echo "Opened totals: " . json_encode($daily_stats['opened']) . " (sum: " . array_sum($daily_stats['opened']) . ")\n";
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
}

echo "<h3>Debug Logs:</h3>";
echo "<div class='alert alert-warning'>Check your Moodle logs or enable debugging to see the detailed logs.</div>";

// Also let's run a direct database query to see what statuses we have
echo "<h3>Direct Database Query (for comparison):</h3>";

global $DB;
$timestart = time() - (($timeframe - 1) * DAYSECS);

$sql = "SELECT DATE(FROM_UNIXTIME(timecreated)) AS event_date,
               status,
               COUNT(*) AS count
          FROM {local_sesdashboard_mail}
         WHERE timecreated >= ?
      GROUP BY DATE(FROM_UNIXTIME(timecreated)), status
      ORDER BY event_date ASC, status ASC";

try {
    $results = $DB->get_records_sql($sql, [$timestart]);
    
    echo "<table class='table table-bordered'>";
    echo "<tr><th>Date</th><th>Status</th><th>Count</th><th>Expected Match</th></tr>";
    
    $status_totals = [];
    foreach ($results as $result) {
        $status = trim($result->status);
        if (!isset($status_totals[$status])) {
            $status_totals[$status] = 0;
        }
        $status_totals[$status] += $result->count;
        
        // Check what this status should match in our switch
        $expected_match = 'Unknown';
        switch ($status) {
            case 'Send': $expected_match = 'sent'; break;
            case 'Delivery': $expected_match = 'delivered'; break;
            case 'DeliveryDelay': $expected_match = 'delivered'; break;
            case 'Bounce': $expected_match = 'bounced'; break;
            case 'Open': $expected_match = 'opened'; break;
            case 'Click': $expected_match = 'opened'; break;
            default: $expected_match = 'UNMATCHED!'; break;
        }
        
        $row_class = ($expected_match == 'UNMATCHED!') ? ' style="background-color: #ffcccc;"' : '';
        echo "<tr$row_class><td>{$result->event_date}</td><td>'$status'</td><td>{$result->count}</td><td>$expected_match</td></tr>";
    }
    echo "</table>";
    
    echo "<h4>Status Totals from Database:</h4>";
    echo "<ul>";
    foreach ($status_totals as $status => $total) {
        echo "<li><strong>'$status'</strong>: $total</li>";
    }
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Database query failed: " . $e->getMessage() . "</div>";
}

echo "<h3>Instructions:</h3>";
echo "<ol>";
echo "<li>Look at the 'Status Totals from Database' section above</li>";
echo "<li>Compare these exact status strings with what our switch statement expects</li>";
echo "<li>Any status showing 'UNMATCHED!' will explain why that data isn't appearing</li>";
echo "<li>Check your Moodle debug logs for detailed processing information</li>";
echo "</ol>";

echo "<h3>Timeframe Comparison:</h3>";

$timeframe = 7;
$daily_timestart = time() - (($timeframe - 1) * DAYSECS);
$dashboard_timestart = time() - (($timeframe - 1) * DAYSECS);

echo "<table class='table table-bordered'>";
echo "<tr><th>Method</th><th>Timestart</th><th>Human Readable</th><th>SQL WHERE</th></tr>";
echo "<tr><td>get_daily_stats</td><td>$daily_timestart</td><td>" . date('Y-m-d H:i:s', $daily_timestart) . "</td><td>timecreated >= $daily_timestart AND GROUP BY DATE</td></tr>";
echo "<tr><td>get_dashboard_stats</td><td>$dashboard_timestart</td><td>" . date('Y-m-d H:i:s', $dashboard_timestart) . "</td><td>timecreated >= $dashboard_timestart</td></tr>";
echo "</table>";

echo "<h3>Testing Both Queries Separately:</h3>";

// Test dashboard stats query
echo "<h4>Dashboard Stats Query (what pie chart uses):</h4>";
$dashboard_sql = "SELECT status, COUNT(*) as count 
                  FROM {local_sesdashboard_mail} 
                  WHERE timecreated >= ? 
                  GROUP BY status 
                  ORDER BY status";

try {
    $dashboard_results = $DB->get_records_sql($dashboard_sql, [$dashboard_timestart]);
    
    echo "<table class='table table-bordered'>";
    echo "<tr><th>Status</th><th>Count</th></tr>";
    $dashboard_total = 0;
    foreach ($dashboard_results as $result) {
        echo "<tr><td>{$result->status}</td><td>{$result->count}</td></tr>";
        $dashboard_total += $result->count;
    }
    echo "<tr><td><strong>TOTAL</strong></td><td><strong>$dashboard_total</strong></td></tr>";
    echo "</table>";
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Dashboard query failed: " . $e->getMessage() . "</div>";
}

// Test daily stats query  
echo "<h4>Daily Stats Query (what line chart uses) - NEW APPROACH:</h4>";
$daily_sql = "SELECT FROM_UNIXTIME(timecreated, '%Y-%m-%d') AS event_date,
                     status,
                     COUNT(*) AS count
              FROM {local_sesdashboard_mail}
             WHERE timecreated >= ?
          GROUP BY FROM_UNIXTIME(timecreated, '%Y-%m-%d'), status
          ORDER BY event_date ASC, status ASC";

try {
    $daily_results = $DB->get_records_sql($daily_sql, [$daily_timestart]);
    
    echo "<table class='table table-bordered'>";
    echo "<tr><th>Date</th><th>Status</th><th>Count</th></tr>";
    $daily_total = 0;
    $daily_status_totals = [];
    foreach ($daily_results as $result) {
        echo "<tr><td>{$result->event_date}</td><td>{$result->status}</td><td>{$result->count}</td></tr>";
        $daily_total += $result->count;
        if (!isset($daily_status_totals[$result->status])) {
            $daily_status_totals[$result->status] = 0;
        }
        $daily_status_totals[$result->status] += $result->count;
    }
    echo "<tr><td colspan='2'><strong>TOTAL</strong></td><td><strong>$daily_total</strong></td></tr>";
    echo "</table>";
    
    echo "<h5>Daily Stats Status Totals:</h5>";
    echo "<ul>";
    foreach ($daily_status_totals as $status => $total) {
        echo "<li><strong>'$status'</strong>: $total</li>";
    }
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Daily stats query failed: " . $e->getMessage() . "</div>";
}

// Now test if the issue is the timeframe or the data distribution
echo "<h3>Record Distribution by Date:</h3>";
$date_distribution_sql = "SELECT DATE(FROM_UNIXTIME(timecreated)) AS event_date,
                                 COUNT(*) AS count
                          FROM {local_sesdashboard_mail}
                         WHERE timecreated >= ?
                      GROUP BY DATE(FROM_UNIXTIME(timecreated))
                      ORDER BY event_date ASC";

try {
    $date_results = $DB->get_records_sql($date_distribution_sql, [$daily_timestart]);
    
    echo "<table class='table table-bordered'>";
    echo "<tr><th>Date</th><th>Total Records</th></tr>";
    foreach ($date_results as $result) {
        echo "<tr><td>{$result->event_date}</td><td>{$result->count}</td></tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Date distribution query failed: " . $e->getMessage() . "</div>";
}

echo $OUTPUT->footer(); 
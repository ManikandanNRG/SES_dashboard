<?php
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');

// SECURITY: Add proper authentication and capability checks
require_login();
$context = context_system::instance();
require_capability('local/sesdashboard:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/sesdashboard/pages/data_analysis.php'));
$PAGE->set_title('SES Dashboard - Data Analysis');
$PAGE->set_heading('SES Dashboard - Data Analysis');

echo $OUTPUT->header();

// Get the repository
$repository = new \local_sesdashboard\repositories\email_repository();
$timeframe = optional_param('timeframe', 7, PARAM_INT);

echo '<h2>Data Analysis for Last ' . $timeframe . ' Days</h2>';

// Get basic stats
$stats = $repository->get_dashboard_stats($timeframe);
echo '<h3>Raw Status Counts:</h3>';
echo '<table class="table table-bordered">';
echo '<thead><tr><th>Status</th><th>Count</th></tr></thead><tbody>';
foreach ($stats as $status => $data) {
    echo '<tr><td>' . $status . '</td><td>' . $data->count . '</td></tr>';
}
echo '</tbody></table>';

// Check for duplicate message IDs
global $DB;
$timestart = time() - ($timeframe * DAYSECS);

$sql = "SELECT messageid, COUNT(*) as count, GROUP_CONCAT(status) as statuses
        FROM {local_sesdashboard_mail} 
        WHERE timecreated >= ? 
        GROUP BY messageid 
        HAVING COUNT(*) > 1 
        ORDER BY count DESC 
        LIMIT 20";

$duplicates = $DB->get_records_sql($sql, [$timestart]);

if (!empty($duplicates)) {
    echo '<h3>Message IDs with Multiple Records:</h3>';
    echo '<table class="table table-bordered">';
    echo '<thead><tr><th>Message ID</th><th>Record Count</th><th>Statuses</th></tr></thead><tbody>';
    foreach ($duplicates as $dup) {
        echo '<tr><td>' . htmlspecialchars($dup->messageid) . '</td><td>' . $dup->count . '</td><td>' . htmlspecialchars($dup->statuses) . '</td></tr>';
    }
    echo '</tbody></table>';
} else {
    echo '<p>No duplicate message IDs found.</p>';
}

// Check for emails with multiple statuses per unique email
$sql = "SELECT email, COUNT(DISTINCT status) as status_count, 
               GROUP_CONCAT(DISTINCT status) as statuses,
               COUNT(*) as total_records
        FROM {local_sesdashboard_mail} 
        WHERE timecreated >= ? 
        GROUP BY email 
        HAVING COUNT(DISTINCT status) > 1 
        ORDER BY status_count DESC 
        LIMIT 20";

$multi_status = $DB->get_records_sql($sql, [$timestart]);

if (!empty($multi_status)) {
    echo '<h3>Email Addresses with Multiple Statuses:</h3>';
    echo '<table class="table table-bordered">';
    echo '<thead><tr><th>Email</th><th>Status Count</th><th>Total Records</th><th>Statuses</th></tr></thead><tbody>';
    foreach ($multi_status as $multi) {
        echo '<tr><td>' . htmlspecialchars($multi->email) . '</td><td>' . $multi->status_count . '</td><td>' . $multi->total_records . '</td><td>' . htmlspecialchars($multi->statuses) . '</td></tr>';
    }
    echo '</tbody></table>';
} else {
    echo '<p>No emails with multiple statuses found.</p>';
}

// Summary analysis
$total_records = $DB->count_records_select('local_sesdashboard_mail', 'timecreated >= ?', [$timestart]);
$unique_emails = $DB->count_records_sql("SELECT COUNT(DISTINCT email) FROM {local_sesdashboard_mail} WHERE timecreated >= ?", [$timestart]);
$unique_messageids = $DB->count_records_sql("SELECT COUNT(DISTINCT messageid) FROM {local_sesdashboard_mail} WHERE timecreated >= ?", [$timestart]);

echo '<h3>Summary:</h3>';
echo '<ul>';
echo '<li>Total Records: ' . $total_records . '</li>';
echo '<li>Unique Email Addresses: ' . $unique_emails . '</li>';
echo '<li>Unique Message IDs: ' . $unique_messageids . '</li>';
echo '<li>Average Records per Email: ' . round($total_records / max($unique_emails, 1), 2) . '</li>';
echo '<li>Average Records per Message ID: ' . round($total_records / max($unique_messageids, 1), 2) . '</li>';
echo '</ul>';

// Time filter form
echo '<form method="get">';
echo '<label for="timeframe">Timeframe (days): </label>';
echo '<select name="timeframe" id="timeframe">';
echo '<option value="0"' . ($timeframe == 0 ? ' selected' : '') . '>Today</option>';
echo '<option value="3"' . ($timeframe == 3 ? ' selected' : '') . '>3 days</option>';
echo '<option value="5"' . ($timeframe == 5 ? ' selected' : '') . '>5 days</option>';
echo '<option value="7"' . ($timeframe == 7 ? ' selected' : '') . '>7 days</option>';
echo '<option value="14"' . ($timeframe == 14 ? ' selected' : '') . '>14 days</option>';
echo '</select>';
echo '<input type="submit" value="Analyze" class="btn btn-primary">';
echo '</form>';

echo $OUTPUT->footer(); 
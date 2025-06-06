<?php
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');

// Remove admin_externalpage_setup - no need for page setup as this is a download

// Get filter parameters
$status = optional_param('status', '', PARAM_ALPHA);
$search = optional_param('search', '', PARAM_TEXT);

// CRITICAL FIX: Use EXACT same timeframe filtering logic as report page
$timeframe = optional_param('timeframe', 7, PARAM_INT);
$from = optional_param('from', '', PARAM_TEXT);
$to = optional_param('to', '', PARAM_TEXT);

// If no explicit date range is provided, apply the timeframe filtering EXACTLY like report page
if (empty($from) && empty($to)) {
    // Validate timeframe to only allow 3, 5, or 7 days (same as dashboard and report)
    if (!in_array($timeframe, [3, 5, 7])) {
        $timeframe = 7;
    }
    
    // CRITICAL FIX: Use EXACT same calculation as dashboard and report
    $timestart = time() - (($timeframe - 1) * DAYSECS);
    $from_timestamp = $timestart; // Use timestamp directly
    $to_timestamp = time(); // Current time
} else {
    // If explicit dates provided, convert them to timestamps
    $from_timestamp = !empty($from) ? strtotime($from) : 0;
    $to_timestamp = !empty($to) ? strtotime($to . ' 23:59:59') : time();
}

// Get repository instance
$repository = new \local_sesdashboard\repositories\email_repository();

// CRITICAL FIX: Use timestamp-based filtering for consistency
if (empty($from) && empty($to)) {
    // Use the timestamp-based method for exact dashboard consistency
    $emails = $repository->get_filtered_emails_by_timestamp(0, 0, $status, $from_timestamp, $to_timestamp, $search); // 0, 0 means no pagination
} else {
    // Use regular date-based filtering when explicit dates provided
    $emails = $repository->get_filtered_emails(0, 0, $status, $from, $to, $search); // 0, 0 means no pagination
}

// Prepare CSV data
$filename = 'email_report_' . date('Y-m-d_His') . '.csv';
$csvdata = [
    ['Date', 'Recipient', 'Subject', 'Status', 'Message ID', 'Event Type']
];

foreach ($emails as $email) {
    $csvdata[] = [
        userdate($email->timecreated),
        $email->email,
        $email->subject,
        $email->status,
        $email->messageid ?? '',
        $email->eventtype ?? ''
    ];
}

// Output CSV
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$fp = fopen('php://output', 'w');
foreach ($csvdata as $row) {
    fputcsv($fp, $row);
}
fclose($fp);
exit;
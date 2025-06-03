<?php
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');

// Remove admin_externalpage_setup - no need for page setup as this is a download

// Get filter parameters
$status = optional_param('status', '', PARAM_ALPHA);
$search = optional_param('search', '', PARAM_TEXT);
$from = optional_param('from', '', PARAM_TEXT);
$to = optional_param('to', '', PARAM_TEXT);

// Get repository instance
$repository = new \local_sesdashboard\repositories\email_repository();

// Get all filtered emails
$emails = $repository->get_filtered_emails(0, 0, $status, $from, $to, $search); // 0, 0 means no pagination

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
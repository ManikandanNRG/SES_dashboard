<?php
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');

// Remove admin_externalpage_setup - no need for page setup as this is a download

// Get filter parameters (removed from/to since we only keep data for 7 days)
$status = optional_param('status', '', PARAM_ALPHA);
$search = optional_param('search', '', PARAM_TEXT);

// Get repository instance
$repository = new \local_sesdashboard\repositories\email_repository();

// Get all filtered emails (removed from/to parameters)
$emails = $repository->get_filtered_emails(0, 0, $status, '', '', $search); // 0, 0 means no pagination

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
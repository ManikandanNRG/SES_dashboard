<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'SES Email Dashboard';

// Settings strings
$string['senderemail'] = 'Sender Email';
$string['senderemail_desc'] = 'The email address used to send emails via Amazon SES';
$string['retention'] = 'Data Retention Period';
$string['retention_desc'] = 'How long to keep email tracking data';
$string['snsarn'] = 'SNS Topic ARN';
$string['snsarn_desc'] = 'Amazon SNS Topic ARN for receiving email notifications';

// Dashboard strings
$string['dashboard'] = 'Email Dashboard';
$string['emailstats'] = 'Email Statistics';
$string['sendrate'] = 'Send Rate';
$string['deliveryrate'] = 'Delivery Rate';
$string['bouncerate'] = 'Bounce Rate';
$string['openrate'] = 'Open Rate';
$string['complaintrate'] = 'Complaint Rate';

// Timeframe strings
$string['last3days'] = 'Last 3 days';
$string['last5days'] = 'Last 5 days';
$string['last7days'] = 'Last 7 days';

// Report strings
$string['emailreport'] = 'Email Report';
$string['backtodashboard'] = 'Back to Dashboard';
$string['backtoreport'] = 'Back to Report';
$string['all'] = 'All';
$string['delivered'] = 'Delivered';
$string['bounced'] = 'Bounced';
$string['complaint'] = 'Complaint';
$string['from'] = 'From';
$string['to'] = 'To';
$string['search'] = 'Search';
$string['searchplaceholder'] = 'Search by email, subject or message ID';
$string['reset'] = 'Reset';
$string['export'] = 'Export';
$string['date'] = 'Date';
$string['messageid'] = 'Message ID';
$string['actions'] = 'Actions';
$string['viewdetails'] = 'View Details';
$string['emaildetails'] = 'Email Details';
$string['noemailsfound'] = 'No emails found';
$string['viewdetailedreport'] = 'View Detailed Report';
$string['exportcsv'] = 'Export CSV';
$string['filter'] = 'Filter';
$string['daterange'] = 'Date Range';
$string['status'] = 'Status';
$string['recipient'] = 'Recipient';
$string['subject'] = 'Subject';
$string['eventtype'] = 'Event Type';
$string['timestamp'] = 'Timestamp';

// Event types
$string['event_delivery'] = 'Delivered';
$string['event_bounce'] = 'Bounced';
$string['event_complaint'] = 'Complaint';
$string['event_open'] = 'Opened';
$string['event_send'] = 'Sent';

// Log messages
$string['log_webhook_received'] = 'Received webhook notification: {$a}';
$string['log_subscription_confirmed'] = 'SNS subscription confirmed';
$string['log_event_stored'] = 'Email event stored: {$a}';
$string['log_invalid_payload'] = 'Invalid webhook payload received';
$string['log_store_failed'] = 'Failed to store email event: {$a}';

// Error messages
$string['errorgetingstats'] = 'Error getting statistics';
$string['errorrenderingpage'] = 'Error rendering page';
$string['emailnotfound'] = 'Email not found';

// Task strings
$string['cleanup_old_data_task'] = 'Clean up old SES email data (7+ days)';

// Event strings
$string['event_cleanup_completed'] = 'SES Dashboard cleanup completed';

// Security and capabilities
$string['sesdashboard:view'] = 'View SES Dashboard';
$string['sesdashboard:manage'] = 'Manage SES Dashboard';
$string['nopermissions'] = 'You do not have permission to access the SES Dashboard';
$string['accessdenied'] = 'Access denied - login required';

// Data validation
$string['datainconsistency'] = 'Data inconsistency detected in email statistics';
$string['debugmode'] = 'Debug mode enabled for troubleshooting';
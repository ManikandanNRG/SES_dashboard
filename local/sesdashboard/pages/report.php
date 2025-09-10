<?php
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');

// SECURITY FIX: Add proper authentication and capability checks
require_login();

// Check if user has SES dashboard view capability
$context = context_system::instance();
require_capability('local/sesdashboard:view', $context);

// Add logging
\local_sesdashboard\util\logger::init();
\local_sesdashboard\util\logger::info('Report page accessed');

// Basic page setup
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/sesdashboard/pages/report.php'));
$PAGE->set_title(get_string('emailreport', 'local_sesdashboard'));
$PAGE->set_heading(get_string('emailreport', 'local_sesdashboard'));

// Get filter parameters early so they're available throughout the script
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 50, PARAM_INT);
$status = optional_param('status', '', PARAM_ALPHA);

// CRITICAL FIX: Use EXACT same timeframe filtering logic as dashboard
$timeframe = optional_param('timeframe', 7, PARAM_INT);
$from = optional_param('from', '', PARAM_TEXT);
$to = optional_param('to', '', PARAM_TEXT);

// If no explicit date range is provided, apply the timeframe filtering EXACTLY like dashboard
if (empty($from) && empty($to)) {
    // Validate timeframe to allow 0 (today), 3, 5, or 7 days (same as dashboard)
    if (!in_array($timeframe, [0, 3, 5, 7])) {
        $timeframe = 7;
    }
    
    // Use timezone-aware midnight for accurate day boundaries (match dashboard)
    $today = usergetmidnight(time()); // Today at 00:00:00 in user's timezone
    
    if ($timeframe == 0) {
        // Today only - from midnight today to current time
        $timestart = $today;
    } else {
        // N days - from N-1 days ago at midnight
        $timestart = $today - (($timeframe - 1) * DAYSECS); // N-1 days ago at 00:00:00
    }
    
    // Override the get_filtered_count method to use direct timestamp comparison
    // instead of date string conversion which can cause discrepancies
    $repository = new \local_sesdashboard\repositories\email_repository();
    
    // Create custom method call that matches dashboard exactly
    $from_timestamp = $timestart; // Use timestamp directly
    $to_timestamp = time(); // Current time
    
    // Clear the date strings to force timestamp usage
    $from = '';
    $to = '';
} else {
    // If explicit dates provided, convert them to timestamps
    $from_timestamp = !empty($from) ? strtotime($from) : 0;
    $to_timestamp = !empty($to) ? strtotime($to . ' 23:59:59') : time();
}

// Handle search parameter more carefully - URL decode and handle + characters
$search_raw = optional_param('search', '', PARAM_RAW);
if (empty($search_raw)) {
    // Try getting from $_GET directly in case of encoding issues
    $search_raw = isset($_GET['search']) ? $_GET['search'] : '';
}
$search = clean_param($search_raw, PARAM_TEXT);
$search = trim($search);

// DEBUG: Log filter parameters
\local_sesdashboard\util\logger::info('Filter parameters - Page: ' . $page . ', Status: ' . $status . ', Search: ' . $search . ', From: ' . $from . ', To: ' . $to . ', Timeframe: ' . $timeframe);

// Get action parameter
$action = optional_param('action', '', PARAM_ALPHA);
$email_id = optional_param('id', 0, PARAM_INT);

// Handle email details view
if ($action === 'view' && $email_id > 0) {
    $repository = new \local_sesdashboard\repositories\email_repository();
    $email_details = $repository->get_email_details($email_id);
    
    if ($email_details) {
        // Add status badge information
        switch ($email_details->status) {
            case 'Send':
                $email_details->status_badge = '<span class="badge badge-primary" style="background: #007bff; color: white; font-weight: bold;">📤 ' . $email_details->status . '</span>';
                $email_details->status_description = '<strong>📤 Send Status:</strong> This email was successfully sent from your system to the email service provider.';
                break;
            case 'Delivery':
                $email_details->status_badge = '<span class="badge badge-success" style="background: #28a745; color: white; font-weight: bold;">✅ ' . $email_details->status . '</span>';
                $email_details->status_description = '<strong>✅ Delivery Status:</strong> This email was successfully delivered to the recipient\'s email server.';
                break;
            case 'Open':
                $email_details->status_badge = '<span class="badge badge-info" style="background: #17a2b8; color: white; font-weight: bold;">👁️ ' . $email_details->status . '</span>';
                $email_details->status_description = '<strong>👁️ Open Status:</strong> The recipient has opened this email. This is tracked through invisible pixels in HTML emails.';
                break;
            case 'Bounce':
                $email_details->status_badge = '<span class="badge badge-danger" style="background: #dc3545; color: white; font-weight: bold;">❌ ' . $email_details->status . '</span>';
                $email_details->status_description = '<strong>❌ Bounce Status:</strong> This email could not be delivered. This could be due to an invalid email address, full mailbox, or server issues.';
                break;
            case 'Click':
                $email_details->status_badge = '<span class="badge badge-warning" style="background: #ffc107; color: #212529; font-weight: bold;">👆 ' . $email_details->status . '</span>';
                $email_details->status_description = '<strong>👆 Click Status:</strong> The recipient has clicked on a link within this email. This shows email engagement.';
                break;
            default:
                $email_details->status_badge = '<span class="badge badge-secondary" style="background: #6c757d; color: white; font-weight: bold;">' . $email_details->status . '</span>';
                $email_details->status_description = '<strong>Status:</strong> ' . $email_details->status;
        }
        
        // Build back URL with preserved filters
        $back_params = [
            'status' => $status,
            'search' => $search,
            'from' => $from,
            'to' => $to,
            'page' => $page
        ];
        if ($perpage != 50) {
            $back_params['perpage'] = $perpage;
        }
        $back_url = new moodle_url('/local/sesdashboard/pages/report.php', $back_params);
        
        // Prepare email details data
        $details_data = [
            'email' => $email_details,
            'formatted_date' => userdate($email_details->timecreated),
            'back_url' => $back_url
        ];
        
        // Add navigation
        $PAGE->navbar->add(get_string('dashboard', 'local_sesdashboard'), new moodle_url('/local/sesdashboard/pages/index.php'));
        $PAGE->navbar->add(get_string('emailreport', 'local_sesdashboard'), new moodle_url('/local/sesdashboard/pages/report.php'));
        $PAGE->navbar->add(get_string('emaildetails', 'local_sesdashboard'));
        
        echo $OUTPUT->header();
        echo $OUTPUT->render_from_template('local_sesdashboard/email_details', $details_data);
        echo $OUTPUT->footer();
        exit;
    } else {
        // Email not found, redirect back to report with preserved filters
        $back_params = [
            'status' => $status,
            'search' => $search,
            'from' => $from,
            'to' => $to,
            'page' => $page
        ];
        if ($perpage != 50) {
            $back_params['perpage'] = $perpage;
        }
        redirect(new moodle_url('/local/sesdashboard/pages/report.php', $back_params), 
                get_string('emailnotfound', 'local_sesdashboard'), null, \core\output\notification::NOTIFY_ERROR);
    }
}

$baseurl = new moodle_url('/local/sesdashboard/pages/report.php');

// Get repository instance
$repository = new \local_sesdashboard\repositories\email_repository();

// CRITICAL FIX: Use timestamp-based filtering when no explicit dates provided
if (empty($from) && empty($to)) {
    // Use the new timestamp-based methods for exact dashboard consistency
    $total = $repository->get_filtered_count_by_timestamp($status, $from_timestamp, $to_timestamp, $search);
    $emails = $repository->get_filtered_emails_by_timestamp($page * $perpage, $perpage, $status, $from_timestamp, $to_timestamp, $search);
} else {
    // Use regular date-based filtering when explicit dates provided
    $total = $repository->get_filtered_count($status, $from, $to, $search);
    $emails = $repository->get_filtered_emails($page * $perpage, $perpage, $status, $from, $to, $search);
}

// Convert emails to array and add formatted data
$emails_array = [];
foreach ($emails as $email) {
    $email_data = (array) $email;
    $email_data['formatted_date'] = userdate($email->timecreated);
    
    // Add status-specific classes and badges
    switch ($email->status) {
        case 'Send':
            $email_data['status_class'] = 'primary';
            $email_data['status_badge'] = '<span class="badge badge-primary" style="background: #007bff; color: white; font-weight: bold;">📤 ' . $email->status . '</span>';
            break;
        case 'Delivery':
            $email_data['status_class'] = 'success';
            $email_data['status_badge'] = '<span class="badge badge-success" style="background: #28a745; color: white; font-weight: bold;">✅ ' . $email->status . '</span>';
            break;
        case 'Open':
            $email_data['status_class'] = 'info';
            $email_data['status_badge'] = '<span class="badge badge-info" style="background: #17a2b8; color: white; font-weight: bold;">👁️ ' . $email->status . '</span>';
            break;
        case 'Bounce':
            $email_data['status_class'] = 'danger';
            $email_data['status_badge'] = '<span class="badge badge-danger" style="background: #dc3545; color: white; font-weight: bold;">❌ ' . $email->status . '</span>';
            break;
        case 'Click':
            $email_data['status_class'] = 'warning';
            $email_data['status_badge'] = '<span class="badge badge-warning" style="background: #ffc107; color: #212529; font-weight: bold;">👆 ' . $email->status . '</span>';
            break;
        default:
            $email_data['status_class'] = 'secondary';
            $email_data['status_badge'] = '<span class="badge badge-secondary" style="background: #6c757d; color: white; font-weight: bold;">' . $email->status . '</span>';
    }
    
    $emails_array[] = $email_data;
}

// Calculate pagination data
$totalpages = ceil($total / $perpage);
$pagination_data = [];

if ($totalpages > 1) {
    // Build pagination URLs with current filters - FIXED: include timeframe and only non-empty filter parameters
    $filter_params = [];
    
    // Only add filter parameters if they have values
    if (!empty($status)) {
        $filter_params['status'] = $status;
    }
    if (!empty($search)) {
        $filter_params['search'] = $search;
    }
    if (!empty($from)) {
        $filter_params['from'] = $from;
    }
    if (!empty($to)) {
        $filter_params['to'] = $to;
    }
    
    // FIXED: Always include timeframe in pagination URLs
    $filter_params['timeframe'] = $timeframe;
    
    // Only include perpage if it's different from default
    if ($perpage != 50) {
        $filter_params['perpage'] = $perpage;
    }

    // Previous page
    if ($page > 0) {
        $prev_params = $filter_params;
        $prev_params['page'] = $page - 1;
        $pagination_data['previous'] = [
            'url' => (new moodle_url($baseurl, $prev_params))->out(false),
            'text' => '« Previous'
        ];
    }

    // Page numbers
    $start_page = max(0, $page - 2);
    $end_page = min($totalpages - 1, $page + 2);
    
    $pagination_data['pages'] = [];
    for ($i = $start_page; $i <= $end_page; $i++) {
        $page_params = $filter_params;
        $page_params['page'] = $i;
        $pagination_data['pages'][] = [
            'number' => $i + 1,
            'url' => (new moodle_url($baseurl, $page_params))->out(false),
            'active' => ($i == $page)
        ];
    }

    // Next page
    if ($page < $totalpages - 1) {
        $next_params = $filter_params;
        $next_params['page'] = $page + 1;
        $pagination_data['next'] = [
            'url' => (new moodle_url($baseurl, $next_params))->out(false),
            'text' => 'Next »'
        ];
    }

    // Add pagination info
    $pagination_data['info'] = [
        'current_page' => $page + 1,
        'total_pages' => $totalpages,
        'total_records' => $total,
        'start_record' => ($page * $perpage) + 1,
        'end_record' => min(($page + 1) * $perpage, $total)
    ];
    
    // DEBUG: Log pagination URLs
    \local_sesdashboard\util\logger::info('Pagination filter params: ' . json_encode($filter_params));
    if (isset($pagination_data['next'])) {
        \local_sesdashboard\util\logger::info('Next page URL: ' . $pagination_data['next']['url']);
    }
    if (isset($pagination_data['pages']) && count($pagination_data['pages']) > 0) {
        \local_sesdashboard\util\logger::info('First page URL: ' . $pagination_data['pages'][0]['url']);
        if (count($pagination_data['pages']) > 1) {
            \local_sesdashboard\util\logger::info('Second page URL: ' . $pagination_data['pages'][1]['url']);
        }
    }
}

// Setup data for template
$data = [
    'emails' => $emails_array,
    'pagination' => $pagination_data,
    'baseurl' => $baseurl->out(false),
    'filters' => [
        'status' => $status,
        'search' => $search,
        'from' => $from,
        'to' => $to,
        'timeframe' => $timeframe,
        'status_send' => ($status === 'Send'),
        'status_delivery' => ($status === 'Delivery'), 
        'status_open' => ($status === 'Open'),
        'status_click' => ($status === 'Click'),
        'status_bounce' => ($status === 'Bounce')
    ],
    'filters_json' => json_encode([
        'status' => $status,
        'search' => $search,
        'from' => $from,
        'to' => $to,
        'timeframe' => $timeframe
    ]),
    // Add current filter parameters for view details URLs
    'current_filters' => [
        'status' => $status,
        'search' => $search,
        'from' => $from,
        'to' => $to,
        'timeframe' => $timeframe,
        'page' => $page,
        'perpage' => $perpage
    ],
    'current_filters_query' => http_build_query(array_filter([
        'status' => $status,
        'search' => $search,
        'from' => $from,
        'to' => $to,
        'timeframe' => $timeframe,
        'page' => $page,
        'perpage' => ($perpage != 50) ? $perpage : null
    ], function($value) {
        return $value !== '' && $value !== null;
    })),
    'downloadurl' => new moodle_url('/local/sesdashboard/pages/download.php'),
    'dashboard_url' => new moodle_url('/local/sesdashboard/pages/index.php'),
    'has_results' => !empty($emails_array),
    'total_count' => $total,
    'timeframe' => $timeframe,
    'timeframeoptions' => [
        ['value' => 0, 'label' => 'Today', 'selected' => ($timeframe == 0)],
        ['value' => 3, 'label' => get_string('last3days', 'local_sesdashboard'), 'selected' => ($timeframe == 3)],
        ['value' => 5, 'label' => get_string('last5days', 'local_sesdashboard'), 'selected' => ($timeframe == 5)],
        ['value' => 7, 'label' => get_string('last7days', 'local_sesdashboard'), 'selected' => ($timeframe == 7)],
    ]
];

// Add navigation links
$PAGE->navbar->add(get_string('dashboard', 'local_sesdashboard'), new moodle_url('/local/sesdashboard/pages/index.php'));
$PAGE->navbar->add(get_string('emailreport', 'local_sesdashboard'));

\local_sesdashboard\util\logger::info('Rendering report template');

// DEBUG: Log template data for pagination
\local_sesdashboard\util\logger::info('Template pagination data: ' . json_encode($data['pagination']));
\local_sesdashboard\util\logger::info('Template filters data: ' . json_encode($data['filters']));

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sesdashboard/report', $data);
echo $OUTPUT->footer();\local_sesdashboard\util\logger::info('Report page completed');

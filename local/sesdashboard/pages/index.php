<?php

// Add a very early log attempt to see if the script starts
require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->libdir . '/adminlib.php');

// SECURITY FIX: Add proper authentication and capability checks
require_login();

// Check if user has SES dashboard view capability
$context = context_system::instance();
require_capability('local/sesdashboard:view', $context);

@error_log("SES Dashboard index.php script started.\n", 3, $CFG->dataroot . '/sesdashboard_logs/debug_startup_' . date('Y-m-d') . '.log');

// Custom logging function
function log_ses_dashboard($message) {
    global $CFG;
    $log_dir = $CFG->dataroot . '/sesdashboard_logs';

    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0777, true);
    }

    $log_file = $log_dir . '/dashboard_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    error_log("[$timestamp] $message\n", 3, $log_file);
}

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/sesdashboard/pages/index.php'));
$PAGE->set_title('SES Dashboard');
$PAGE->set_heading('SES Dashboard');

$PAGE->requires->css('/local/sesdashboard/styles.css');

// FORCE FRESH DATA: Clear template cache only (safe)
try {
    // Clear only template cache to avoid database connection issues
    if (class_exists('cache')) {
        $template_cache = cache::make('core', 'template');
        if ($template_cache) {
            $template_cache->purge();
        }
    }
} catch (Exception $e) {
    log_ses_dashboard('Cache clearing failed: ' . $e->getMessage());
}

// Set anti-cache headers
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

log_ses_dashboard('=== STARTING FRESH PAGE LOAD ===');

// Get statistics - FORCE FRESH DATA
try {
    $repository = new \local_sesdashboard\repositories\email_repository();
    $timeframe = optional_param('timeframe', 7, PARAM_INT);
    
    // Validate timeframe to only allow 3, 5, or 7 days
    if (!in_array($timeframe, [3, 5, 7])) {
        $timeframe = 7;
    }
    
    // Add timestamp to force fresh data
    $cache_buster = time();
    log_ses_dashboard("Getting fresh stats at timestamp: $cache_buster");
    
    $stats = $repository->get_dashboard_stats($timeframe);
    $daily_stats = $repository->get_daily_stats($timeframe);
    
    // Debug logging to understand data inconsistency
    log_ses_dashboard("Raw stats data: " . json_encode($stats));
    
    // Calculate counts - UPDATED ORDER: Send, Delivery, Bounce, Open
    $send_count = isset($stats['Send']) ? $stats['Send']->count : 0;

    // Combine Delivery + DeliveryDelay for Delivered
    $delivery_count = 0;
    if (isset($stats['Delivery'])) {
        $delivery_count += $stats['Delivery']->count;
    }
    if (isset($stats['DeliveryDelay'])) {
        $delivery_count += $stats['DeliveryDelay']->count;
    }

    // Bounce is just Bounce
    $bounce_count = isset($stats['Bounce']) ? $stats['Bounce']->count : 0;

    // Combine Open + Click for Opened
    $open_count = 0;
    if (isset($stats['Open'])) {
        $open_count += $stats['Open']->count;
    }
    if (isset($stats['Click'])) {
        $open_count += $stats['Click']->count;
    }
    
    log_ses_dashboard("Calculated counts - Send: $send_count, Delivery: $delivery_count, Bounce: $bounce_count, Open: $open_count");
    
    // ENHANCED DEBUG: Log all available statuses and their counts
    log_ses_dashboard("=== ALL AVAILABLE STATUSES IN DATABASE ===");
    foreach ($stats as $status => $data) {
        log_ses_dashboard("Status '$status': {$data->count} records");
    }
    log_ses_dashboard("=== END STATUS DEBUG ===");
    
    // IMPROVED CALCULATION LOGIC
    // In SES, emails can have the following flow:
    // 1. Send -> Delivery -> (possibly) Open
    // 2. Send -> Bounce (failed delivery)
    // 3. Some emails might have multiple status records
    
    // CRITICAL FIX: If no Send records, use Delivery + Bounce as total
    // Many SES configurations don't track Send events, only Delivery/Bounce
    $total_emails = $send_count;
    if ($total_emails == 0) {
        // Fallback: use delivery + bounce as total attempted emails
        $total_emails = $delivery_count + $bounce_count;
        log_ses_dashboard("No Send records found, using Delivery + Bounce as total: $total_emails");
    }
    
    if ($total_emails > 0) {
        // FIXED: Adjust rates calculation based on available data
    if ($send_count > 0) {
            // If we have Send records, use traditional calculation
            $send_rate = 100; // If we track it, 100% was sent
            $delivery_rate = round(($delivery_count / $total_emails) * 100);
            $bounce_rate = round(($bounce_count / $total_emails) * 100);
        } else {
            // If no Send records, show delivery/bounce rates based on total attempted
            $send_rate = 0; // No send tracking
            $delivery_rate = round(($delivery_count / $total_emails) * 100);
            $bounce_rate = round(($bounce_count / $total_emails) * 100);
        }
        
        // Open rate: opens out of successfully delivered emails
        $open_rate = $delivery_count > 0 ? round(($open_count / $delivery_count) * 100) : 0;
        
        // Data validation check
        if (($delivery_count + $bounce_count) > $total_emails) {
            log_ses_dashboard("WARNING: Data inconsistency detected! Delivery ($delivery_count) + Bounce ($bounce_count) = " . 
                            ($delivery_count + $bounce_count) . " > Total sent ($total_emails)");
        }
        
    } else {
        // No send data, fallback to delivery-based calculations
        $total_emails = max($delivery_count + $bounce_count, 1);
        $send_rate = 0;
        $delivery_rate = $total_emails > 0 ? round(($delivery_count / $total_emails) * 100) : 0;
        $bounce_rate = $total_emails > 0 ? round(($bounce_count / $total_emails) * 100) : 0;
        $open_rate = $delivery_count > 0 ? round(($open_count / $delivery_count) * 100) : 0;
    }
    
    // Ensure no rate exceeds 100%
    $send_rate = min($send_rate, 100);
    $delivery_rate = min($delivery_rate, 100);
    $bounce_rate = min($bounce_rate, 100);
    $open_rate = min($open_rate, 100);
    
    log_ses_dashboard("Calculated rates: Send: $send_rate%, Delivery: $delivery_rate%, Bounce: $bounce_rate%, Open: $open_rate%");
} catch (Exception $e) {
    log_ses_dashboard('Error getting stats: ' . $e->getMessage());
    print_error('errorgetingstats', 'local_sesdashboard');
}

// Prepare data for charts
try {
    log_ses_dashboard('Preparing chart data');
    
    // Get the data from get_daily_stats which is already in the correct format
    $chart_data = [
        'dates' => $daily_stats['dates'],
        'delivered' => $daily_stats['delivered'],
        'opened' => $daily_stats['opened'],
        'sent' => $daily_stats['sent'],
        'bounced' => $daily_stats['bounced'],
        'delivery_rate' => $delivery_rate,
        'open_rate' => $open_rate,
        'send_rate' => $send_rate,
        'bounce_rate' => $bounce_rate
    ];
    
    log_ses_dashboard('Chart data prepared');
    log_ses_dashboard('Chart data: ' . json_encode($chart_data));

} catch (Exception $e) {
    log_ses_dashboard('Error preparing chart data: ' . $e->getMessage());
    // Ensure $chart_data is defined even on error
    $chart_data = [
        'dates' => [],
        'delivered' => [],
        'opened' => [],
        'sent' => [],
        'bounced' => [],
        'delivery_rate' => 0,
        'open_rate' => 0,
        'send_rate' => 0,
        'bounce_rate' => 0
    ];
}

// Create charts using Moodle's Core Chart API - RESTORE ORIGINAL IMPLEMENTATION
try {
    log_ses_dashboard('Creating charts using Core Chart API');
    
    // Daily Email Activity Chart - CORRECT ORDER: Send, Delivery, Bounce, Open
    $daily_chart = new \core\chart_line();
    $daily_chart->set_smooth(true);
    
    // Create series in the correct order: Send → Delivery → Bounce → Open
    $sent_series = new \core\chart_series('Sent', $daily_stats['sent']);
    $delivered_series = new \core\chart_series('Delivered', $daily_stats['delivered']);
    $bounced_series = new \core\chart_series('Bounced', $daily_stats['bounced']);
    $opened_series = new \core\chart_series('Opened', $daily_stats['opened']);
    
    // Add series to chart in correct order
    $daily_chart->add_series($sent_series);
    $daily_chart->add_series($delivered_series);
    $daily_chart->add_series($bounced_series);
    $daily_chart->add_series($opened_series);
    
    // Set labels (dates)
    $daily_chart->set_labels($daily_stats['dates']);
    
    // Status Distribution Pie Chart - FIXED: Use same logic as summary stats
    $status_chart = new \core\chart_pie();
    $status_series = new \core\chart_series('Email Status', [
        $send_count,     // Already calculated correctly above
        $delivery_count, // Already calculated correctly above (Delivery + DeliveryDelay)
        $bounce_count,   // Already calculated correctly above
        $open_count      // Already calculated correctly above (Open + Click)
    ]);
    $status_chart->add_series($status_series);
    $status_chart->set_labels(['Sent', 'Delivered', 'Bounced', 'Opened']);
    
    log_ses_dashboard('Charts created successfully');
    
} catch (Exception $e) {
    log_ses_dashboard('Error creating charts: ' . $e->getMessage());
    // Fallback to simple text display if charts fail
    $daily_chart = null;
    $status_chart = null;
}

// Update template context
$template_context = [
    'stats' => $stats,
    'send_rate' => $send_rate,        // NEW ORDER: 1. Send rate
    'delivery_rate' => $delivery_rate, // 2. Delivery rate  
    'bounce_rate' => $bounce_rate,     // 3. Bounce rate
    'open_rate' => $open_rate,         // 4. Open rate
    // ADDED: Include actual counts for display
    'send_count' => $send_count,
    'delivery_count' => $delivery_count,
    'bounce_count' => $bounce_count,
    'open_count' => $open_count,
    'total_emails' => $total_emails,
    'timeframe' => $timeframe,
    'report_url' => (new moodle_url('/local/sesdashboard/pages/report.php'))->out(false),
    // CRITICAL FIX: Add daily stats data for chart data tables
    'daily_stats' => $daily_stats,
    'chart_data' => $chart_data,
    'timeframeoptions' => [
        ['value' => 3, 'label' => get_string('last3days', 'local_sesdashboard'), 'selected' => ($timeframe == 3)],
        ['value' => 5, 'label' => get_string('last5days', 'local_sesdashboard'), 'selected' => ($timeframe == 5)],
        ['value' => 7, 'label' => get_string('last7days', 'local_sesdashboard'), 'selected' => ($timeframe == 7)],
    ],
    'daily_chart' => $daily_chart ? $OUTPUT->render($daily_chart) : '<div class="alert alert-info">Chart not available</div>',
    'status_chart' => $status_chart ? $OUTPUT->render($status_chart) : '<div class="alert alert-info">Chart not available</div>'
];

// DEBUG: Log what data is being passed to template
log_ses_dashboard('=== TEMPLATE CONTEXT DEBUG ===');
log_ses_dashboard('Send Rate: ' . $send_rate . '%');
log_ses_dashboard('Delivery Rate: ' . $delivery_rate . '%');
log_ses_dashboard('Bounce Rate: ' . $bounce_rate . '%');
log_ses_dashboard('Open Rate: ' . $open_rate . '%');
log_ses_dashboard('Timeframe: ' . $timeframe);

// DEBUG: Log the daily_stats data that should be in the chart
log_ses_dashboard('Daily Stats being used for charts:');
log_ses_dashboard('Dates: ' . json_encode($daily_stats['dates']));
log_ses_dashboard('Sent data: ' . json_encode($daily_stats['sent']));
log_ses_dashboard('Delivered data: ' . json_encode($daily_stats['delivered']));
log_ses_dashboard('Bounced data: ' . json_encode($daily_stats['bounced']));
log_ses_dashboard('Opened data: ' . json_encode($daily_stats['opened']));

// Add logging for template context
log_ses_dashboard('Template context - report_url: ' . $template_context['report_url']);
log_ses_dashboard('Template context - language strings loaded: ' . 
    json_encode([
        'viewdetailedreport' => get_string('viewdetailedreport', 'local_sesdashboard'),
        'emailstats' => get_string('emailstats', 'local_sesdashboard')
    ]
));

// DEBUG: Log timestart to file
error_log("SES DEBUG: Timestart = $timestart (" . date('Y-m-d H:i:s', $timestart) . ")");

// Render the page
try {
    log_ses_dashboard('Starting page render');
    echo $OUTPUT->header();
    
    // Log before template render
    log_ses_dashboard('About to render dashboard template');
    $rendered_content = $OUTPUT->render_from_template('local_sesdashboard/dashboard', $template_context);
    log_ses_dashboard('Template rendered successfully');
    
    echo $rendered_content;
    echo $OUTPUT->footer();
    log_ses_dashboard('Page rendering complete');
} catch (Exception $e) {
    log_ses_dashboard('Error rendering page: ' . $e->getMessage());
    log_ses_dashboard('Error trace: ' . $e->getTraceAsString());
    // Fallback error display
    echo $OUTPUT->header();
    echo html_writer::tag('div', get_string('errorrenderingpage', 'local_sesdashboard'), ['class' => 'alert alert-danger']);
    echo $OUTPUT->footer();
}

log_ses_dashboard('End of index.php script');
<?php
/**
 * Advanced Analytics Page for SES Dashboard
 * Provides comprehensive email analytics with advanced filtering and insights
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');

// SECURITY: Add proper authentication and capability checks
require_login();
$context = context_system::instance();
require_capability('local/sesdashboard:view', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/sesdashboard/pages/analytics.php'));
$PAGE->set_title('SES Dashboard - Advanced Analytics');
$PAGE->set_heading('SES Dashboard - Advanced Analytics');

// Initialize logger with performance tracking
\local_sesdashboard\util\logger::init();
$start_time = microtime(true);

// Get parameters
$timeframe = optional_param('timeframe', 7, PARAM_INT);
$analysis_type = optional_param('analysis', 'overview', PARAM_ALPHA);
$domain_filter = optional_param('domain', '', PARAM_TEXT);

// Validate timeframe
if (!in_array($timeframe, [7, 14, 30, 90])) {
    $timeframe = 7;
}

$repository = new \local_sesdashboard\repositories\email_repository();

// Get comprehensive analytics data
$analytics_data = [
    'basic_stats' => $repository->get_dashboard_stats($timeframe),
    'daily_trends' => $repository->get_daily_stats($timeframe),
    'domain_analysis' => get_domain_analysis($timeframe, $domain_filter),
    'performance_metrics' => get_performance_metrics($timeframe),
    'deliverability_insights' => get_deliverability_insights($timeframe),
    'engagement_metrics' => get_engagement_metrics($timeframe)
];

/**
 * Get domain-based email analysis
 */
function get_domain_analysis($timeframe, $domain_filter = '') {
    global $DB;
    
    $timestart = time() - ($timeframe * DAYSECS);
    
    $where_clause = 'timecreated >= ?';
    $params = [$timestart];
    
    if (!empty($domain_filter)) {
        $where_clause .= ' AND email LIKE ?';
        $params[] = '%@' . $domain_filter . '%';
    }
    
    $sql = "SELECT 
                SUBSTRING_INDEX(email, '@', -1) as domain,
                COUNT(*) as total_emails,
                COUNT(CASE WHEN status = 'Delivery' THEN 1 END) as delivered,
                COUNT(CASE WHEN status = 'Bounce' THEN 1 END) as bounced,
                COUNT(CASE WHEN status = 'Open' THEN 1 END) as opened,
                ROUND(COUNT(CASE WHEN status = 'Delivery' THEN 1 END) * 100.0 / COUNT(*), 2) as delivery_rate,
                ROUND(COUNT(CASE WHEN status = 'Bounce' THEN 1 END) * 100.0 / COUNT(*), 2) as bounce_rate
            FROM {local_sesdashboard_mail} 
            WHERE $where_clause
            GROUP BY domain 
            ORDER BY total_emails DESC 
            LIMIT 20";
    
    return $DB->get_records_sql($sql, $params);
}

/**
 * Get performance metrics
 */
function get_performance_metrics($timeframe) {
    global $DB;
    
    $timestart = time() - ($timeframe * DAYSECS);
    
    // Get hourly distribution
    $hourly_sql = "SELECT 
                       HOUR(FROM_UNIXTIME(timecreated)) as hour,
                       COUNT(*) as count,
                       COUNT(CASE WHEN status = 'Delivery' THEN 1 END) as delivered
                   FROM {local_sesdashboard_mail} 
                   WHERE timecreated >= ?
                   GROUP BY HOUR(FROM_UNIXTIME(timecreated))
                   ORDER BY hour";
    
    $hourly_data = $DB->get_records_sql($hourly_sql, [$timestart]);
    
    // Get day of week distribution
    $dow_sql = "SELECT 
                    DAYOFWEEK(FROM_UNIXTIME(timecreated)) as day_of_week,
                    COUNT(*) as count,
                    COUNT(CASE WHEN status = 'Delivery' THEN 1 END) as delivered
                FROM {local_sesdashboard_mail} 
                WHERE timecreated >= ?
                GROUP BY DAYOFWEEK(FROM_UNIXTIME(timecreated))
                ORDER BY day_of_week";
    
    $dow_data = $DB->get_records_sql($dow_sql, [$timestart]);
    
    return [
        'hourly' => $hourly_data,
        'day_of_week' => $dow_data
    ];
}

/**
 * Get deliverability insights
 */
function get_deliverability_insights($timeframe) {
    global $DB;
    
    $timestart = time() - ($timeframe * DAYSECS);
    
    // Get bounce reasons analysis
    $bounce_sql = "SELECT 
                       eventtype,
                       COUNT(*) as count
                   FROM {local_sesdashboard_mail} 
                   WHERE timecreated >= ? AND status = 'Bounce'
                   GROUP BY eventtype
                   ORDER BY count DESC";
    
    $bounce_data = $DB->get_records_sql($bounce_sql, [$timestart]);
    
    // Get sending patterns
    $pattern_sql = "SELECT 
                        DATE(FROM_UNIXTIME(timecreated)) as send_date,
                        COUNT(*) as total_sent,
                        COUNT(CASE WHEN status = 'Delivery' THEN 1 END) as delivered,
                        COUNT(CASE WHEN status = 'Bounce' THEN 1 END) as bounced
                    FROM {local_sesdashboard_mail} 
                    WHERE timecreated >= ?
                    GROUP BY DATE(FROM_UNIXTIME(timecreated))
                    ORDER BY send_date DESC";
    
    $pattern_data = $DB->get_records_sql($pattern_sql, [$timestart]);
    
    return [
        'bounce_reasons' => $bounce_data,
        'sending_patterns' => $pattern_data
    ];
}

/**
 * Get engagement metrics
 */
function get_engagement_metrics($timeframe) {
    global $DB;
    
    $timestart = time() - ($timeframe * DAYSECS);
    
    // Calculate engagement rates
    $engagement_sql = "SELECT 
                           COUNT(CASE WHEN status = 'Send' THEN 1 END) as total_sent,
                           COUNT(CASE WHEN status = 'Delivery' THEN 1 END) as delivered,
                           COUNT(CASE WHEN status = 'Open' THEN 1 END) as opened,
                           COUNT(CASE WHEN status = 'Click' THEN 1 END) as clicked
                       FROM {local_sesdashboard_mail} 
                       WHERE timecreated >= ?";
    
    $engagement_data = $DB->get_record_sql($engagement_sql, [$timestart]);
    
    // Calculate rates
    $metrics = [
        'total_sent' => $engagement_data->total_sent,
        'delivered' => $engagement_data->delivered,
        'opened' => $engagement_data->opened,
        'clicked' => $engagement_data->clicked,
        'delivery_rate' => $engagement_data->total_sent > 0 ? 
            round(($engagement_data->delivered / $engagement_data->total_sent) * 100, 2) : 0,
        'open_rate' => $engagement_data->delivered > 0 ? 
            round(($engagement_data->opened / $engagement_data->delivered) * 100, 2) : 0,
        'click_rate' => $engagement_data->opened > 0 ? 
            round(($engagement_data->clicked / $engagement_data->opened) * 100, 2) : 0
    ];
    
    return $metrics;
}

// Prepare template data
$template_data = [
    'timeframe' => $timeframe,
    'analysis_type' => $analysis_type,
    'domain_filter' => $domain_filter,
    'analytics' => $analytics_data,
    'timeframe_options' => [
        ['value' => 7, 'label' => 'Last 7 days', 'selected' => ($timeframe == 7)],
        ['value' => 14, 'label' => 'Last 14 days', 'selected' => ($timeframe == 14)],
        ['value' => 30, 'label' => 'Last 30 days', 'selected' => ($timeframe == 30)],
        ['value' => 90, 'label' => 'Last 90 days', 'selected' => ($timeframe == 90)],
    ],
    'analysis_options' => [
        ['value' => 'overview', 'label' => 'Overview', 'selected' => ($analysis_type == 'overview')],
        ['value' => 'domains', 'label' => 'Domain Analysis', 'selected' => ($analysis_type == 'domains')],
        ['value' => 'performance', 'label' => 'Performance', 'selected' => ($analysis_type == 'performance')],
        ['value' => 'deliverability', 'label' => 'Deliverability', 'selected' => ($analysis_type == 'deliverability')],
    ],
    'dashboard_url' => new moodle_url('/local/sesdashboard/pages/index.php'),
    'report_url' => new moodle_url('/local/sesdashboard/pages/report.php')
];

// Log performance
\local_sesdashboard\util\logger::performance('Advanced analytics page loaded', $start_time, [
    'timeframe' => $timeframe,
    'analysis_type' => $analysis_type,
    'domain_filter' => $domain_filter
]);

// Add navigation
$PAGE->navbar->add(get_string('dashboard', 'local_sesdashboard'), new moodle_url('/local/sesdashboard/pages/index.php'));
$PAGE->navbar->add('Advanced Analytics');

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sesdashboard/analytics', $template_data);
echo $OUTPUT->footer(); 
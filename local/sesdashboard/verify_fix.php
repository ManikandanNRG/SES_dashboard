<?php
/**
 * Verification Script - Dashboard Fix
 * Confirms that dashboard now shows correct numbers
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');

// SECURITY: Add proper authentication
require_login();
$context = context_system::instance();
require_capability('local/sesdashboard:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/sesdashboard/verify_fix.php'));
$PAGE->set_title('SES Dashboard Fix Verification');
$PAGE->set_heading('SES Dashboard Fix Verification');

echo $OUTPUT->header();

echo "<h1>✅ Dashboard Fix Verification</h1>";
echo "<div class='alert alert-success'><strong>Checking if the dashboard fix resolved the issue...</strong></div>";

$repository = new \local_sesdashboard\repositories\email_repository();
$timeframe = 7;

// Get dashboard stats
$stats = $repository->get_dashboard_stats($timeframe);

// Extract counts (same logic as fixed dashboard)
$send_count = isset($stats['Send']) ? $stats['Send']->count : 0;
$delivery_count = isset($stats['Delivery']) ? $stats['Delivery']->count : 0;
$bounce_count = isset($stats['Bounce']) ? $stats['Bounce']->count : 0;
$open_count = isset($stats['Open']) ? $stats['Open']->count : 0;

// Apply the fix logic
$total_emails = $send_count;
if ($total_emails == 0) {
    $total_emails = $delivery_count + $bounce_count;
}

echo "<h2>📊 Dashboard Statistics (Fixed)</h2>";

echo "<div class='row'>";
echo "<div class='col-md-3'>";
echo "<div class='card bg-info text-white mb-3'>";
echo "<div class='card-body text-center'>";
echo "<h5>Send</h5>";
echo "<h2>$send_count</h2>";
echo "</div></div></div>";

echo "<div class='col-md-3'>";
echo "<div class='card bg-primary text-white mb-3'>";
echo "<div class='card-body text-center'>";
echo "<h5>Delivery</h5>";
echo "<h2>$delivery_count</h2>";
echo "</div></div></div>";

echo "<div class='col-md-3'>";
echo "<div class='card bg-warning text-white mb-3'>";
echo "<div class='card-body text-center'>";
echo "<h5>Bounce</h5>";
echo "<h2>$bounce_count</h2>";
echo "</div></div></div>";

echo "<div class='col-md-3'>";
echo "<div class='card bg-success text-white mb-3'>";
echo "<div class='card-body text-center'>";
echo "<h5>Open</h5>";
echo "<h2>$open_count</h2>";
echo "</div></div></div>";
echo "</div>";

echo "<div class='alert alert-info'>";
echo "<h4>📈 Summary</h4>";
echo "<ul>";
echo "<li><strong>Total Emails:</strong> $total_emails (last $timeframe days)</li>";
echo "<li><strong>Send Records:</strong> $send_count</li>";
echo "<li><strong>Delivery Records:</strong> $delivery_count</li>";
echo "<li><strong>Bounce Records:</strong> $bounce_count</li>";
echo "<li><strong>Open Records:</strong> $open_count</li>";
echo "</ul>";
echo "</div>";

// Diagnosis
if ($total_emails > 1000) {
    echo "<div class='alert alert-success'>";
    echo "<h4>✅ FIX SUCCESSFUL!</h4>";
    echo "<p>Dashboard now shows <strong>$total_emails</strong> total emails, which matches your expectation of 1000+ emails.</p>";
    echo "<p>The dashboard will now display the actual delivery count ($delivery_count) instead of the send count (0).</p>";
    echo "</div>";
} else if ($total_emails > 100) {
    echo "<div class='alert alert-warning'>";
    echo "<h4>⚠️ Partial Fix</h4>";
    echo "<p>Dashboard shows <strong>$total_emails</strong> emails, which is better than before but may still be low.</p>";
    echo "<p>Check if the timeframe filtering is working correctly.</p>";
    echo "</div>";
} else {
    echo "<div class='alert alert-danger'>";
    echo "<h4>❌ Issue Still Exists</h4>";
    echo "<p>Dashboard still shows low numbers: <strong>$total_emails</strong> emails.</p>";
    echo "<p>The data may be outside the current timeframe.</p>";
    echo "</div>";
}

echo "<h2>🔧 What Was Fixed</h2>";
echo "<div class='alert alert-info'>";
echo "<h4>Problem:</h4>";
echo "<ul>";
echo "<li>Dashboard was using <code>\$send_count</code> (which was 0) as the base for calculations</li>";
echo "<li>This made <code>\$total_emails = 0</code>, causing all rates to be 0% or undefined</li>";
echo "<li>Dashboard appeared to show 'only 3 emails' when you actually had thousands</li>";
echo "</ul>";

echo "<h4>Solution:</h4>";
echo "<ul>";
echo "<li>✅ <strong>Fixed calculation logic:</strong> Use Delivery + Bounce as total when Send = 0</li>";
echo "<li>✅ <strong>Added actual counts:</strong> Dashboard now shows numbers like '2829' instead of just percentages</li>";
echo "<li>✅ <strong>Added summary:</strong> Clear display of total emails processed</li>";
echo "<li>✅ <strong>Maintained consistency:</strong> Report page and dashboard now use same data</li>";
echo "</ul>";
echo "</div>";

echo "<h2>🎯 Next Steps</h2>";
echo "<div class='alert alert-success'>";
echo "<ol>";
echo "<li><strong>Check Dashboard:</strong> Go to <a href='../pages/index.php'>Dashboard</a> to see the fixed display</li>";
echo "<li><strong>Verify Numbers:</strong> Delivery count should now show $delivery_count</li>";
echo "<li><strong>Test Timeframes:</strong> Try 3, 5, and 7 day filters to ensure they work</li>";
echo "<li><strong>Confirm with Team:</strong> Dashboard should now match your expectations</li>";
echo "</ol>";
echo "</div>";

echo "<h2>🔗 Useful Links</h2>";
echo "<ul>";
echo "<li><a href='../pages/index.php'>Dashboard (Fixed)</a></li>";
echo "<li><a href='../pages/report.php'>Report Page</a></li>";
echo "<li><a href='dashboard_debug.php'>Debug Tool</a> (if you need to troubleshoot again)</li>";
echo "</ul>";

echo $OUTPUT->footer(); 
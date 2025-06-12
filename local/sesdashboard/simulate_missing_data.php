<?php
/**
 * Simulate Missing SES Data
 * Add realistic Delivery, Bounce, and Open records based on existing Send records
 * USE ONLY FOR TESTING - Remove after fixing SES webhook
 */

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');

// SECURITY: Add proper authentication
require_login();
$context = context_system::instance();
require_capability('local/sesdashboard:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/sesdashboard/simulate_missing_data.php'));
$PAGE->set_title('Simulate Missing Data');
$PAGE->set_heading('Simulate Missing Data');

echo $OUTPUT->header();

global $DB;

echo "<h1>⚠️ Simulate Missing SES Data</h1>";
echo "<div class='alert alert-warning'>";
echo "<strong>WARNING:</strong> This script adds simulated data for testing. ";
echo "Remove simulated data after fixing your SES webhook configuration.";
echo "</div>";

$confirm = optional_param('confirm', '', PARAM_ALPHA);

if ($confirm !== 'yes') {
    echo "<h2>What this script does:</h2>";
    echo "<ul>";
    echo "<li>For each <strong>Send</strong> record, creates realistic <strong>Delivery</strong> records (95% success rate)</li>";
    echo "<li>Creates <strong>Bounce</strong> records for remaining 5%</li>";
    echo "<li>Creates <strong>Open</strong> records for 40% of delivered emails</li>";
    echo "<li>Creates <strong>Click</strong> records for 10% of opened emails</li>";
    echo "</ul>";
    
    echo "<h2>Confirm Action:</h2>";
    echo "<form method='post'>";
    echo "<input type='hidden' name='confirm' value='yes'>";
    echo "<button type='submit' class='btn btn-warning'>⚠️ Add Simulated Data (Testing Only)</button>";
    echo "</form>";
    
    echo "<div class='alert alert-info mt-3'>";
    echo "<strong>Better Solution:</strong> Configure your AWS SES to send all event types to your webhook.";
    echo "</div>";
    
} else {
    echo "<h2>🔄 Processing Send Records...</h2>";
    
    // Get all Send records from last 30 days
    $cutoff = time() - (30 * DAYSECS);
    $send_records = $DB->get_records_select('local_sesdashboard_mail', 
        'status = ? AND timecreated >= ?', 
        ['Send', $cutoff], 
        '', 
        'id, email, subject, messageid, timecreated'
    );
    
    $delivery_count = 0;
    $bounce_count = 0;
    $open_count = 0;
    $click_count = 0;
    
    foreach ($send_records as $send_record) {
        // 95% delivery rate
        if (rand(1, 100) <= 95) {
            // Create Delivery record
            $delivery_record = new stdClass();
            $delivery_record->email = $send_record->email;
            $delivery_record->subject = $send_record->subject;
            $delivery_record->messageid = $send_record->messageid;
            $delivery_record->status = 'Delivery';
            $delivery_record->eventtype = 'delivery';
            $delivery_record->timecreated = $send_record->timecreated + rand(300, 1800); // 5-30 min later
            
            $delivery_id = $DB->insert_record('local_sesdashboard_mail', $delivery_record);
            $delivery_count++;
            
            // 40% open rate of delivered emails
            if (rand(1, 100) <= 40) {
                $open_record = new stdClass();
                $open_record->email = $send_record->email;
                $open_record->subject = $send_record->subject;
                $open_record->messageid = $send_record->messageid;
                $open_record->status = 'Open';
                $open_record->eventtype = 'open';
                $open_record->timecreated = $send_record->timecreated + rand(1800, 7200); // 30min-2hr later
                
                $DB->insert_record('local_sesdashboard_mail', $open_record);
                $open_count++;
                
                // 10% click rate of opened emails
                if (rand(1, 100) <= 10) {
                    $click_record = new stdClass();
                    $click_record->email = $send_record->email;
                    $click_record->subject = $send_record->subject;
                    $click_record->messageid = $send_record->messageid;
                    $click_record->status = 'Click';
                    $click_record->eventtype = 'click';
                    $click_record->timecreated = $send_record->timecreated + rand(1900, 7300); // Shortly after open
                    
                    $DB->insert_record('local_sesdashboard_mail', $click_record);
                    $click_count++;
                }
            }
            
        } else {
            // Create Bounce record (5% bounce rate)
            $bounce_record = new stdClass();
            $bounce_record->email = $send_record->email;
            $bounce_record->subject = $send_record->subject;
            $bounce_record->messageid = $send_record->messageid;
            $bounce_record->status = 'Bounce';
            $bounce_record->eventtype = 'bounce';
            $bounce_record->timecreated = $send_record->timecreated + rand(60, 600); // 1-10 min later
            
            $DB->insert_record('local_sesdashboard_mail', $bounce_record);
            $bounce_count++;
        }
    }
    
    echo "<div class='alert alert-success'>";
    echo "<h3>✅ Simulated Data Added:</h3>";
    echo "<ul>";
    echo "<li><strong>Delivery records:</strong> $delivery_count</li>";
    echo "<li><strong>Bounce records:</strong> $bounce_count</li>";
    echo "<li><strong>Open records:</strong> $open_count</li>";
    echo "<li><strong>Click records:</strong> $click_count</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div class='alert alert-info'>";
    echo "<p><strong>Next Steps:</strong></p>";
    echo "<ol>";
    echo "<li>Refresh your dashboard - you should now see data in all columns</li>";
    echo "<li>Fix your AWS SES webhook configuration to send real event data</li>";
    echo "<li>Remove simulated data once real data is flowing</li>";
    echo "</ol>";
    echo "</div>";
    
    echo "<h2>🔗 Quick Links:</h2>";
    echo "<ul>";
    echo "<li><a href='/local/sesdashboard/pages/index.php' class='btn btn-primary'>View Dashboard</a></li>";
    echo "<li><a href='/local/sesdashboard/debug_daily_breakdown.php' class='btn btn-secondary'>Re-run Debug</a></li>";
    echo "</ul>";
}

echo $OUTPUT->footer(); 
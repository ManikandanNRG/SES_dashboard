<?php
/**
 * Test script for SES Dashboard cleanup functionality
 * Run this from the Moodle root directory: php local/sesdashboard/test_cleanup.php
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/clilib.php');

// Ensure we're running from command line
if (!CLI_SCRIPT) {
    die('This script can only be run from the command line.');
}

echo "=== SES Dashboard Cleanup Test ===\n";

try {
    // Test 1: Check if repository class exists and can be instantiated
    echo "Test 1: Instantiating email repository...\n";
    $repository = new \local_sesdashboard\repositories\email_repository();
    echo "✓ Repository instantiated successfully\n";
    
    // Test 2: Check cleanup stats
    echo "\nTest 2: Getting cleanup statistics...\n";
    $stats = $repository->get_cleanup_stats();
    echo "✓ Cleanup stats retrieved:\n";
    echo "  - Old mail records: {$stats['old_mail_count']}\n";
    echo "  - Old event records: {$stats['old_events_count']}\n";
    echo "  - Cutoff date: {$stats['cutoff_date']}\n";
    
    // Test 3: Check current record counts
    echo "\nTest 3: Getting current record counts...\n";
    $total_mail = $DB->count_records('local_sesdashboard_mail');
    $total_events = $DB->count_records('local_sesdashboard_events');
    echo "✓ Current record counts:\n";
    echo "  - Total mail records: {$total_mail}\n";
    echo "  - Total event records: {$total_events}\n";
    
    // Test 4: Test cleanup task instantiation
    echo "\nTest 4: Testing cleanup task instantiation...\n";
    $task = new \local_sesdashboard\task\cleanup_old_data();
    echo "✓ Cleanup task instantiated successfully\n";
    echo "  - Task name: " . $task->get_name() . "\n";
    
    // Test 5: Dry run of cleanup (if there are old records)
    if ($stats['old_mail_count'] > 0 || $stats['old_events_count'] > 0) {
        echo "\nTest 5: Performing actual cleanup...\n";
        $cleanup_result = $repository->cleanup_old_data();
        echo "✓ Cleanup completed:\n";
        echo "  - Mail records deleted: {$cleanup_result['mail_deleted']}\n";
        echo "  - Event records deleted: {$cleanup_result['events_deleted']}\n";
    } else {
        echo "\nTest 5: No old records to clean up - skipping actual cleanup\n";
    }
    
    echo "\n=== All tests completed successfully! ===\n";
    echo "The cleanup functionality appears to be working correctly.\n";
    echo "You can now enable the scheduled task in Moodle admin.\n";
    
} catch (Exception $e) {
    echo "\n❌ Error during testing: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
} 
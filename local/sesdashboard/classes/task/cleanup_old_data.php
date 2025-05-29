<?php
namespace local_sesdashboard\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task to clean up old email data (older than 7 days)
 */
class cleanup_old_data extends \core\task\scheduled_task {
    
    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('cleanup_old_data_task', 'local_sesdashboard');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $CFG;
        
        mtrace('Starting SES Dashboard data cleanup...');
        
        try {
            $repository = new \local_sesdashboard\repositories\email_repository();
            
            // Get stats before cleanup
            $stats_before = $repository->get_cleanup_stats();
            mtrace("Records to be cleaned up: {$stats_before['old_mail_count']} mail records, {$stats_before['old_events_count']} event records");
            mtrace("Cutoff date: {$stats_before['cutoff_date']}");
            
            // Perform cleanup
            $cleanup_result = $repository->cleanup_old_data();
            
            mtrace("Cleanup completed successfully:");
            mtrace("- Deleted {$cleanup_result['mail_deleted']} mail records");
            mtrace("- Deleted {$cleanup_result['events_deleted']} event records");
            
            // Log to Moodle logs
            $event = \core\event\base::create([
                'context' => \context_system::instance(),
                'other' => [
                    'mail_deleted' => $cleanup_result['mail_deleted'],
                    'events_deleted' => $cleanup_result['events_deleted']
                ]
            ]);
            
        } catch (\Exception $e) {
            mtrace('Error during cleanup: ' . $e->getMessage());
            throw $e;
        }
        
        mtrace('SES Dashboard data cleanup completed.');
    }
} 
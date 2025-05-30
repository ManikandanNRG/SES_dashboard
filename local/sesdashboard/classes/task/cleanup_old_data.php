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
            
            // Perform database cleanup
            $cleanup_result = $repository->cleanup_old_data();
            
            mtrace("Database cleanup completed successfully:");
            mtrace("- Deleted {$cleanup_result['mail_deleted']} mail records");
            mtrace("- Deleted {$cleanup_result['events_deleted']} event records");
            
            // ADDED: Clean up old log files
            $log_cleanup_result = $this->cleanup_old_log_files();
            mtrace("Log file cleanup completed:");
            mtrace("- Deleted {$log_cleanup_result['files_deleted']} log files");
            mtrace("- Freed {$log_cleanup_result['space_freed']} bytes");
            
            // Log to Moodle logs
            $event = \core\event\base::create([
                'context' => \context_system::instance(),
                'other' => [
                    'mail_deleted' => $cleanup_result['mail_deleted'],
                    'events_deleted' => $cleanup_result['events_deleted'],
                    'log_files_deleted' => $log_cleanup_result['files_deleted'],
                    'log_space_freed' => $log_cleanup_result['space_freed']
                ]
            ]);
            
            mtrace('SES Dashboard cleanup completed successfully.');
            
        } catch (Exception $e) {
            mtrace('Error during SES Dashboard cleanup: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Clean up old log files (older than 7 days)
     */
    private function cleanup_old_log_files() {
        global $CFG;
        
        $log_dir = $CFG->dataroot . '/sesdashboard_logs';
        $files_deleted = 0;
        $space_freed = 0;
        $cutoff_time = time() - (7 * DAYSECS);
        
        if (!is_dir($log_dir)) {
            return ['files_deleted' => 0, 'space_freed' => 0];
        }
        
        try {
            $iterator = new \DirectoryIterator($log_dir);
            
            foreach ($iterator as $file) {
                if ($file->isDot() || !$file->isFile()) {
                    continue;
                }
                
                // Check if file is older than 7 days
                if ($file->getMTime() < $cutoff_time) {
                    $file_size = $file->getSize();
                    $file_path = $file->getPathname();
                    
                    if (unlink($file_path)) {
                        $files_deleted++;
                        $space_freed += $file_size;
                        mtrace("Deleted old log file: " . $file->getFilename() . " (freed " . $file_size . " bytes)");
                    } else {
                        mtrace("Warning: Could not delete log file: " . $file->getFilename());
                    }
                }
            }
            
            // Remove the log directory if it's empty
            if ($files_deleted > 0 && count(scandir($log_dir)) == 2) { // Only . and .. remain
                if (rmdir($log_dir)) {
                    mtrace("Removed empty log directory: " . $log_dir);
                }
            }
            
        } catch (Exception $e) {
            mtrace('Error cleaning up log files: ' . $e->getMessage());
        }
        
        return [
            'files_deleted' => $files_deleted,
            'space_freed' => $space_freed
        ];
    }
} 
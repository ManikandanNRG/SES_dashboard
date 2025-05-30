<?php
namespace local_sesdashboard\repositories;

defined('MOODLE_INTERNAL') || die();

class email_repository {
    private $table = 'local_sesdashboard_mail';

    /**
     * Get filtered count of emails
     */
    public function get_filtered_count($status = '', $from = '', $to = '', $search = '') {
        global $DB;
        
        list($where, $params) = $this->get_filter_sql($status, $from, $to, $search);
        return $DB->count_records_sql("SELECT COUNT(*) FROM {{$this->table}} " . $where, $params);
    }

    /**
     * Get filtered emails with pagination
     */
    public function get_filtered_emails($start = 0, $limit = 50, $status = '', $from = '', $to = '', $search = '') {
        global $DB;

        list($where, $params) = $this->get_filter_sql($status, $from, $to, $search);
        $sql = "SELECT * FROM {{$this->table}} " . $where . " ORDER BY timecreated DESC";
        
        if ($limit > 0) {
            return $DB->get_records_sql($sql, $params, $start, $limit);
        }
        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Get email details by ID
     */
    public function get_email_details($id) {
        global $DB;
        return $DB->get_record($this->table, ['id' => $id]);
    }

    /**
     * Helper method to build filter SQL
     */
    private function get_filter_sql($status, $from, $to, $search) {
        global $DB;
        
        $where = [];
        $params = [];

        if ($status) {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }

        if ($from) {
            $where[] = 'timecreated >= :from';
            $params['from'] = strtotime($from);
        }

        if ($to) {
            $where[] = 'timecreated <= :to';
            $params['to'] = strtotime($to . ' 23:59:59');
        }

        if ($search) {
            $where[] = $DB->sql_like('email', ':email', false, false) . 
                      ' OR ' . $DB->sql_like('subject', ':subject', false, false) . 
                      ' OR ' . $DB->sql_like('messageid', ':messageid', false, false);
            $params['email'] = '%' . $search . '%';
            $params['subject'] = '%' . $search . '%';
            $params['messageid'] = '%' . $search . '%';
        }

        $whereStr = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        return [$whereStr, $params];
    }

    /**
     * Get email statistics for dashboard
     */
    public function get_dashboard_stats($timeframe = 7) {
        global $DB;
    
        debugging('Starting get_dashboard_stats with timeframe: ' . $timeframe, DEBUG_NORMAL);
    
        $timestart = time() - ($timeframe * DAYSECS);
        debugging('Time start: ' . date('Y-m-d H:i:s', $timestart), DEBUG_NORMAL);
    
        try {
            // Get total records for debugging
            $total = $DB->count_records_select('local_sesdashboard_mail', 
                'timecreated >= ?', [$timestart]);
            debugging('Total records in timeframe: ' . $total, DEBUG_NORMAL);
                
            // Get counts by status
            $sql = "SELECT status, COUNT(*) as count 
                    FROM {local_sesdashboard_mail} 
                    WHERE timecreated >= ? 
                    GROUP BY status";
            $stats = $DB->get_records_sql($sql, [$timestart]);
            debugging('Status stats: ' . json_encode($stats), DEBUG_NORMAL);
            
            // Additional debugging - check for potential duplicates
            $sql_unique_emails = "SELECT COUNT(DISTINCT email) as unique_emails, 
                                         COUNT(DISTINCT messageid) as unique_messageids,
                                         COUNT(*) as total_records
                                  FROM {local_sesdashboard_mail} 
                                  WHERE timecreated >= ?";
            $unique_stats = $DB->get_record_sql($sql_unique_emails, [$timestart]);
            debugging('Unique stats: ' . json_encode($unique_stats), DEBUG_NORMAL);
            
            // Check for emails with multiple statuses
            $sql_multiple_status = "SELECT email, messageid, COUNT(DISTINCT status) as status_count, 
                                           GROUP_CONCAT(DISTINCT status) as statuses
                                    FROM {local_sesdashboard_mail} 
                                    WHERE timecreated >= ? 
                                    GROUP BY email, messageid 
                                    HAVING COUNT(DISTINCT status) > 1
                                    LIMIT 5";
            $multiple_status = $DB->get_records_sql($sql_multiple_status, [$timestart]);
            if (!empty($multiple_status)) {
                debugging('Emails with multiple statuses found: ' . json_encode($multiple_status), DEBUG_NORMAL);
            }
            
            // Return the processed stats
            return $stats;
            
        } catch (Exception $e) {
            debugging('Error in get_dashboard_stats: ' . $e->getMessage(), DEBUG_NORMAL);
            throw $e;
        }
    }

    /**
     * Get daily email statistics
     */
    public function get_daily_stats($days = 7) {
        global $DB;
        
        // Debug the input
        debugging('Starting get_daily_stats with days: ' . $days, DEBUG_NORMAL);
        
        // Calculate the start time (beginning of day, days ago)
        $timestart = strtotime(date('Y-m-d 00:00:00')) - ($days - 1) * 86400;
        debugging('Time start: ' . date('Y-m-d H:i:s', $timestart) . ' (' . $timestart . ')', DEBUG_NORMAL);
        
        // Pre-generate all dates in the range to ensure we have data for each day
        $dates_array = [];
        $delivered_array = [];
        $opened_array = [];
        $sent_array = []; // Changed from clicked_array
        $bounced_array = [];
        
        for ($i = 0; $i < $days; $i++) {
            $date = date('Y-m-d', $timestart + ($i * 86400));
            $dates_array[] = $date;
            $delivered_array[] = 0;
            $opened_array[] = 0;
            $sent_array[] = 0; // Changed from clicked_array
            $bounced_array[] = 0;
        }
        debugging('Generated dates: ' . json_encode($dates_array), DEBUG_NORMAL);
        
        // Debug raw data in the date range
        $raw_data_sql = "SELECT id, timecreated, status FROM {local_sesdashboard_mail} WHERE timecreated >= ? ORDER BY timecreated ASC LIMIT 20";
        $raw_results = $DB->get_records_sql($raw_data_sql, [$timestart]);
        
        // Log the raw data with formatted dates for debugging
        $formatted_results = [];
        foreach ($raw_results as $record) {
            $formatted_results[] = [
                'id' => $record->id,
                'timecreated' => $record->timecreated,
                'formatted_date' => date('Y-m-d', $record->timecreated),
                'status' => $record->status
            ];
        }
        debugging('Raw data sample with formatted dates: ' . json_encode($formatted_results), DEBUG_NORMAL);
        
        // Try a PHP-based approach instead of relying on MySQL's FROM_UNIXTIME
        try {
            // Get all records in the date range
            $sql = "SELECT id, timecreated, status FROM {local_sesdashboard_mail} WHERE timecreated >= ?";
            $all_records = $DB->get_records_sql($sql, [$timestart]);
            debugging('Total records retrieved: ' . count($all_records), DEBUG_NORMAL);
            
            // Process records in PHP
            foreach ($all_records as $record) {
                // Format the date in PHP
                $date = date('Y-m-d', $record->timecreated);
                debugging('Processing record: id=' . $record->id . ', date=' . $date . ', status=' . $record->status, DEBUG_NORMAL);
                
                // Find the date index
                $date_index = array_search($date, $dates_array);
                if ($date_index === false) {
                    debugging('Date not in range: ' . $date, DEBUG_NORMAL);
                    continue;
                }
                
                $status = trim($record->status);
                
                // Update the appropriate array
                if ($status === 'Delivery') {
                    $delivered_array[$date_index]++;
                } else if ($status === 'Open') {
                    $opened_array[$date_index]++;
                } else if ($status === 'Send') { // Changed from 'Click'
                    $sent_array[$date_index]++;
                } else if ($status === 'Bounce') {
                    $bounced_array[$date_index]++;
                } else {
                    debugging('Unknown status: ' . $status, DEBUG_NORMAL);
                }
            }
            
            // Build the final data structure
            $data = [
                'dates' => $dates_array,
                'delivered' => $delivered_array,
                'opened' => $opened_array,
                'sent' => $sent_array, // Changed from clicked_array
                'bounced' => $bounced_array
            ];
            
            debugging('Final data: ' . json_encode($data), DEBUG_NORMAL);
            return $data;
        } catch (Exception $e) {
            debugging('Error processing records: ' . $e->getMessage(), DEBUG_NORMAL);
            // Return empty data structure on error
            return [
                'dates' => $dates_array,
                'delivered' => $delivered_array,
                'opened' => $opened_array,
                'sent' => $sent_array,
                'bounced' => $bounced_array
            ];
        }
    }

    /**
     * Clean up old email data (older than 7 days)
     * This method should be called regularly via cron
     */
    public function cleanup_old_data() {
        global $DB;
        
        // Calculate cutoff time (7 days ago)
        $cutoff_time = time() - (7 * DAYSECS);
        
        try {
            // Start transaction
            $transaction = $DB->start_delegated_transaction();
            
            // Delete old records from main table
            $deleted_mail = $DB->delete_records_select('local_sesdashboard_mail', 
                'timecreated < ?', [$cutoff_time]);
            
            // Delete old records from events table
            $deleted_events = $DB->delete_records_select('local_sesdashboard_events', 
                'timestamp < ?', [$cutoff_time]);
            
            // Commit transaction
            $transaction->allow_commit();
            
            // Log cleanup results
            debugging("SES Dashboard cleanup: Deleted $deleted_mail mail records and $deleted_events event records older than 7 days", DEBUG_NORMAL);
            
            return [
                'mail_deleted' => $deleted_mail,
                'events_deleted' => $deleted_events,
                'cutoff_time' => $cutoff_time
            ];
            
        } catch (Exception $e) {
            $transaction->rollback($e);
            debugging('SES Dashboard cleanup failed: ' . $e->getMessage(), DEBUG_NORMAL);
            throw $e;
        }
    }

    /**
     * Get cleanup statistics
     */
    public function get_cleanup_stats() {
        global $DB;
        
        $cutoff_time = time() - (7 * DAYSECS);
        
        $old_mail_count = $DB->count_records_select('local_sesdashboard_mail', 
            'timecreated < ?', [$cutoff_time]);
        $old_events_count = $DB->count_records_select('local_sesdashboard_events', 
            'timestamp < ?', [$cutoff_time]);
        
        return [
            'old_mail_count' => $old_mail_count,
            'old_events_count' => $old_events_count,
            'cutoff_date' => userdate($cutoff_time)
        ];
    }
}
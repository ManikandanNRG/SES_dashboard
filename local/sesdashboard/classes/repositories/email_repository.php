<?php
namespace local_sesdashboard\repositories;

defined('MOODLE_INTERNAL') || die();

class email_repository {
    private $table = 'local_sesdashboard_mail';

    /**
     * Get filtered count of emails using direct timestamps (for consistency with dashboard)
     * CRITICAL: This method matches dashboard behavior exactly - uses only >= condition
     */
    public function get_filtered_count_by_timestamp($status = '', $from_timestamp = 0, $to_timestamp = 0, $search = '') {
        global $DB;
        
        $where = [];
        $params = [];

        if ($status) {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }

        if ($from_timestamp > 0) {
            $where[] = 'timecreated >= :from_timestamp';
            $params['from_timestamp'] = $from_timestamp;
        }

        // CRITICAL FIX: Don't add upper limit condition to match dashboard behavior exactly
        // Dashboard uses only: WHERE timecreated >= $timestart
        // We should do the same for consistency
        
        if ($search) {
            $where[] = '(' . $DB->sql_like('email', ':email', false, false) . 
                      ' OR ' . $DB->sql_like('subject', ':subject', false, false) . 
                      ' OR ' . $DB->sql_like('messageid', ':messageid', false, false) . ')';
            $params['email'] = '%' . $search . '%';
            $params['subject'] = '%' . $search . '%';
            $params['messageid'] = '%' . $search . '%';
        }

        $whereStr = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "SELECT COUNT(*) FROM {local_sesdashboard_mail} $whereStr";
        
        $count = $DB->count_records_sql($sql, $params);
        
        return $count;
    }

    /**
     * Get filtered emails using direct timestamps (for consistency with dashboard)
     * CRITICAL: This method matches dashboard behavior exactly - uses only >= condition
     */
    public function get_filtered_emails_by_timestamp($start = 0, $limit = 50, $status = '', $from_timestamp = 0, $to_timestamp = 0, $search = '') {
        global $DB;
        
        $where = [];
        $params = [];

        if ($status) {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }

        if ($from_timestamp > 0) {
            $where[] = 'timecreated >= :from_timestamp';
            $params['from_timestamp'] = $from_timestamp;
        }

        // CRITICAL FIX: Don't add upper limit condition to match dashboard behavior exactly
        // Dashboard uses only: WHERE timecreated >= $timestart
        // We should do the same for consistency

        if ($search) {
            $where[] = '(' . $DB->sql_like('email', ':email', false, false) . 
                      ' OR ' . $DB->sql_like('subject', ':subject', false, false) . 
                      ' OR ' . $DB->sql_like('messageid', ':messageid', false, false) . ')';
            $params['email'] = '%' . $search . '%';
            $params['subject'] = '%' . $search . '%';
            $params['messageid'] = '%' . $search . '%';
        }

        $whereStr = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // OPTIMIZATION: Use specific field selection instead of *
        $sql = "SELECT id, email, subject, status, messageid, eventtype, timecreated 
                FROM {local_sesdashboard_mail} $whereStr 
                ORDER BY timecreated DESC";
        
        if ($limit > 0) {
            $results = $DB->get_records_sql($sql, $params, $start, $limit);
        } else {
            $results = $DB->get_records_sql($sql, $params);
        }
        
        return $results;
    }

    /**
     * Get filtered count of emails
     */
    public function get_filtered_count($status = '', $from = '', $to = '', $search = '') {
        global $DB;
        
        list($where, $params) = $this->get_filter_sql($status, $from, $to, $search);
        
        $sql = "SELECT COUNT(*) FROM {local_sesdashboard_mail} $where";
        
        $count = $DB->count_records_sql($sql, $params);
        
        return $count;
    }

    /**
     * Get filtered emails with pagination - OPTIMIZED VERSION
     */
    public function get_filtered_emails($start = 0, $limit = 50, $status = '', $from = '', $to = '', $search = '') {
        global $DB;
        
        list($where, $params) = $this->get_filter_sql($status, $from, $to, $search);
        
        // OPTIMIZATION: Use specific field selection instead of *
        $sql = "SELECT id, email, subject, status, messageid, eventtype, timecreated 
                FROM {local_sesdashboard_mail} $where 
                ORDER BY timecreated DESC";
        
        if ($limit > 0) {
            $results = $DB->get_records_sql($sql, $params, $start, $limit);
        } else {
            $results = $DB->get_records_sql($sql, $params);
        }
        
        return $results;
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
            $where[] = '(' . $DB->sql_like('email', ':email', false, false) . 
                      ' OR ' . $DB->sql_like('subject', ':subject', false, false) . 
                      ' OR ' . $DB->sql_like('messageid', ':messageid', false, false) . ')';
            $params['email'] = '%' . $search . '%';
            $params['subject'] = '%' . $search . '%';
            $params['messageid'] = '%' . $search . '%';
        }

        $whereStr = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        return [$whereStr, $params];
    }

    /**
     * FIXED: Get dashboard stats with correct data structure
     */
    public function get_dashboard_stats($timeframe = 7) {
        global $DB;
    
        // FIXED: Calculate timestart using same logic as get_daily_stats
        // Use midnight timestamps for accurate day boundaries
        $today = strtotime('today'); // Today at 00:00:00
        $timestart = $today - (($timeframe - 1) * DAYSECS); // N-1 days ago at 00:00:00
    
        try {
            // OPTIMIZATION: Use more efficient query with indexing
            $sql = "SELECT status, COUNT(*) as count 
                    FROM {local_sesdashboard_mail} 
                    WHERE timecreated >= ? 
                    GROUP BY status 
                    ORDER BY status";
            $results = $DB->get_records_sql($sql, [$timestart]);
            
            // FIXED: Convert to the format expected by dashboard (status as key)
            $stats = [];
            foreach ($results as $result) {
                $status = trim($result->status);
                $stats[$status] = $result; // Keep the full object but index by status
            }
            
            return $stats;
            
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * FIXED: Get daily stats with a memory-efficient, scalable, and correct SQL query.
     * This method now performs aggregation in the database to avoid loading thousands of
     * records into PHP memory, which was the root cause of the data mismatch.
     */
    public function get_daily_stats($days = 7) {
        global $DB;

        // FIXED: Calculate date range properly - start from N days ago to today
        // Use midnight timestamps for accurate day boundaries
        $today = strtotime('today'); // Today at 00:00:00
        $timestart = $today - (($days - 1) * DAYSECS); // N-1 days ago at 00:00:00
        
        $dates_array = [];
        for ($i = 0; $i < $days; $i++) {
            $date_timestamp = $timestart + ($i * DAYSECS);
            $dates_array[] = date('Y-m-d', $date_timestamp);
        }

        // Initialize data arrays with zeros for each day in the range.
        $data_arrays = [
            'delivered' => array_fill_keys($dates_array, 0),
            'opened'    => array_fill_keys($dates_array, 0),
            'sent'      => array_fill_keys($dates_array, 0),
            'bounced'   => array_fill_keys($dates_array, 0)
        ];

        try {
            // HYBRID APPROACH: Use the working dashboard query approach but get date distribution
            // This ensures we get ALL data (like pie chart) but distribute it by date efficiently
            
            \local_sesdashboard\util\logger::debug("get_daily_stats: Using hybrid approach to get date distributions for each status");
            
            // Status mapping for line chart
            $status_mapping = [
                'Send' => 'sent',
                'Delivery' => 'delivered',
                'DeliveryDelay' => 'delivered',
                'Bounce' => 'bounced',
                'Open' => 'opened',
                'Click' => 'opened'
            ];
            
            $processed_debug = [];
            
            // For each status, get its date distribution using individual queries
            foreach ($status_mapping as $status => $target_array) {
                $status_sql = "SELECT FROM_UNIXTIME(timecreated, '%Y-%m-%d') AS event_date,
                                      COUNT(*) AS count
                               FROM {local_sesdashboard_mail}
                               WHERE timecreated >= ? AND status = ?
                               GROUP BY FROM_UNIXTIME(timecreated, '%Y-%m-%d')
                               ORDER BY event_date ASC";
                
                $status_results = $DB->get_records_sql($status_sql, [$timestart, $status]);
                
                \local_sesdashboard\util\logger::debug("Status '$status': Found " . count($status_results) . " date groups");
                
                if (!isset($processed_debug[$status])) {
                    $processed_debug[$status] = 0;
                }
                
                foreach ($status_results as $result) {
                    $record_date = $result->event_date;
                    $count = (int)$result->count;
                    
                    // Only process if date is in our expected range
                    if (array_key_exists($record_date, $data_arrays[$target_array])) {
                        $data_arrays[$target_array][$record_date] += $count;
                        $processed_debug[$status] += $count;
                        \local_sesdashboard\util\logger::debug("Added $count '$status' records to '$target_array' for $record_date");
                    } else {
                        \local_sesdashboard\util\logger::debug("SKIPPED: Date '$record_date' not in expected range for status '$status'");
                    }
                }
            }

            // DEBUG: Final summary
            \local_sesdashboard\util\logger::debug("=== FINAL DEBUG SUMMARY ===");
            \local_sesdashboard\util\logger::debug("Successfully processed by status: " . json_encode($processed_debug));
            
            // DEBUG: Final array totals
            $final_totals = [
                'sent' => array_sum($data_arrays['sent']),
                'delivered' => array_sum($data_arrays['delivered']),
                'bounced' => array_sum($data_arrays['bounced']),
                'opened' => array_sum($data_arrays['opened'])
            ];
            \local_sesdashboard\util\logger::debug("Final array totals: " . json_encode($final_totals));
            
            // VERIFICATION: Check against dashboard stats
            $dashboard_totals = 0;
            $dashboard_stats = $this->get_dashboard_stats($days);
            foreach ($dashboard_stats as $status => $data) {
                $dashboard_totals += $data->count;
            }
            $line_totals = array_sum($final_totals);
            \local_sesdashboard\util\logger::debug("Dashboard total: $dashboard_totals, Line chart total: $line_totals");
            
        } catch (Exception $e) {
            \local_sesdashboard\util\logger::debug("SQL query failed in get_daily_stats: " . $e->getMessage());
        }
        
        // Return the final data, ensuring the keys (dates) are reset to indexed arrays for the chart library.
        return [
            'dates'     => array_values($dates_array),
            'delivered' => array_values($data_arrays['delivered']),
            'opened'    => array_values($data_arrays['opened']),
            'sent'      => array_values($data_arrays['sent']),
            'bounced'   => array_values($data_arrays['bounced'])
        ];
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
            return [
                'mail_deleted' => $deleted_mail,
                'events_deleted' => $deleted_events,
                'cutoff_time' => $cutoff_time
            ];
            
        } catch (Exception $e) {
            $transaction->rollback($e);
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
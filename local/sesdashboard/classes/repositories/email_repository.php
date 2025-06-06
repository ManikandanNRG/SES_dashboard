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
    
        // FIXED: Calculate timestart for exactly last N days (today + previous N-1 days)
        // This matches the get_daily_stats calculation
        $timestart = time() - (($timeframe - 1) * DAYSECS);
    
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

        // Calculate timestart and generate the complete date array for the last N days.
        $timestart = time() - (($days - 1) * DAYSECS);
        $dates_array = [];
        $start_date = $timestart;
        for ($i = 0; $i < $days; $i++) {
            $dates_array[] = date('Y-m-d', $start_date + ($i * DAYSECS));
        }

        // Initialize data arrays with zeros for each day in the range.
        $data_arrays = [
            'delivered' => array_fill_keys($dates_array, 0),
            'opened'    => array_fill_keys($dates_array, 0),
            'sent'      => array_fill_keys($dates_array, 0),
            'bounced'   => array_fill_keys($dates_array, 0)
        ];

        // EFFICIENT SQL: Group by date and status directly in the database.
        // This prevents memory exhaustion by not loading all raw records into PHP.
        // DATE(FROM_UNIXTIME(...)) is used as it's consistent with the debug report's SQL.
        $sql = "SELECT DATE(FROM_UNIXTIME(timecreated)) AS event_date,
                       status,
                       COUNT(*) AS count
                  FROM {local_sesdashboard_mail}
                 WHERE timecreated >= ?
              GROUP BY DATE(FROM_UNIXTIME(timecreated)), status
              ORDER BY event_date ASC";

        try {
            $results = $DB->get_records_sql($sql, [$timestart]);

            // Process the pre-aggregated results from the database.
            foreach ($results as $result) {
                $record_date = $result->event_date;
                $status = trim($result->status);
                $count = (int)$result->count;

                // Ensure the date from the DB exists in our date range.
                if (isset($data_arrays['sent'][$record_date])) {
                    switch ($status) {
                        case 'Send':
                            $data_arrays['sent'][$record_date] += $count;
                            break;
                        case 'Delivery':
                            $data_arrays['delivered'][$record_date] += $count;
                            break;
                        case 'Open':
                            $data_arrays['opened'][$record_date] += $count;
                            break;
                        case 'Bounce':
                            $data_arrays['bounced'][$record_date] += $count;
                            break;
                        case 'Click':
                            // Map Click counts to the 'opened' category for line chart consistency.
                            $data_arrays['opened'][$record_date] += $count;
                            break;
                        case 'DeliveryDelay':
                            // Map DeliveryDelay counts to the 'delivered' category.
                            $data_arrays['delivered'][$record_date] += $count;
                            break;
                    }
                }
            }
        } catch (Exception $e) {
            \local_sesdashboard\util\logger::debug("SQL query failed in get_daily_stats: " . $e->getMessage());
            // In case of error, the arrays will remain filled with zeros.
        }
        
        // Return the final data, ensuring the keys (dates) are reset to indexed arrays for the chart library.
        return [
            'dates'     => array_keys($data_arrays['sent']),
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
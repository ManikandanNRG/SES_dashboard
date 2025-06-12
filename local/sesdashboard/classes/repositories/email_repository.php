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

        // Add upper limit condition if provided
        if ($to_timestamp > 0) {
            $where[] = 'timecreated <= :to_timestamp';
            $params['to_timestamp'] = $to_timestamp;
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

        // Add upper limit condition if provided
        if ($to_timestamp > 0) {
            $where[] = 'timecreated <= :to_timestamp';
            $params['to_timestamp'] = $to_timestamp;
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
     * CRITICAL FIX: Get dashboard stats using the EXACT same SQL approach as report.php and daily stats
     * This ensures pie chart shows the same data as line chart and report page
     */
    public function get_dashboard_stats($timeframe = 7) {
        global $DB;
    
        // Use EXACT same time calculation as get_daily_stats and report.php
        $today = strtotime('today'); // Today at 00:00:00
        $timestart = $today - (($timeframe - 1) * DAYSECS); // N-1 days ago at 00:00:00
        $timeend = time(); // Current time as upper boundary
    
        try {
            // CRITICAL FIX: Use the EXACT same SQL as report.php and daily stats
            // This ensures pie chart data matches line chart and report page exactly
            $sql = "SELECT id, email, subject, status, messageid, eventtype, timecreated 
                    FROM {local_sesdashboard_mail} 
                    WHERE timecreated >= ? AND timecreated <= ?
                    ORDER BY timecreated DESC";
            $results = $DB->get_records_sql($sql, [$timestart, $timeend]);
            
            // DEBUG: Log the query and total records to verify
            error_log("Dashboard Stats SQL (FIXED): " . $sql);
            error_log("Dashboard Stats Parameters: [" . $timestart . ", " . $timeend . "]");
            error_log("Dashboard Stats Raw Results Count: " . count($results));
            
            // Process each record and count by status (same as daily stats processing)
            $status_counts = [];
            foreach ($results as $result) {
                $status = trim($result->status);
                if (!isset($status_counts[$status])) {
                    $status_counts[$status] = 0;
                }
                $status_counts[$status]++;
            }
            
            // Convert to the format expected by dashboard (status as key with count object)
            $stats = [];
            foreach ($status_counts as $status => $count) {
                $stats[$status] = (object)['count' => $count];
            }
            
            // DEBUG: Log the final counts to verify they match line chart
            error_log("Dashboard Stats Final Counts:");
            foreach ($stats as $status => $data) {
                error_log("Status '$status': {$data->count} records");
            }
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("get_dashboard_stats error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * CRITICAL FIX: Get daily stats using the EXACT same SQL approach as report.php
     * This ensures the line chart shows the same data as the report page
     */
    public function get_daily_stats($days = 7) {
        global $DB;
        
        // Use EXACT same time calculation as report.php and dashboard
        $today = strtotime('today'); // Today at 00:00:00
        $timestart = $today - (($days - 1) * DAYSECS); // N-1 days ago at 00:00:00
        $timeend = time(); // Current time as upper boundary
        
        // Create dates array for the chart
        $dates_array = [];
        for ($i = 0; $i < $days; $i++) {
            $date_timestamp = $timestart + ($i * DAYSECS);
            $dates_array[] = date('Y-m-d', $date_timestamp);
        }

        // Initialize data arrays
        $data_arrays = [
            'delivered' => array_fill(0, $days, 0),
            'opened'    => array_fill(0, $days, 0),
            'sent'      => array_fill(0, $days, 0),
            'bounced'   => array_fill(0, $days, 0)
        ];

        try {
            // CRITICAL FIX: Use the EXACT same SQL as report.php get_filtered_emails_by_timestamp
            // This is the SQL that works correctly in report.php
            $sql = "SELECT id, email, subject, status, messageid, eventtype, timecreated 
                    FROM {local_sesdashboard_mail} 
                    WHERE timecreated >= ? AND timecreated <= ?
                    ORDER BY timecreated DESC";
            
            $results = $DB->get_records_sql($sql, [$timestart, $timeend]);
            
            // DEBUG: Log the query and total records to verify
            error_log("Daily Stats SQL (FIXED): " . $sql);
            error_log("Daily Stats Parameters: [" . $timestart . ", " . $timeend . "]");
            error_log("Daily Stats Raw Results Count: " . count($results));
            
            // Process each record and assign to correct date and status
            foreach ($results as $result) {
                // Convert timestamp to date
                $record_date = date('Y-m-d', $result->timecreated);
                $status = trim($result->status);
                
                // Find the date index in our dates array
                $date_index = array_search($record_date, $dates_array);
                
                if ($date_index !== false) {
                    // Map status to chart series - EXACT same logic as index.php
                    switch ($status) {
                        case 'Send':
                            $data_arrays['sent'][$date_index]++;
                            break;
                        case 'Delivery':
                        case 'DeliveryDelay':
                            $data_arrays['delivered'][$date_index]++;
                            break;
                        case 'Bounce':
                            $data_arrays['bounced'][$date_index]++;
                            break;
                        case 'Open':
                        case 'Click':
                            $data_arrays['opened'][$date_index]++;
                            break;
                    }
                } else {
                    // DEBUG: Log dates that fall outside expected range
                    error_log("Date outside range: " . $record_date . " (record timestamp: " . $result->timecreated . ")");
                }
            }
            
            // DEBUG: Log the final totals to verify they match the pie chart
            $total_sent = array_sum($data_arrays['sent']);
            $total_delivered = array_sum($data_arrays['delivered']);
            $total_bounced = array_sum($data_arrays['bounced']);
            $total_opened = array_sum($data_arrays['opened']);
            
            error_log("Daily Stats Final Totals - Sent: $total_sent, Delivered: $total_delivered, Bounced: $total_bounced, Opened: $total_opened");
            
            // DEBUG: Log daily breakdown
            foreach ($dates_array as $i => $date) {
                if ($data_arrays['sent'][$i] > 0 || $data_arrays['delivered'][$i] > 0 || $data_arrays['bounced'][$i] > 0 || $data_arrays['opened'][$i] > 0) {
                    error_log("Date $date: Sent={$data_arrays['sent'][$i]}, Delivered={$data_arrays['delivered'][$i]}, Bounced={$data_arrays['bounced'][$i]}, Opened={$data_arrays['opened'][$i]}");
                }
            }
            
        } catch (Exception $e) {
            // Log error but don't break the dashboard
            error_log("get_daily_stats error: " . $e->getMessage());
        }
        
        return [
            'dates'     => $dates_array,
            'delivered' => $data_arrays['delivered'],
            'opened'    => $data_arrays['opened'],
            'sent'      => $data_arrays['sent'],
            'bounced'   => $data_arrays['bounced']
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
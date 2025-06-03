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
     * OPTIMIZED: Get dashboard stats with better performance
     */
    public function get_dashboard_stats($timeframe = 7) {
        global $DB;
    
        $timestart = time() - ($timeframe * DAYSECS);
    
        try {
            // OPTIMIZATION: Use more efficient query with indexing
            $sql = "SELECT status, COUNT(*) as count 
                    FROM {local_sesdashboard_mail} 
                    WHERE timecreated >= ? 
                    GROUP BY status 
                    ORDER BY status";
            $stats = $DB->get_records_sql($sql, [$timestart]);
            
            return $stats;
            
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * OPTIMIZED: Get daily stats with better memory usage
     */
    public function get_daily_stats($days = 7) {
        global $DB;
        
        $timestart = strtotime(date('Y-m-d 00:00:00')) - ($days - 1) * 86400;
        
        // Pre-generate all dates
        $dates_array = [];
        $data_arrays = ['delivered' => [], 'opened' => [], 'sent' => [], 'bounced' => []];
        
        for ($i = 0; $i < $days; $i++) {
            $date = date('Y-m-d', $timestart + ($i * 86400));
            $dates_array[] = $date;
            foreach ($data_arrays as $key => &$array) {
                $array[] = 0;
            }
        }
        
        // OPTIMIZATION: Single query instead of processing all records in PHP
        $sql = "SELECT DATE(FROM_UNIXTIME(timecreated)) as date_str, status, COUNT(*) as count
                FROM {local_sesdashboard_mail} 
                WHERE timecreated >= ? 
                GROUP BY DATE(FROM_UNIXTIME(timecreated)), status
                ORDER BY date_str ASC";
        
        try {
            $results = $DB->get_records_sql($sql, [$timestart]);
            
            foreach ($results as $result) {
                $date_index = array_search($result->date_str, $dates_array);
                if ($date_index !== false) {
                    $status = trim($result->status);
                    switch ($status) {
                        case 'Delivery':
                            $data_arrays['delivered'][$date_index] = (int)$result->count;
                            break;
                        case 'Open':
                            $data_arrays['opened'][$date_index] = (int)$result->count;
                            break;
                        case 'Send':
                            $data_arrays['sent'][$date_index] = (int)$result->count;
                            break;
                        case 'Bounce':
                            $data_arrays['bounced'][$date_index] = (int)$result->count;
                            break;
                    }
                }
            }
            
        } catch (Exception $e) {
            // Fallback to PHP processing if SQL fails
            $all_records = $DB->get_records_sql(
                "SELECT timecreated, status FROM {local_sesdashboard_mail} WHERE timecreated >= ?", 
                [$timestart]
            );
            
            foreach ($all_records as $record) {
                $date = date('Y-m-d', $record->timecreated);
                $date_index = array_search($date, $dates_array);
                if ($date_index !== false) {
                    $status = trim($record->status);
                    switch ($status) {
                        case 'Delivery':
                            $data_arrays['delivered'][$date_index]++;
                            break;
                        case 'Open':
                            $data_arrays['opened'][$date_index]++;
                            break;
                        case 'Send':
                            $data_arrays['sent'][$date_index]++;
                            break;
                        case 'Bounce':
                            $data_arrays['bounced'][$date_index]++;
                            break;
                    }
                }
            }
        }
        
        return [
            'dates' => $dates_array,
            'delivered' => $data_arrays['delivered'],
            'opened' => $data_arrays['opened'],
            'sent' => $data_arrays['sent'],
            'bounced' => $data_arrays['bounced']
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
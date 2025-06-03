<?php
namespace local_sesdashboard\util;

defined('MOODLE_INTERNAL') || die();

class logger {
    private static $logfile = null;
    private static $max_log_size = 10485760; // 10MB
    private static $max_log_files = 5;
    
    public static function init() {
        global $CFG;
        if (self::$logfile === null) {
            $logdir = $CFG->dataroot . '/sesdashboard_logs';
            if (!is_dir($logdir)) {
                // Create directory with web server permissions
                if (!mkdir($logdir, 0755, true)) {
                    debugging('Failed to create log directory: ' . $logdir, DEBUG_DEVELOPER);
                    return false;
                }
                // Ensure web server can write to directory
                chmod($logdir, 0755);
            }
            self::$logfile = $logdir . '/sesdashboard_' . date('Y-m-d') . '.log';
            
            // Check for log rotation
            self::rotate_logs_if_needed();
            
            // Ensure log file exists and is writable
            if (!file_exists(self::$logfile)) {
                touch(self::$logfile);
                chmod(self::$logfile, 0644);
            }
        }
        return true;
    }
    
    private static function rotate_logs_if_needed() {
        if (file_exists(self::$logfile) && filesize(self::$logfile) > self::$max_log_size) {
            $logdir = dirname(self::$logfile);
            $basename = basename(self::$logfile, '.log');
            
            // Rotate existing logs
            for ($i = self::$max_log_files - 1; $i > 0; $i--) {
                $old_file = $logdir . '/' . $basename . '.' . $i . '.log';
                $new_file = $logdir . '/' . $basename . '.' . ($i + 1) . '.log';
                if (file_exists($old_file)) {
                    rename($old_file, $new_file);
                }
            }
            
            // Move current log to .1
            rename(self::$logfile, $logdir . '/' . $basename . '.1.log');
            
            // Clean up old logs beyond max_log_files
            $cleanup_file = $logdir . '/' . $basename . '.' . (self::$max_log_files + 1) . '.log';
            if (file_exists($cleanup_file)) {
                unlink($cleanup_file);
            }
        }
    }
    
    public static function log($message, $level = 'INFO', $context = []) {
        if (!self::init()) {
            debugging('Failed to initialize logger', DEBUG_DEVELOPER);
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $memory_usage = round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB';
        
        // Add context information if provided
        $context_str = '';
        if (!empty($context)) {
            $context_str = ' | Context: ' . json_encode($context);
        }
        
        $logentry = sprintf("[%s] [%s] [Memory: %s] %s%s\n", 
            $timestamp, $level, $memory_usage, $message, $context_str);
            
        if (!file_put_contents(self::$logfile, $logentry, FILE_APPEND | LOCK_EX)) {
            debugging('Failed to write to log file: ' . self::$logfile, DEBUG_DEVELOPER);
        }
    }
    
    public static function error($message, $context = []) {
        self::log($message, 'ERROR', $context);
        // Also log to Moodle debugging in development
        if (debugging('', DEBUG_DEVELOPER)) {
            debugging('SES Dashboard Error: ' . $message, DEBUG_DEVELOPER);
        }
    }
    
    public static function info($message, $context = []) {
        self::log($message, 'INFO', $context);
    }
    
    public static function debug($message, $context = []) {
        self::log($message, 'DEBUG', $context);
    }
    
    public static function warning($message, $context = []) {
        self::log($message, 'WARNING', $context);
    }
    
    public static function performance($message, $start_time = null, $context = []) {
        if ($start_time !== null) {
            $execution_time = round((microtime(true) - $start_time) * 1000, 2);
            $message .= " | Execution time: {$execution_time}ms";
        }
        self::log($message, 'PERFORMANCE', $context);
    }
    
    /**
     * Get recent log entries for admin dashboard
     */
    public static function get_recent_logs($lines = 50) {
        if (!self::init()) {
            return [];
        }
        
        if (!file_exists(self::$logfile)) {
            return [];
        }
        
        $file_lines = file(self::$logfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return array_slice($file_lines, -$lines);
    }
    
    /**
     * Clean up old log files
     */
    public static function cleanup_old_logs($days = 30) {
        global $CFG;
        $logdir = $CFG->dataroot . '/sesdashboard_logs';
        
        if (!is_dir($logdir)) {
            return 0;
        }
        
        $cutoff_time = time() - ($days * 86400);
        $deleted = 0;
        
        $files = glob($logdir . '/*.log');
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_time) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }
        
        return $deleted;
    }
}
<?php
namespace local_sesdashboard\util;

defined('MOODLE_INTERNAL') || die();

class logger {
    private static $logfile = null;
    
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
            
            // Ensure log file exists and is writable
            if (!file_exists(self::$logfile)) {
                touch(self::$logfile);
                chmod(self::$logfile, 0644);
            }
        }
        return true;
    }
    
    public static function log($message, $level = 'INFO') {
        if (!self::init()) {
            debugging('Failed to initialize logger', DEBUG_DEVELOPER);
            return;
        }
        $timestamp = date('Y-m-d H:i:s');
        $logentry = sprintf("[%s] [%s] %s\n", $timestamp, $level, $message);
        if (!file_put_contents(self::$logfile, $logentry, FILE_APPEND)) {
            debugging('Failed to write to log file: ' . self::$logfile, DEBUG_DEVELOPER);
        }
    }
    
    public static function error($message) {
        self::log($message, 'ERROR');
    }
    
    public static function info($message) {
        self::log($message, 'INFO');
    }
    
    public static function debug($message) {
        self::log($message, 'DEBUG');
    }
}
<?php
namespace local_sesdashboard;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

class external extends \external_api {
    /**
     * Returns description of get_stats parameters
     */
    public static function get_stats_parameters() {
        return new \external_function_parameters([
            'timeframe' => new \external_value(PARAM_INT, 'Time period in days', VALUE_DEFAULT, 7)
        ]);
    }

    /**
     * Returns description of get_stats return values
     */
    public static function get_stats_returns() {
        return new \external_single_structure([
            'total' => new \external_value(PARAM_INT, 'Total emails'),
            'delivered' => new \external_value(PARAM_INT, 'Delivered emails'),
            'bounced' => new \external_value(PARAM_INT, 'Bounced emails'),
            'complaint' => new \external_value(PARAM_INT, 'Complaint emails'),
            'daily_stats' => new \external_multiple_structure(
                new \external_single_structure([
                    'date' => new \external_value(PARAM_TEXT, 'Date'),
                    'count' => new \external_value(PARAM_INT, 'Email count')
                ])
            )
        ]);
    }

    /**
     * Get email statistics
     */
    public static function get_stats($timeframe = 7) {
        $params = self::validate_parameters(self::get_stats_parameters(), ['timeframe' => $timeframe]);
        $repository = new \local_sesdashboard\repositories\email_repository();
        return $repository->get_dashboard_stats($params['timeframe']);
    }

    /**
     * Returns description of get_email_details parameters
     */
    public static function get_email_details_parameters() {
        return new \external_function_parameters([
            'id' => new \external_value(PARAM_INT, 'Email ID')
        ]);
    }

    /**
     * Returns description of get_email_details return values
     */
    public static function get_email_details_returns() {
        return new \external_single_structure([
            'id' => new \external_value(PARAM_INT, 'Email ID'),
            'userid' => new \external_value(PARAM_INT, 'User ID'),
            'email' => new \external_value(PARAM_TEXT, 'Email address'),
            'subject' => new \external_value(PARAM_TEXT, 'Email subject'),
            'status' => new \external_value(PARAM_TEXT, 'Email status'),
            'messageid' => new \external_value(PARAM_TEXT, 'Message ID'),
            'eventtype' => new \external_value(PARAM_TEXT, 'Event type'),
            'timecreated' => new \external_value(PARAM_INT, 'Time created'),
            'timemodified' => new \external_value(PARAM_INT, 'Time modified')
        ]);
    }

    /**
     * Get email details
     */
    public static function get_email_details($id) {
        $params = self::validate_parameters(self::get_email_details_parameters(), ['id' => $id]);
        
        $repository = new \local_sesdashboard\repositories\email_repository();
        $email = $repository->get_email_details($params['id']);
        
        if (!$email) {
            throw new \moodle_exception('emailnotfound', 'local_sesdashboard');
        }
        
        return $email;
    }
}
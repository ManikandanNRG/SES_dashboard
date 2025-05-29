<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_sesdashboard_get_stats' => [
        'classname' => 'local_sesdashboard\\external',
        'methodname' => 'get_stats',
        'description' => 'Get email statistics for dashboard',
        'type' => 'read',
        'ajax' => true
    ],
    'local_sesdashboard_get_email_details' => [
        'classname' => 'local_sesdashboard\\external',
        'methodname' => 'get_email_details',
        'classpath' => '',
        'description' => 'Get email details',
        'type' => 'read',
        'ajax' => true
    ]
];
<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/sesdashboard:view' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
            'admin' => CAP_ALLOW
        ]
    ]
];
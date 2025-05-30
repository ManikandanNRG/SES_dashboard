<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sesdashboard';
$plugin->version = 2024120505;        // Plugin version - added log file cleanup
$plugin->requires = 2022112801;        // Moodle 4.2 minimum (includes core_chart support)
$plugin->maturity = MATURITY_STABLE;    // STABLE for production use
$plugin->release = 'v1.2.2';           // Human readable version number

// No dependencies needed - core_chart is part of Moodle core since 3.2


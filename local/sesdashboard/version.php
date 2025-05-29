<?php
defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2024060101;        // Plugin version
$plugin->requires  = 2022041900;        // Moodle 4.2 minimum (includes core_chart support)
$plugin->component = 'local_sesdashboard';
$plugin->maturity  = MATURITY_STABLE;    // STABLE for production use
$plugin->release   = '1.2.0';           // Semantic versioning

// No dependencies needed - core_chart is part of Moodle core since 3.2


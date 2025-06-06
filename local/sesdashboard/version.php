<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_sesdashboard';
$plugin->version = 2024120507;        // Plugin version - reverted to original chart implementation
$plugin->requires = 2022112801;        // Moodle 4.2 minimum (includes core_chart support)
$plugin->maturity = MATURITY_STABLE;    // STABLE for production use
$plugin->release = 'v1.2.4';           // Human readable version number

// Core chart API is part of Moodle core since 3.2


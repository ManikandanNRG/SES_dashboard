<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_sesdashboard', get_string('pluginname', 'local_sesdashboard'));
    $settings->add(new admin_setting_configtext('local_sesdashboard/senderemail',
        get_string('senderemail', 'local_sesdashboard'),
        get_string('senderemail_desc', 'local_sesdashboard'), '', PARAM_EMAIL));
    $settings->add(new admin_setting_configselect('local_sesdashboard/retention',
        get_string('retention', 'local_sesdashboard'),
        get_string('retention_desc', 'local_sesdashboard'), 14, [7 => '7 days', 14 => '14 days', 28 => '28 days']));
    $settings->add(new admin_setting_configtext('local_sesdashboard/snsarn',
        get_string('snsarn', 'local_sesdashboard'),
        get_string('snsarn_desc', 'local_sesdashboard'), '', PARAM_TEXT));
    $ADMIN->add('localplugins', $settings);
}
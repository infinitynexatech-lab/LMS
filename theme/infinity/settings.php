<?php
defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings = new theme_boost_admin_settingspage_tabs('themesettinginfinity', get_string('configtitle', 'theme_infinity'));

    $page = new admin_settingpage('theme_infinity_advanced', get_string('advancedsettings', 'theme_infinity'));
    $page->add(new admin_setting_configtextarea(
        'theme_infinity/scss',
        get_string('rawscss', 'theme_infinity'),
        get_string('rawscss_desc', 'theme_infinity'),
        '',
        PARAM_RAW
    ));
    $settings->add($page);
}

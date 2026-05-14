<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Runs once when local_infinity is installed (i.e. on Moodle's first boot
 * with this plugin mounted). Equivalent to the CRM's provision/settings overlay.
 */
function xmldb_local_infinity_install() {
    global $DB;

    set_config('theme', 'infinity');
    set_config('themedesignermode', 0);

    $site = $DB->get_record('course', ['id' => SITEID]);
    if ($site) {
        $site->fullname  = 'Infinity Learn';
        $site->shortname = 'Infinity Learn';
        $site->summary   = 'Corporate, customer, partner, and academic training — all in one place.';
        $site->summaryformat = FORMAT_HTML;
        $DB->update_record('course', $site);
    }

    set_config('supportname',  'Infinity Learn Support');
    set_config('supportemail', 'admin@infinitynexatech.com');

    return true;
}

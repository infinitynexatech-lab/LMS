<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Runs once when local_infinity is installed (Moodle's first boot with this
 * plugin mounted). Equivalent to Corteza's provision/settings overlay.
 */
function xmldb_local_infinity_install() {
    global $DB, $CFG;

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

    local_infinity_install_brand_file('logo.svg', 'logo');

    return true;
}

/**
 * Copy a brand asset from local/infinity/pix/<source> into Moodle's file
 * storage so it appears under Site administration → Appearance → Logos.
 */
function local_infinity_install_brand_file($source, $filearea) {
    global $CFG;
    require_once($CFG->libdir . '/filelib.php');

    $sourcepath = __DIR__ . '/../pix/' . $source;
    if (!is_readable($sourcepath)) {
        return;
    }

    $fs = get_file_storage();
    $ctx = context_system::instance();

    $fs->delete_area_files($ctx->id, 'core_admin', $filearea, 0);

    $record = (object) [
        'contextid' => $ctx->id,
        'component' => 'core_admin',
        'filearea'  => $filearea,
        'itemid'    => 0,
        'filepath'  => '/',
        'filename'  => $source,
    ];
    $fs->create_file_from_pathname($record, $sourcepath);
}

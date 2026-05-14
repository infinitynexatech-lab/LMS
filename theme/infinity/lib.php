<?php
defined('MOODLE_INTERNAL') || die();

function theme_infinity_get_main_scss_content($theme) {
    global $CFG;
    $boostpreset = $CFG->dirroot . '/theme/boost/scss/preset/default.scss';
    $scss = is_readable($boostpreset) ? file_get_contents($boostpreset) : '';
    $post = __DIR__ . '/scss/post.scss';
    if (is_readable($post)) {
        $scss .= "\n" . file_get_contents($post);
    }
    return $scss;
}

function theme_infinity_get_pre_scss($theme) {
    $brand = [
        'primary'   => '#6366F1',
        'secondary' => '#64748B',
        'success'   => '#10B981',
        'warning'   => '#F59E0B',
        'danger'    => '#EF4444',
        'body-bg'   => '#F8FAFC',
    ];
    $scss = '';
    foreach ($brand as $name => $value) {
        $scss .= "\${$name}: {$value};\n";
    }
    return $scss;
}

function theme_infinity_get_extra_scss($theme) {
    return $theme->settings->scss ?? '';
}

<?php
defined('MOODLE_INTERNAL') || die();

$THEME->name           = 'infinity';
$THEME->sheets         = [];
$THEME->editor_sheets  = [];
$THEME->parents        = ['boost'];
$THEME->enable_dock    = false;
$THEME->yuicssmodules  = [];
$THEME->rendererfactory = 'theme_overridden_renderer_factory';
$THEME->usefallback    = true;

$THEME->scss            = function ($theme) { return theme_infinity_get_main_scss_content($theme); };
$THEME->prescsscallback = 'theme_infinity_get_pre_scss';
$THEME->extrascsscallback = 'theme_infinity_get_extra_scss';

$THEME->iconsystem      = '\core\output\icon_system_fontawesome';
$THEME->haseditswitch   = true;
$THEME->usescourseindex = true;
$THEME->haslogincover   = false;

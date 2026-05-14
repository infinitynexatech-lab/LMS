<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component   = 'theme_infinity';
$plugin->version     = 2026051400;
$plugin->release     = '0.1.0';
$plugin->requires    = 2024100700; // Moodle 4.5
$plugin->maturity    = MATURITY_ALPHA;
$plugin->dependencies = [
    'theme_boost' => 2024100700,
];

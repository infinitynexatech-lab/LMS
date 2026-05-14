<?php
// Run from inside the Moodle container:
//   docker compose cp seed/categories.php moodle:/tmp/categories.php
//   docker compose exec -T moodle php /tmp/categories.php

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');

$categories = [
    ['name' => 'Corporate Training', 'description' => 'Employee onboarding, compliance, and internal upskilling.'],
    ['name' => 'Customer Training',  'description' => 'Product certification and onboarding for B2B customers.'],
    ['name' => 'Partner Enablement', 'description' => 'Channel-partner courses, NDA-gated curriculum.'],
    ['name' => 'Public Catalog',     'description' => 'Self-paced paid courses, open self-enrolment.'],
    ['name' => 'Academic',           'description' => 'Term-based, instructor-led courses with full gradebook.'],
];

foreach ($categories as $cat) {
    $existing = $DB->get_record('course_categories', ['name' => $cat['name']]);
    if ($existing) {
        echo "= '{$cat['name']}' already exists (id={$existing->id})\n";
        continue;
    }
    $data = (object) [
        'name'              => $cat['name'],
        'description'       => $cat['description'],
        'descriptionformat' => FORMAT_HTML,
        'parent'            => 0,
    ];
    $new = core_course_category::create($data);
    echo "+ created '{$cat['name']}' (id={$new->id})\n";
}

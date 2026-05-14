<?php
// Run from inside the Moodle container:
//   docker compose cp seed/courses.php moodle:/tmp/courses.php
//   docker compose exec -T moodle php /tmp/courses.php

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->dirroot . '/course/lib.php');

$courses = [
    ['cat' => 'Corporate Training', 'shortname' => 'welcome',  'fullname' => 'Welcome to Infinity Learn',     'summary' => '<p>Quick orientation for new joiners.</p>'],
    ['cat' => 'Corporate Training', 'shortname' => 'gdpr',     'fullname' => 'GDPR Compliance (SCORM)',       'summary' => '<p>GDPR essentials, delivered via a SCORM 1.2 package.</p>'],
    ['cat' => 'Customer Training',  'shortname' => 'product',  'fullname' => 'Product Certification',         'summary' => '<p>Become a Certified Infinity Nexatech Product Specialist.</p>'],
    ['cat' => 'Partner Enablement', 'shortname' => 'channel',  'fullname' => 'Channel Sales Playbook',        'summary' => '<p>Partner-only sales enablement curriculum.</p>'],
    ['cat' => 'Public Catalog',     'shortname' => 'excel',    'fullname' => 'Advanced Excel (Paid Demo)',    'summary' => '<p>PayPal-enrolled paid demo course.</p>'],
    ['cat' => 'Public Catalog',     'shortname' => 'live-q1',  'fullname' => 'Live: Q1 All-Hands',            'summary' => '<p>Live virtual classroom via Jitsi.</p>'],
    ['cat' => 'Academic',           'shortname' => 'stats101', 'fullname' => 'Intro to Statistics',           'summary' => '<p>Term-based academic course with weekly assignments.</p>'],
];

foreach ($courses as $c) {
    $cat = $DB->get_record('course_categories', ['name' => $c['cat']]);
    if (!$cat) {
        echo "! category '{$c['cat']}' missing — run categories.php first\n";
        continue;
    }
    $existing = $DB->get_record('course', ['shortname' => $c['shortname']]);
    if ($existing) {
        echo "= '{$c['shortname']}' already exists\n";
        continue;
    }
    $data = (object) [
        'category'      => $cat->id,
        'fullname'      => $c['fullname'],
        'shortname'     => $c['shortname'],
        'summary'       => $c['summary'],
        'summaryformat' => FORMAT_HTML,
        'format'        => 'topics',
        'numsections'   => 4,
        'visible'       => 1,
        'startdate'     => time(),
    ];
    $new = create_course($data);
    echo "+ created '{$c['shortname']}' (id={$new->id}) in '{$c['cat']}'\n";
}

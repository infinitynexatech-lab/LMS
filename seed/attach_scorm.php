<?php
// Attach the seed/packages/gdpr-demo.zip SCORM package as an activity in the
// 'gdpr' course (created by courses.php).
//
// Run from inside the Moodle container:
//   docker compose cp seed/packages/gdpr-demo.zip moodle:/tmp/gdpr-demo.zip
//   docker compose cp seed/attach_scorm.php       moodle:/tmp/attach_scorm.php
//   docker compose exec -T moodle php /tmp/attach_scorm.php

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/mod/scorm/lib.php');
require_once($CFG->dirroot . '/mod/scorm/locallib.php');

$ZIP_PATH      = '/tmp/gdpr-demo.zip';
$COURSE_SHORT  = 'gdpr';
$ACTIVITY_NAME = 'GDPR Essentials (SCORM 1.2)';

if (!is_readable($ZIP_PATH)) {
    fwrite(STDERR, "! zip not found at {$ZIP_PATH} — did you docker compose cp it in?\n");
    exit(1);
}

$course = $DB->get_record('course', ['shortname' => $COURSE_SHORT]);
if (!$course) {
    fwrite(STDERR, "! course '{$COURSE_SHORT}' not found — run courses.php first\n");
    exit(1);
}

// Skip if already attached.
$existing = $DB->get_record_sql("
    SELECT s.* FROM {scorm} s WHERE s.course = ? AND s.name = ?
", [$course->id, $ACTIVITY_NAME]);
if ($existing) {
    echo "= '{$ACTIVITY_NAME}' already attached to '{$COURSE_SHORT}' (scorm id={$existing->id})\n";
    exit(0);
}

$module = $DB->get_record('modules', ['name' => 'scorm'], '*', MUST_EXIST);
$admin  = $DB->get_record('user',    ['username' => 'admin'], '*', MUST_EXIST);

// Put the zip into admin's draft file area — add_moduleinfo() will copy it
// from there into the module's permanent package filearea.
\core\session\manager::set_user($admin);
$usercontext = context_user::instance($admin->id);
$fs = get_file_storage();
$draftitemid = file_get_unused_draft_itemid();
$fs->create_file_from_pathname(
    (object) [
        'contextid' => $usercontext->id,
        'component' => 'user',
        'filearea'  => 'draft',
        'itemid'    => $draftitemid,
        'filepath'  => '/',
        'filename'  => 'gdpr-demo.zip',
    ],
    $ZIP_PATH
);

$modinfo = (object) [
    'course'                 => $course->id,
    'modulename'             => 'scorm',
    'module'                 => $module->id,
    'section'                => 0,
    'visible'                => 1,
    'visibleoncoursepage'    => 1,
    'name'                   => $ACTIVITY_NAME,
    'intro'                  => '<p>Sample SCORM 1.2 package — three slides + a Mark Complete button.</p>',
    'introformat'            => FORMAT_HTML,
    'scormtype'              => SCORM_TYPE_LOCAL,
    'packagefile'            => $draftitemid,
    'reference'              => '',
    'updatefreq'             => SCORM_UPDATE_NEVER,
    'popup'                  => 0,
    'width'                  => 100,
    'height'                 => 500,
    'skipview'               => 0,
    'hidebrowse'             => 0,
    'hidetoc'                => 1,
    'displaycoursestructure' => 0,
    'hidenav'                => 1,
    'maxgrade'               => 100,
    'grademethod'            => GRADEHIGHEST,
    'whatgrade'              => HIGHESTATTEMPT,
    'maxattempt'             => 0,
    'forcecompleted'         => 0,
    'forcenewattempt'        => 0,
    'lastattemptlock'        => 0,
    'masteryoverride'        => 1,
    'displayattemptstatus'   => 1,
    'autocommit'             => 0,
    'auto'                   => 0,
    'completion'             => 0,
    'completionview'         => 0,
    'completionexpected'     => 0,
    'cmidnumber'             => '',
    'groupmode'              => 0,
    'groupingid'             => 0,
    'tags'                   => [],
    'availabilityconditionsjson' => null,
    'lang'                   => null,
];

$modinfo = add_moduleinfo($modinfo, $course);

echo "+ attached SCORM activity to course '{$COURSE_SHORT}':\n";
echo "    course module id : {$modinfo->coursemodule}\n";
echo "    scorm instance id: {$modinfo->instance}\n";

// Force-parse the imsmanifest to populate mdl_scorm_scoes — add_moduleinfo
// should do this, but it's also safe to re-run.
$scorm = $DB->get_record('scorm', ['id' => $modinfo->instance], '*', MUST_EXIST);
$cm    = get_coursemodule_from_instance('scorm', $scorm->id, $course->id, false, MUST_EXIST);
scorm_parse($scorm, true);
$scoes = $DB->count_records('scorm_scoes', ['scorm' => $scorm->id]);
echo "    scoes parsed     : {$scoes}\n";

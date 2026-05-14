<?php
// Run from inside the Moodle container:
//   docker compose cp seed/roles.php moodle:/tmp/roles.php
//   docker compose exec -T moodle php /tmp/roles.php

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/accesslib.php');

$roles = [
    ['shortname' => 'infinity_corporate_admin', 'name' => 'Infinity — Corporate Admin',  'archetype' => 'manager'],
    ['shortname' => 'infinity_customer_csm',    'name' => 'Infinity — Customer CSM',     'archetype' => 'coursecreator'],
    ['shortname' => 'infinity_partner_lead',    'name' => 'Infinity — Partner Lead',     'archetype' => 'editingteacher'],
    ['shortname' => 'infinity_paying_learner',  'name' => 'Infinity — Paying Learner',   'archetype' => 'student'],
];

foreach ($roles as $r) {
    $existing = $DB->get_record('role', ['shortname' => $r['shortname']]);
    if ($existing) {
        echo "= role '{$r['shortname']}' exists (id={$existing->id})\n";
        continue;
    }
    $roleid = create_role($r['name'], $r['shortname'], 'Custom Infinity Learn role.', $r['archetype']);

    $archetype = $DB->get_record('role', ['shortname' => $r['archetype']]);
    if ($archetype) {
        foreach ($DB->get_records('role_capabilities', ['roleid' => $archetype->id]) as $cap) {
            assign_capability($cap->capability, $cap->permission, $roleid, $cap->contextid);
        }
        set_role_contextlevels($roleid, get_role_contextlevels($archetype->id));
    }
    echo "+ created role '{$r['shortname']}' (id={$roleid})\n";
}

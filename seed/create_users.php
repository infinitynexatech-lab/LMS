<?php
// Bulk-create demo users via Moodle's user_create_user() API.
// Run from inside the Moodle container:
//   docker compose cp seed/create_users.php moodle:/tmp/create_users.php
//   docker compose exec -T moodle php /tmp/create_users.php

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->dirroot . '/user/lib.php');

$users = [
    ['username' => 'sarah',  'firstname' => 'Sarah',  'lastname' => 'Johnson', 'email' => 'sarah.johnson@infinitynexatech.com',  'role' => 'infinity_corporate_admin'],
    ['username' => 'miguel', 'firstname' => 'Miguel', 'lastname' => 'Reyes',   'email' => 'miguel.reyes@infinitynexatech.com',   'role' => 'infinity_customer_csm'],
    ['username' => 'priya',  'firstname' => 'Priya',  'lastname' => 'Sharma',  'email' => 'priya.sharma@infinitynexatech.com',   'role' => 'infinity_partner_lead'],
    ['username' => 'alex',   'firstname' => 'Alex',   'lastname' => 'Chen',    'email' => 'alex.chen@example.com',               'role' => 'infinity_paying_learner'],
];

$password = 'Strong@2026';

foreach ($users as $u) {
    if ($DB->record_exists('user', ['username' => $u['username']])) {
        echo "= user '{$u['username']}' already exists\n";
        $userid = $DB->get_field('user', 'id', ['username' => $u['username']]);
    } else {
        $user = (object) [
            'username'  => $u['username'],
            'password'  => $password,
            'firstname' => $u['firstname'],
            'lastname'  => $u['lastname'],
            'email'     => $u['email'],
            'auth'      => 'manual',
            'confirmed' => 1,
            'mnethostid'=> $CFG->mnet_localhost_id,
            'lang'      => 'en',
        ];
        $userid = user_create_user($user, true, false);
        echo "+ created user '{$u['username']}' (id={$userid})\n";
    }

    // Assign the requested role at SYSTEM context.
    $role = $DB->get_record('role', ['shortname' => $u['role']]);
    if (!$role) {
        echo "! role '{$u['role']}' not found — run roles.php first\n";
        continue;
    }
    $ctx = context_system::instance();
    if (!$DB->record_exists('role_assignments', ['userid' => $userid, 'roleid' => $role->id, 'contextid' => $ctx->id])) {
        role_assign($role->id, $userid, $ctx->id);
        echo "  · assigned role '{$u['role']}' to '{$u['username']}'\n";
    } else {
        echo "  · role '{$u['role']}' already assigned\n";
    }
}

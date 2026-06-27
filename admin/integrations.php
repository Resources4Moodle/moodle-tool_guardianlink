<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * ERP/API integration administration.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('tool_guardianlink_integrations');
$context = context_system::instance();
require_capability('tool/guardianlink:sync', $context);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('admin_integrations', 'tool_guardianlink'));
echo html_writer::tag('p', get_string('integrationintro', 'tool_guardianlink'));

$functions = [
    'tool_guardianlink_upsert_relationships',
    'tool_guardianlink_revoke_relationships',
    'tool_guardianlink_get_relationships',
    'tool_guardianlink_upsert_health_records',
    'tool_guardianlink_upsert_organisations',
    'tool_guardianlink_get_audit_events',
    'tool_guardianlink_get_my_learners',
];
$table = new html_table();
$table->head = ['Web service function', 'Purpose'];
foreach ($functions as $function) {
    $table->data[] = [$function, 'Declared in admin/tool/guardianlink/db/services.php'];
}
echo html_writer::table($table);

echo $OUTPUT->heading(get_string('syncjob', 'tool_guardianlink'), 3);
$jobs = $DB->get_records('tool_guardianlink_erpsync', null, 'finished DESC, id DESC', '*', 0, 50);
$jtable = new html_table();
$jtable->head = [
    'ID',
    get_string('sourcecode', 'tool_guardianlink'),
    'Entity',
    get_string('action', 'tool_guardianlink'),
    get_string('result', 'tool_guardianlink'),
    'Succeeded',
    'Failed',
    'Finished',
];
foreach ($jobs as $job) {
    $jtable->data[] = [
        (int)$job->id,
        s($job->sourcecode),
        s($job->entitytype),
        s($job->action),
        s($job->status),
        (int)$job->recordssucceeded,
        (int)$job->recordsfailed,
        userdate($job->finished),
    ];
}
if ($jobs) {
    echo html_writer::table($jtable);
} else {
    echo $OUTPUT->notification('No ERP/SIS synchronisation jobs have been logged yet.', 'info');
}
echo $OUTPUT->footer();

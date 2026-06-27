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
 * GuardianLink audit and reports page.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('tool_guardianlink_audit');
$context = context_system::instance();
require_capability('tool/guardianlink:viewaudit', $context);

$since = optional_param('since', 0, PARAM_INT);
$records = \tool_guardianlink\local\relationship_service::get_recent_audit(200, $since);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('admin_audit', 'tool_guardianlink'));
echo $OUTPUT->notification(
    'Audit logs identify the adult actor and the learner record accessed. This is the reason GuardianLink does not silently '
        . 'impersonate the learner.',
    'info'
);

$table = new html_table();
$table->head = [
    'ID',
    get_string('timecreated', 'tool_guardianlink'),
    'Actor',
    get_string('learnerid', 'tool_guardianlink'),
    get_string('course'),
    get_string('action', 'tool_guardianlink'),
    get_string('result', 'tool_guardianlink'),
    get_string('sourcecode', 'tool_guardianlink'),
];
foreach ($records as $record) {
    $table->data[] = [
        (int)$record->id,
        userdate($record->timecreated),
        (int)$record->actorid,
        (int)$record->childid,
        (int)$record->courseid,
        s($record->action),
        s($record->result),
        s($record->sourcecode),
    ];
}
echo html_writer::table($table);
echo $OUTPUT->footer();

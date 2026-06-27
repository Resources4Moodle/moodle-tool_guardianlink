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
 * Hostel, care-home, orphanage, welfare and tutoring organisation admin.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('tool_guardianlink_organisations');
$context = context_system::instance();
require_capability('tool/guardianlink:manageorganisations', $context);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('admin_organisations', 'tool_guardianlink'));
echo $OUTPUT->notification(
    'This registry includes hostels, boarding homes, orphanages, children\'s homes, troubled-home support organisations, '
        . 'welfare agencies, and approved tutoring organisations. The organisation can be outside the school; the person still '
        . 'needs a Moodle account and a scoped relationship grant.',
    'info'
);

$records = $DB->get_records('tool_guardianlink_org', null, 'timemodified DESC, name ASC', '*', 0, 100);
$table = new html_table();
$table->head = [
    'ID',
    get_string('orgtype', 'tool_guardianlink'),
    get_string('orgname', 'tool_guardianlink'),
    get_string('sourcecode', 'tool_guardianlink'),
    get_string('externalid', 'tool_guardianlink'),
    get_string('status', 'tool_guardianlink'),
];
foreach ($records as $record) {
    $table->data[] = [
        (int)$record->id,
        s($record->orgtype),
        s($record->name),
        s($record->sourcecode),
        s($record->externalid),
        s($record->status),
    ];
}
if ($records) {
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(
        'No organisations yet. Add them through the ERP/API integration or a later manual organisation form.',
        'info'
    );
}
echo $OUTPUT->footer();

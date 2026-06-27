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
 * Health and care summaries administration.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/admin/tool/guardianlink/classes/form/health_record_form.php');

admin_externalpage_setup('tool_guardianlink_health');
$context = context_system::instance();
require_capability('tool/guardianlink:managehealth', $context);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('admin_health', 'tool_guardianlink'));
echo $OUTPUT->notification(
    'Health and care data should be minimal, summary-level, visible only to roles that need it, and governed by a lawful basis, '
        . 'retention rule, and local safeguarding policy. Do not use this as a clinical records system.',
    'warning'
);

if (!get_config('tool_guardianlink', 'enablehealthrecords')) {
    echo $OUTPUT->notification(get_string('healthrecordsdisabled', 'tool_guardianlink'), 'info');
} else {
    $form = new \tool_guardianlink\form\health_record_form();
    if ($form->is_cancelled()) {
        redirect(new moodle_url('/admin/tool/guardianlink/admin/index.php'));
    } else if ($data = $form->get_data()) {
        require_sesskey();
        \tool_guardianlink\local\relationship_service::upsert_health_record($data, (int)$USER->id);
        redirect(
            new moodle_url('/admin/tool/guardianlink/admin/health.php'),
            get_string('healthcreated', 'tool_guardianlink')
        );
    }
    $form->display();
}

$records = $DB->get_records('tool_guardianlink_health', null, 'timemodified DESC, id DESC', '*', 0, 100);
$table = new html_table();
$table->head = [
    'ID',
    get_string('learnerid', 'tool_guardianlink'),
    get_string('healthtype', 'tool_guardianlink'),
    get_string('healthtitle', 'tool_guardianlink'),
    get_string('severity', 'tool_guardianlink'),
    get_string('visibility', 'tool_guardianlink'),
    get_string('status', 'tool_guardianlink'),
];
foreach ($records as $record) {
    $table->data[] = [
        (int)$record->id,
        (int)$record->childid,
        s($record->healthtype),
        s($record->title),
        s($record->severity),
        s($record->visibility),
        s($record->status),
    ];
}
if ($records) {
    echo html_writer::table($table);
}
echo $OUTPUT->footer();

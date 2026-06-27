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
 * Relationship role type administration.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('tool_guardianlink_roletypes');
$context = context_system::instance();
require_capability('tool/guardianlink:configureroles', $context);

if (optional_param('seed', 0, PARAM_BOOL)) {
    require_sesskey();
    \tool_guardianlink\local\relationship_service::ensure_default_relationship_types();
    redirect(
        new moodle_url('/admin/tool/guardianlink/admin/roletypes.php'),
        get_string('relationshiptypesseeded', 'tool_guardianlink')
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('admin_roletypes', 'tool_guardianlink'));
echo html_writer::tag(
    'p',
    'GuardianLink deliberately avoids a binary parent model. Role types cover legal parents, restricted parents, guardians, '
        . 'foster/kinship carers, hostel wardens, residential key workers, orphanage or children\'s home staff, welfare officers, '
        . 'tutors, mentors, sponsors, and other authorised adults.'
);
echo html_writer::link(
    new moodle_url('/admin/tool/guardianlink/admin/roletypes.php', ['seed' => 1, 'sesskey' => sesskey()]),
    get_string('seeddefaults', 'tool_guardianlink'),
    ['class' => 'btn btn-secondary mb-3']
);

$records = $DB->get_records('tool_guardianlink_reltype', null, 'sortorder ASC, name ASC');
$table = new html_table();
$table->head = [
    'ID',
    get_string('reltype', 'tool_guardianlink'),
    get_string('relcategory', 'tool_guardianlink'),
    get_string('accessprofile', 'tool_guardianlink'),
    get_string('legal', 'tool_guardianlink'),
    get_string('status', 'tool_guardianlink'),
];
foreach ($records as $record) {
    $table->data[] = [
        (int)$record->id,
        s($record->name) . ' (' . s($record->shortname) . ')',
        s($record->category),
        s($record->defaultprofile),
        $record->mayholdlegal ? get_string('yes') : get_string('no'),
        $record->active
            ? get_string('status_active', 'tool_guardianlink')
            : get_string('status_suspended', 'tool_guardianlink'),
    ];
}
echo html_writer::table($table);
echo $OUTPUT->footer();

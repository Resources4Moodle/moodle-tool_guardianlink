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
 * Tutor/helper request administration.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('tool_guardianlink_tutor_requests');
$context = context_system::instance();
require_capability('tool/guardianlink:approvetutors', $context);

$approve = optional_param('approve', 0, PARAM_INT);
if ($approve) {
    require_sesskey();
    \tool_guardianlink\local\relationship_service::approve_tutor_request(
        $approve,
        (int)$USER->id,
        'Approved from GuardianLink admin.'
    );
    redirect(
        new moodle_url('/admin/tool/guardianlink/admin/tutor_requests.php'),
        get_string('requestapproved', 'tool_guardianlink')
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('admin_tutor_requests', 'tool_guardianlink'));
$requests = $DB->get_records('tool_guardianlink_tutorreq', null, 'timemodified DESC, id DESC', '*', 0, 100);
$table = new html_table();
$table->head = [
    'ID',
    get_string('requester', 'tool_guardianlink'),
    get_string('tutorid', 'tool_guardianlink'),
    get_string('learnerid', 'tool_guardianlink'),
    get_string('courseids', 'tool_guardianlink'),
    get_string('status', 'tool_guardianlink'),
    get_string('approve', 'tool_guardianlink'),
];
foreach ($requests as $request) {
    $approveurl = '-';
    if ($request->status === \tool_guardianlink\local\relationship_service::STATUS_PENDING) {
        $approveurl = html_writer::link(
            new moodle_url(
                '/admin/tool/guardianlink/admin/tutor_requests.php',
                ['approve' => $request->id, 'sesskey' => sesskey()]
            ),
            get_string('approve', 'tool_guardianlink')
        );
    }
    $table->data[] = [
        (int)$request->id,
        (int)$request->requesterid,
        (int)$request->tutorid,
        (int)$request->childid,
        s($request->courseids),
        s($request->status),
        $approveurl,
    ];
}
echo html_writer::table($table);
echo $OUTPUT->footer();

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
 * Bulk message to authorised adults of an audience.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_guardianlink\local\bulk_message_service;

admin_externalpage_setup('tool_guardianlink_bulkmail');
$context = context_system::instance();
require_capability('tool/guardianlink:sendbulkmessages', $context);

$PAGE->set_url(new moodle_url('/admin/tool/guardianlink/admin/bulkmail.php'));

$form = new \tool_guardianlink\form\bulk_message_form();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('admin_bulkmail', 'tool_guardianlink'));
echo $OUTPUT->notification(get_string('bulkmailintro', 'tool_guardianlink'), 'info');

if ($form->is_cancelled()) {
    redirect(new moodle_url('/admin/tool/guardianlink/admin/index.php'));
} else if ($data = $form->get_data()) {
    require_sesskey();
    $criteria = bulk_message_service::normalise_criteria($data);
    $recipients = bulk_message_service::resolve_recipients($criteria);
    $count = count($recipients);

    if (!empty($data->send)) {
        if ($count === 0) {
            echo $OUTPUT->notification(get_string('bulknorecipients', 'tool_guardianlink'), 'warning');
        } else {
            $result = bulk_message_service::send_bulk_message((int)$USER->id, $criteria, $data->subject, $data->message);
            echo $OUTPUT->notification(get_string('bulksent', 'tool_guardianlink', (object)$result), 'success');
        }
    } else {
        // Preview: report the resolved audience size without revealing contact details.
        echo $OUTPUT->notification(get_string('bulkpreviewresult', 'tool_guardianlink', $count), 'info');
    }
    $form->display();
} else {
    $form->display();
}

echo $OUTPUT->footer();

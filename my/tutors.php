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
 * Self-service tutor/helper request page.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/admin/tool/guardianlink/classes/form/tutor_request_form.php');

$childid = optional_param('childid', 0, PARAM_INT);
require_login();
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/admin/tool/guardianlink/my/tutors.php', ['childid' => $childid]));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('tutorrequest', 'tool_guardianlink'));
$PAGE->set_heading(get_string('pluginname', 'tool_guardianlink'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('tutorrequest', 'tool_guardianlink'));

if (!get_config('tool_guardianlink', 'allowguardianproposetutor')) {
    echo $OUTPUT->notification(get_string('accessdenied', 'tool_guardianlink'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$form = new \tool_guardianlink\form\tutor_request_form();
$form->set_data(['childid' => $childid]);
if ($form->is_cancelled()) {
    redirect(new moodle_url('/admin/tool/guardianlink/my/admin.php'));
} else if ($data = $form->get_data()) {
    require_sesskey();
    \tool_guardianlink\local\relationship_service::create_tutor_request($data, (int)$USER->id);
    redirect(new moodle_url('/admin/tool/guardianlink/my/admin.php'), get_string('requestcreated', 'tool_guardianlink'));
}
$form->display();
echo $OUTPUT->footer();

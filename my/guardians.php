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
 * Parent self-service: nominate and review additional authorised adults for a learner.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/admin/tool/guardianlink/classes/form/add_guardian_form.php');

use tool_guardianlink\local\relationship_service;

$childid = required_param('childid', PARAM_INT);
require_login();

$context = context_system::instance();

// The acting user must hold an active, legal relationship to this learner to delegate.
$relationship = relationship_service::get_active_relationship((int)$USER->id, $childid);
if (!$relationship || empty($relationship->legal)) {
    throw new moodle_exception('accessdenied', 'tool_guardianlink');
}
$child = core_user::get_user($childid, '*', MUST_EXIST);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/admin/tool/guardianlink/my/guardians.php', ['childid' => $childid]));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('addguardian', 'tool_guardianlink'));
$PAGE->set_heading(get_string('pluginname', 'tool_guardianlink'));

$form = new \tool_guardianlink\form\add_guardian_form();
$form->set_data(['childid' => $childid]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/admin/tool/guardianlink/my/admin.php'));
} else if ($data = $form->get_data()) {
    require_sesskey();
    $data->learnerid = $childid;
    try {
        relationship_service::nominate_guardian($data, (int)$USER->id);
        redirect(
            new moodle_url('/admin/tool/guardianlink/my/guardians.php', ['childid' => $childid]),
            get_string('nominationcreated', 'tool_guardianlink'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } catch (\Throwable $e) {
        redirect(
            new moodle_url('/admin/tool/guardianlink/my/guardians.php', ['childid' => $childid]),
            $e->getMessage(),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('addguardian', 'tool_guardianlink') . ': ' . fullname($child));
echo $OUTPUT->notification(get_string('addguardianintro', 'tool_guardianlink'), 'info');

// Show only the nominations THIS user has made — never other adults' details (peer privacy).
global $DB;
$mine = $DB->get_records_select(
    'tool_guardianlink_rel',
    'childid = :childid AND createdby = :me AND authoritybasis = :basis',
    ['childid' => $childid, 'me' => (int)$USER->id, 'basis' => 'parent_nomination'],
    'timecreated DESC'
);
if ($mine) {
    $table = new html_table();
    $table->head = [get_string('relationship', 'tool_guardianlink'), get_string('status', 'tool_guardianlink'),
        get_string('authoritystatus', 'tool_guardianlink'), get_string('endtime', 'tool_guardianlink')];
    foreach ($mine as $rec) {
        $table->data[] = [
            s($rec->reltype),
            s($rec->status),
            s($rec->authoritystatus),
            !empty($rec->endtime) ? userdate($rec->endtime) : get_string('noexpiry', 'tool_guardianlink'),
        ];
    }
    echo $OUTPUT->heading(get_string('mynominations', 'tool_guardianlink'), 3);
    echo html_writer::table($table);
}

$form->display();
echo $OUTPUT->footer();

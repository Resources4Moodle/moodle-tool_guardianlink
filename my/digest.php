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
 * Authorised adult digest preferences for one learner.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/admin/tool/guardianlink/classes/form/digest_preference_form.php');

$childid = required_param('childid', PARAM_INT);

require_login();

if (!\tool_guardianlink\local\relationship_service::can_access_child((int)$USER->id, $childid, 0, 'overview')) {
    throw new moodle_exception('accessdenied', 'tool_guardianlink');
}

$child = core_user::get_user($childid, '*', MUST_EXIST);
$context = context_user::instance($childid);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/admin/tool/guardianlink/my/digest.php', ['childid' => $childid]));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('digestpreferences', 'tool_guardianlink'));
$PAGE->set_heading(get_string('pluginname', 'tool_guardianlink'));

$form = new \tool_guardianlink\form\digest_preference_form(null, ['childid' => $childid]);
$current = \tool_guardianlink\local\relationship_service::get_digest_preference((int)$USER->id, $childid);
$defaults = [
    'childid' => $childid,
    'enabled' => $current && $current->status === \tool_guardianlink\local\relationship_service::STATUS_ACTIVE ? 1 : 0,
    'frequency' => $current ? $current->frequency : 'weekly',
    'channels' => $current ? $current->channels : 'moodle',
    'includeoverdue' => $current ? (int)$current->includeoverdue : 1,
    'includegrades' => $current ? (int)$current->includegrades : 0,
    'includeattendance' => $current ? (int)$current->includeattendance : 0,
    'includehealth' => $current ? (int)$current->includehealth : 0,
];
$form->set_data($defaults);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/admin/tool/guardianlink/my/admin.php'));
} else if ($data = $form->get_data()) {
    require_sesskey();
    \tool_guardianlink\local\relationship_service::save_digest_preference((int)$USER->id, $childid, $data);
    redirect(new moodle_url('/admin/tool/guardianlink/my/admin.php'), get_string('digestpreferencesaved', 'tool_guardianlink'));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('digestpreferences', 'tool_guardianlink') . ': ' . fullname($child));
echo html_writer::tag('p', get_string('digestfooter', 'tool_guardianlink'));
if ($current) {
    $meta = [];
    $meta[] = get_string('digestlastsent', 'tool_guardianlink') . ': '
        . (!empty($current->lastsent) ? userdate($current->lastsent) : '-');
    $meta[] = get_string('digestnextsend', 'tool_guardianlink') . ': '
        . (!empty($current->nextsend) ? userdate($current->nextsend) : '-');
    echo html_writer::tag('p', implode(' | ', $meta));
}
$form->display();
echo $OUTPUT->footer();

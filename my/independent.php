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
 * Parent/guardian: allow a learner to use a course independently (unsupervised).
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');

use tool_guardianlink\local\relationship_service;
use tool_guardianlink\local\supervision_service;

$childid = required_param('childid', PARAM_INT);
require_login();
$context = context_system::instance();

// Only an authorised adult with an active relationship to the learner may manage this.
if (!relationship_service::get_active_relationship((int)$USER->id, $childid)) {
    throw new moodle_exception('accessdenied', 'tool_guardianlink');
}
$child = core_user::get_user($childid, '*', MUST_EXIST);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/admin/tool/guardianlink/my/independent.php', ['childid' => $childid]));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('independentaccess', 'tool_guardianlink'));
$PAGE->set_heading(get_string('pluginname', 'tool_guardianlink'));

// Record an acknowledgement (allow) or a revocation.
$setcourse = optional_param('setcourse', 0, PARAM_INT);
$allow = optional_param('allow', -1, PARAM_INT);
if ($setcourse && $allow >= 0 && confirm_sesskey()) {
    supervision_service::acknowledge((int)$USER->id, $childid, $setcourse, (bool)$allow, (int)$USER->id);
    redirect(
        $PAGE->url,
        get_string($allow ? 'independentallowed' : 'independentrevoked', 'tool_guardianlink'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('independentaccess', 'tool_guardianlink') . ': ' . fullname($child));
echo \tool_guardianlink\local\ui::help_link('independent');

if (!supervision_service::feature_enabled()) {
    echo $OUTPUT->notification(get_string('independentdisabled', 'tool_guardianlink'), 'info');
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->notification(get_string('independentintro', 'tool_guardianlink'), 'info');

$offered = supervision_service::offered_courses((int)$USER->id, $childid);
if (!$offered) {
    echo $OUTPUT->notification(get_string('independentnocourses', 'tool_guardianlink'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->attributes['class'] = 'generaltable';
$table->head = [get_string('course'), get_string('status', 'tool_guardianlink'), ''];
foreach ($offered as $courseid => $info) {
    $isallowed = ($info->status === 'allowed');
    $statuslabel = $isallowed
        ? html_writer::tag(
            'span',
            get_string('independentstatusallowed', 'tool_guardianlink'),
            ['class' => 'badge badge-success']
        )
        : html_writer::tag(
            'span',
            get_string('independentstatussupervised', 'tool_guardianlink'),
            ['class' => 'badge badge-secondary']
        );
    $toggleurl = new moodle_url($PAGE->url, [
        'setcourse' => $courseid,
        'allow' => $isallowed ? 0 : 1,
        'sesskey' => sesskey(),
    ]);
    $button = html_writer::link(
        $toggleurl,
        get_string($isallowed ? 'independentrevokebtn' : 'independentallowbtn', 'tool_guardianlink'),
        ['class' => 'btn ' . ($isallowed ? 'btn-outline-danger' : 'btn-primary') . ' btn-sm']
    );
    $table->data[] = [format_string($info->course->fullname), $statuslabel, $button];
}
echo html_writer::table($table);

echo $OUTPUT->footer();

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
 * Governed assisted access: a parent/caregiver works through a course AS the learner,.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use tool_guardianlink\local\relationship_service;
use tool_guardianlink\local\setup;

$childid = required_param('childid', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);

require_login();

// Never nest assisted sessions.
if (\core\session\manager::is_loggedinas()) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
}

$adultid = (int)$USER->id;
$context = context_course::instance($courseid);
$child = core_user::get_user($childid, '*', MUST_EXIST);
$course = get_course($courseid);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/admin/tool/guardianlink/assist.php', ['childid' => $childid, 'courseid' => $courseid]));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('assistedaccess', 'tool_guardianlink'));
$PAGE->set_heading(format_string($course->fullname));

// Fail-closed gate evaluation.
$status = relationship_service::assisted_access_status($adultid, $childid, $courseid);
if (empty($status['allowed'])) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('assistedaccess', 'tool_guardianlink'));
    echo $OUTPUT->notification($status['reason'], 'error');
    echo $OUTPUT->continue_button(new moodle_url('/admin/tool/guardianlink/child.php', ['id' => $childid]));
    echo $OUTPUT->footer();
    exit;
}

// Confirmation step (also our CSRF gate) so the adult knowingly starts the session.
if (!optional_param('confirm', 0, PARAM_BOOL) || !confirm_sesskey()) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('assistedaccess', 'tool_guardianlink'));
    echo $OUTPUT->notification(get_string(
        'assistedconfirm',
        'tool_guardianlink',
        (object)['learner' => fullname($child), 'course' => format_string($course->fullname)]
    ), 'info');
    $confirmurl = new moodle_url(
        '/admin/tool/guardianlink/assist.php',
        ['childid' => $childid, 'courseid' => $courseid, 'confirm' => 1, 'sesskey' => sesskey()]
    );
    echo $OUTPUT->single_button($confirmurl, get_string('assistedstart', 'tool_guardianlink'));
    echo $OUTPUT->footer();
    exit;
}

// Grant the adult inspector view on this course so core's login-as course-access check passes.
setup::set_course_view($adultid, $courseid, true);

// Audit BEFORE the switch, while $USER is still the adult.
relationship_service::trigger_event('assisted_session_started', $context, $childid, 0, ['courseid' => $courseid]);
relationship_service::log_access(
    $adultid,
    $childid,
    'assisted_session_started',
    $courseid,
    'assisted',
    0,
    ['courseid' => $courseid]
);

// Core login-as, fenced to this course (records real user, shows banner, fires user_loggedinas).
\core\session\manager::loginas($childid, $context);

// Flag the new (child) session as a GuardianLink assisted session so the enforcement hook
// can block assessed activities and apply the time cap.
global $SESSION;
$SESSION->tool_guardianlink_assisted = (object)[
    'childid' => $childid,
    'courseid' => $courseid,
    'realuserid' => $adultid,
    'started' => time(),
];

redirect(
    new moodle_url('/course/view.php', ['id' => $courseid]),
    get_string('assistedstarted', 'tool_guardianlink', fullname($child)),
    null,
    \core\output\notification::NOTIFY_INFO
);

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
 * GuardianLink learner dashboard.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/enrollib.php');

use tool_guardianlink\local\relationship_service;

$childid = required_param('id', PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);

require_login();

if (!\tool_guardianlink\local\relationship_service::can_access_child((int)$USER->id, $childid, $courseid, 'overview')) {
    throw new moodle_exception('accessdenied', 'tool_guardianlink');
}

$child = core_user::get_user($childid, '*', MUST_EXIST);
$context = context_user::instance($childid);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/admin/tool/guardianlink/child.php', ['id' => $childid, 'courseid' => $courseid]));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('childdashboard', 'tool_guardianlink') . ': ' . fullname($child));
$PAGE->set_heading(get_string('pluginname', 'tool_guardianlink'));

\tool_guardianlink\local\relationship_service::log_access((int)$USER->id, $childid, 'view_child_dashboard', $courseid);
// Strengthened logging: emit a standard Moodle event for the delegated report view.
// The actor is always recorded as themselves — GuardianLink provides no login-as / act-as path.
\tool_guardianlink\local\relationship_service::trigger_event(
    'learner_report_viewed',
    $context,
    $childid,
    0,
    ['courseid' => $courseid]
);

$relationship = relationship_service::get_active_relationship((int)$USER->id, $childid);
// The classroom view: every enrolled course this adult may see, expanding direct course scopes AND
// broad category/learner/site scopes into concrete course cards. Each feature cell below is
// independently re-checked via can_access_child(), so expired/revoked scopes never expose data.
$courses = $relationship ? relationship_service::visible_courses_for_adult((int)$USER->id, $childid) : [];
$health = \tool_guardianlink\local\relationship_service::get_health_records_for_adult((int)$USER->id, $childid);

echo $OUTPUT->header();
echo $OUTPUT->heading(fullname($child));
echo $OUTPUT->notification(get_string('relationshipwarning', 'tool_guardianlink'), 'info');

// Offer the independent-access acknowledgement page when the feature is enabled and courses are offered.
if (
    \tool_guardianlink\local\supervision_service::feature_enabled()
        && \tool_guardianlink\local\supervision_service::offered_courses((int)$USER->id, $childid)
) {
    echo html_writer::div(html_writer::link(
        new moodle_url('/admin/tool/guardianlink/my/independent.php', ['childid' => $childid]),
        get_string('independentaccessmanage', 'tool_guardianlink'),
        ['class' => 'btn btn-secondary']
    ), 'mb-3');
}

if ($relationship) {
    $summary = [
        get_string('relationship', 'tool_guardianlink') . ': ' . s($relationship->reltype),
        get_string('authoritystatus', 'tool_guardianlink') . ': ' . s($relationship->authoritystatus),
        get_string('confidentiality', 'tool_guardianlink') . ': ' . s($relationship->confidentiality),
    ];
    echo html_writer::tag('p', implode(' | ', $summary));
}

$reasonlabels = [
    'course' => get_string('accessreason_course', 'tool_guardianlink'),
    'category' => get_string('accessreason_category', 'tool_guardianlink'),
    'learner' => get_string('accessreason_learner', 'tool_guardianlink'),
    'site' => get_string('accessreason_site', 'tool_guardianlink'),
];
$table = new html_table();
$table->head = [
    get_string('course'),
    get_string('accessreasonhdr', 'tool_guardianlink'),
    get_string('progress', 'tool_guardianlink'),
    get_string('allowgrades', 'tool_guardianlink'),
    get_string('overdue', 'tool_guardianlink'),
    get_string('allowattendance', 'tool_guardianlink'),
    get_string('teachers', 'tool_guardianlink'),
];
foreach ($courses as $course) {
    $cid = (int)$course->id;
    // Re-check every feature against the live invariant (verified + active + in-date scope) for
    // THIS course, so broad scopes expand safely and stale scopes never expose data.
    $cangrades = relationship_service::can_access_child((int)$USER->id, $childid, $cid, 'grades');
    $canattendance = relationship_service::can_access_child((int)$USER->id, $childid, $cid, 'attendance');
    $canteacher = relationship_service::can_access_child((int)$USER->id, $childid, $cid, 'teachercontact')
        && relationship_service::course_allows_teacher_proxy($cid);
    $actions = [];
    if ($canteacher) {
        $actions[] = html_writer::link(
            new moodle_url('/admin/tool/guardianlink/teachers.php', ['childid' => $childid, 'courseid' => $cid]),
            get_string('teachers', 'tool_guardianlink')
        );
    }
    // Governed assisted access: only offered when every gate currently passes.
    if (
        relationship_service::can_access_child((int)$USER->id, $childid, $cid, 'assisted')
            && relationship_service::assisted_access_status((int)$USER->id, $childid, $cid)['allowed']
    ) {
        $actions[] = html_writer::link(
            new moodle_url('/admin/tool/guardianlink/assist.php', ['childid' => $childid, 'courseid' => $cid]),
            get_string('assistedstart', 'tool_guardianlink')
        );
    }
    $contact = $actions ? implode(' | ', $actions) : '-';
    // Real progress data (read-only), honouring the re-checked grades permission.
    $progress = \tool_guardianlink\local\progress_service::course_progress($cid, $childid, $cangrades);
    $progresscell = $progress->completionpercent === null
        ? get_string('nocompletion', 'tool_guardianlink')
        : $progress->completionpercent . '% (' . $progress->completed . '/' . $progress->total . ')';
    $gradecell = $cangrades
        ? ($progress->coursegrade !== null ? s($progress->coursegrade) : '-')
        : get_string('notpermitted', 'tool_guardianlink');
    $overduecell = $progress->overdue > 0
        ? html_writer::tag('strong', $progress->overdue, ['class' => 'text-danger'])
        : '0';
    $reason = relationship_service::access_reason_for_course((int)$USER->id, $childid, $cid);
    $reasoncell = $reasonlabels[$reason] ?? get_string('accessreason_unknown', 'tool_guardianlink');
    $coursecell = format_string($course->fullname);
    if (!empty($course->shortname)) {
        $coursecell .= html_writer::tag('div', s($course->shortname), ['class' => 'small text-muted']);
    }
    $table->data[] = [
        $coursecell,
        $reasoncell,
        $progresscell,
        $gradecell,
        $overduecell,
        $canattendance ? get_string('yes') : get_string('no'),
        $contact,
    ];
}

if (empty($table->data)) {
    echo $OUTPUT->notification(get_string('nocoursesvisible', 'tool_guardianlink'), 'info');
} else {
    echo html_writer::table($table);
}

if (!empty($health)) {
    echo $OUTPUT->heading(get_string('healthrecords', 'tool_guardianlink'), 3);
    $htable = new html_table();
    $htable->head = [
        get_string('healthtype', 'tool_guardianlink'),
        get_string('healthtitle', 'tool_guardianlink'),
        get_string('severity', 'tool_guardianlink'),
        get_string('visibility', 'tool_guardianlink'),
    ];
    foreach ($health as $record) {
        $htable->data[] = [s($record->healthtype), s($record->title), s($record->severity), s($record->visibility)];
    }
    echo html_writer::table($htable);
}

echo $OUTPUT->footer();

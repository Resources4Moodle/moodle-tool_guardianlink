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
 * Teacher action: email a learner's authorised adults their results/performance using a template.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use tool_guardianlink\local\template_service;
use tool_guardianlink\local\progress_service;
use tool_guardianlink\local\message_service;
use tool_guardianlink\local\relationship_service;

$childid = required_param('childid', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
require_capability('tool/guardianlink:sendproxymessages', $context);

$child = core_user::get_user($childid, '*', MUST_EXIST);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/admin/tool/guardianlink/sendresults.php', ['childid' => $childid, 'courseid' => $courseid]));
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('sendresults', 'tool_guardianlink'));
$PAGE->set_heading(format_string($course->fullname));

// Templates suitable for results (results trigger or manual), enabled — this course's plus global.
$templates = [];
foreach (
    array_merge(
        template_service::get_course_templates($courseid, true, 'results', true),
        template_service::get_course_templates($courseid, true, 'manual', true)
    ) as $t
) {
    $templates[$t->id] = $t;
}

$gradeitems = progress_service::gradeitem_options($courseid);

$templateid = optional_param('templateid', 0, PARAM_INT);
$gradeitemid = optional_param('gradeitemid', 0, PARAM_INT);
$dosend = optional_param('send', 0, PARAM_BOOL);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('sendresults', 'tool_guardianlink'));
echo \tool_guardianlink\local\ui::help_link('sendresults');
echo html_writer::tag('p', get_string('selectlearner', 'tool_guardianlink') . ': ' . s(fullname($child)));

if (empty($templates)) {
    echo $OUTPUT->notification(get_string('sendresultsnotemplates', 'tool_guardianlink'), 'warning');
    echo html_writer::tag('p', html_writer::link(
        new moodle_url('/admin/tool/guardianlink/teachertemplates.php', ['courseid' => $courseid]),
        get_string('managecoursetemplates', 'tool_guardianlink')
    ));
    echo $OUTPUT->footer();
    exit;
}

$extra = $gradeitemid ? ['gradeitemid' => $gradeitemid] : [];

if ($dosend && $templateid && isset($templates[$templateid]) && confirm_sesskey()) {
    $result = message_service::send_proxy_template((int)$USER->id, $childid, $courseid, $templates[$templateid], $extra);
    echo $OUTPUT->notification(
        get_string('sendresultsdone', 'tool_guardianlink', (object)$result),
        $result['recipients'] ? 'success' : 'warning'
    );
    echo $OUTPUT->continue_button(new moodle_url('/admin/tool/guardianlink/course.php', ['courseid' => $courseid]));
    echo $OUTPUT->footer();
    exit;
}

// Selection + preview controls (simple GET).
$options = [];
foreach ($templates as $id => $t) {
    $scope = $t->courseid ? get_string('templatescope_course', 'tool_guardianlink')
        : get_string('templatescope_global', 'tool_guardianlink');
    $options[$id] = format_string($t->name) . ' [' . $scope . ']';
}
$pickid = $templateid ?: array_key_first($templates);

$tplselect = new single_select($PAGE->url, 'templateid', $options, $pickid, null);
$tplselect->label = get_string('template', 'tool_guardianlink') . ' ';
echo $OUTPUT->render($tplselect);

if (!empty($gradeitems)) {
    $gopts = [0 => get_string('notestselected', 'tool_guardianlink')] + $gradeitems;
    $gurl = new moodle_url($PAGE->url, ['templateid' => $pickid]);
    $gselect = new single_select($gurl, 'gradeitemid', $gopts, $gradeitemid, null);
    $gselect->label = get_string('specifictest', 'tool_guardianlink') . ' ';
    echo $OUTPUT->render($gselect);
}

// Preview the rendered template for this learner (with a representative adult context).
if ($pickid && isset($templates[$pickid])) {
    $reps = relationship_service::get_proxy_recipients($childid, $courseid);
    $repadult = $reps ? (int)reset($reps)->id : (int)$USER->id;
    $ctx = template_service::context($repadult, $childid, $courseid, true, $extra);
    $rendered = template_service::render($templates[$pickid], $ctx);
    echo $OUTPUT->heading(get_string('preview', 'tool_guardianlink'), 4);
    echo html_writer::div(
        html_writer::tag('strong', s($rendered['subject'])) . html_writer::tag('div', format_text($rendered['body'], FORMAT_HTML)),
        'card card-body bg-light mb-3'
    );
    echo html_writer::tag('p', get_string('sendresultsrecipients', 'tool_guardianlink', count($reps)));
    $sendurl = new moodle_url(
        $PAGE->url,
        ['templateid' => $pickid, 'gradeitemid' => $gradeitemid, 'send' => 1, 'sesskey' => sesskey()]
    );
    echo $OUTPUT->single_button($sendurl, get_string('sendresultssend', 'tool_guardianlink'));
}

echo $OUTPUT->footer();

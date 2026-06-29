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
 * Teacher/adult communication entry point.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/admin/tool/guardianlink/classes/form/proxy_message_form.php');

use tool_guardianlink\local\template_service;
use tool_guardianlink\local\progress_service;
use tool_guardianlink\local\message_service;
use tool_guardianlink\local\relationship_service;

$childid = required_param('childid', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);
$loadtemplate = optional_param('loadtemplate', 0, PARAM_INT);
require_login();

$course = get_course($courseid);
$child = core_user::get_user($childid, '*', MUST_EXIST);
$context = context_course::instance($courseid);
$isteacher = has_capability('tool/guardianlink:sendproxymessages', $context);
$isadult = relationship_service::can_access_child((int)$USER->id, $childid, $courseid, 'teachercontact');
if (!$isteacher && !$isadult) {
    throw new moodle_exception('accessdenied', 'tool_guardianlink');
}
// Reject stale/guessed childid+courseid pairings: the learner must be enrolled in this course.
if (!relationship_service::learner_enrolled_in_course($childid, $courseid)) {
    throw new moodle_exception('notenrolled', 'tool_guardianlink');
}
// Course policy: teacher proxy messaging can be disabled per course. Gradebook visibility is a
// separate Moodle permission from being able to message adults.
$proxyok = relationship_service::course_allows_teacher_proxy($courseid);
$canviewgrades = has_capability('moodle/grade:viewall', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/admin/tool/guardianlink/teachers.php', ['childid' => $childid, 'courseid' => $courseid]));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('teachers', 'tool_guardianlink'));
$PAGE->set_heading(format_string($course->fullname));

relationship_service::log_access((int)$USER->id, $childid, 'open_teacher_contact', $courseid);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('messageauthorisedadults', 'tool_guardianlink'));
echo \tool_guardianlink\local\ui::help_link('teachers');
echo html_writer::tag('p', get_string('selectlearner', 'tool_guardianlink') . ': ' . s(fullname($child)));
echo html_writer::tag('p', get_string('course') . ': ' . s(format_string($course->fullname)));

if ($isteacher && !$proxyok) {
    echo $OUTPUT->notification(get_string('teacherproxydisabled', 'tool_guardianlink'), 'warning');
}
if ($isteacher && $proxyok) {
    // Grade tokens/options are only offered to a sender who may view the gradebook.
    $gradeitems = $canviewgrades ? progress_service::gradeitem_options($courseid) : [];
    $activityph = $canviewgrades ? template_service::course_activity_placeholders($courseid) : [];
    $form = new \tool_guardianlink\form\proxy_message_form(
        null,
        ['gradeitems' => $gradeitems, 'activityplaceholders' => $activityph]
    );

    // Optional: prefill from a course or global template chosen above the form.
    $templates = template_service::get_course_templates($courseid, true, '', true);
    if ($templates) {
        $opts = [0 => get_string('templatechoose', 'tool_guardianlink')];
        foreach ($templates as $t) {
            $scope = $t->courseid ? get_string('templatescope_course', 'tool_guardianlink')
                : get_string('templatescope_global', 'tool_guardianlink');
            $opts[$t->id] = format_string($t->name) . ' [' . $scope . ']';
        }
        $loadurl = new moodle_url(
            '/admin/tool/guardianlink/teachers.php',
            ['childid' => $childid, 'courseid' => $courseid]
        );
        $select = new single_select($loadurl, 'loadtemplate', $opts, $loadtemplate, null);
        $select->label = get_string('templateload', 'tool_guardianlink') . ' ';
        echo $OUTPUT->render($select);
        echo html_writer::tag(
            'p',
            html_writer::link(
                new moodle_url('/admin/tool/guardianlink/teachertemplates.php', ['courseid' => $courseid]),
                get_string('managecoursetemplates', 'tool_guardianlink')
            ),
            ['class' => 'small']
        );
    }

    $defaults = ['childid' => $childid, 'courseid' => $courseid];
    if ($loadtemplate && isset($templates[$loadtemplate])) {
        $tpl = $templates[$loadtemplate];
        $defaults['subject'] = $tpl->subject;
        $defaults['message'] = ['text' => $tpl->body, 'format' => (int)$tpl->bodyformat];
    }
    $form->set_data($defaults);

    if ($form->is_cancelled()) {
        redirect(new moodle_url('/course/view.php', ['id' => $courseid]));
    } else if ($data = $form->get_data()) {
        require_sesskey();
        $template = (object)[
            'subject' => $data->subject,
            'body' => is_array($data->message) ? ($data->message['text'] ?? '') : (string)$data->message,
            'bodyformat' => is_array($data->message) ? (int)($data->message['format'] ?? FORMAT_HTML) : FORMAT_HTML,
        ];
        $extra = ($canviewgrades && !empty($data->gradeitemid)) ? ['gradeitemid' => (int)$data->gradeitemid] : [];
        $result = message_service::send_proxy_template(
            (int)$USER->id,
            $childid,
            $courseid,
            $template,
            $extra,
            $canviewgrades
        );
        redirect(
            $PAGE->url,
            get_string('proxysentsummary', 'tool_guardianlink', (object)$result),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
    $form->display();
} else {
    echo $OUTPUT->notification(
        'Teacher communication is routed through GuardianLink so contact details and separated-family metadata remain '
        . 'private by default. Use the school-approved contact channel shown in your Moodle site.',
        'info'
    );
}

echo $OUTPUT->footer();

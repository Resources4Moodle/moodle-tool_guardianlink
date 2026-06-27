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
 * Teacher action: send one template to the authorised adults of a course audience.
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

$courseid = required_param('courseid', PARAM_INT);
$audience = optional_param('audience', 'all', PARAM_ALPHA);     // All | atrisk.
$templateid = optional_param('templateid', 0, PARAM_INT);
$gradeitemid = optional_param('gradeitemid', 0, PARAM_INT);
$dosend = optional_param('send', 0, PARAM_BOOL);

$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
require_capability('tool/guardianlink:sendproxymessages', $context);

$baseurl = new moodle_url('/admin/tool/guardianlink/coursebulk.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_url($baseurl);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('bulkmessageguardians', 'tool_guardianlink'));
$PAGE->set_heading(format_string($course->fullname));

// Templates available in this course (course + global), enabled.
$templates = [];
foreach (template_service::get_course_templates($courseid, true, '', true) as $t) {
    $templates[$t->id] = $t;
}
$gradeitems = progress_service::gradeitem_options($courseid);

// Resolve the audience to learner ids that actually have reachable adults.
$learnerids = [];
if ($audience === 'atrisk') {
    $candidate = progress_service::overdue_learner_ids($courseid);
} else {
    $candidate = array_keys(get_enrolled_users($context, '', 0, 'u.id', null, 0, 0, true));
}
foreach ($candidate as $lid) {
    if (relationship_service::get_proxy_recipients((int)$lid, $courseid)) {
        $learnerids[] = (int)$lid;
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('bulkmessageguardians', 'tool_guardianlink'));
echo \tool_guardianlink\local\ui::help_link('coursebulk');
echo $OUTPUT->notification(get_string('coursebulkintro', 'tool_guardianlink'), 'info');

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

// Send.
if ($dosend && $templateid && isset($templates[$templateid]) && confirm_sesskey()) {
    $sent = 0;
    $recipients = 0;
    foreach ($learnerids as $lid) {
        $r = message_service::send_proxy_template((int)$USER->id, $lid, $courseid, $templates[$templateid], $extra);
        $sent += $r['sent'];
        $recipients += $r['recipients'];
    }
    relationship_service::log_access(
        (int)$USER->id,
        0,
        'course_bulk_sent',
        $courseid,
        'template',
        $templateid,
        ['audience' => $audience, 'learners' => count($learnerids)]
    );
    echo $OUTPUT->notification(
        get_string(
            'coursebulkdone',
            'tool_guardianlink',
            (object)['sent' => $sent, 'recipients' => $recipients, 'learners' => count($learnerids)]
        ),
        $sent ? 'success' : 'warning'
    );
    echo $OUTPUT->continue_button(new moodle_url('/admin/tool/guardianlink/course.php', ['courseid' => $courseid]));
    echo $OUTPUT->footer();
    exit;
}

// Selection controls (GET).
$audienceopts = [
    'all' => get_string('coursebulk_all', 'tool_guardianlink'),
    'atrisk' => get_string('coursebulk_atrisk', 'tool_guardianlink'),
];
$aurl = new moodle_url($baseurl, ['templateid' => $templateid, 'gradeitemid' => $gradeitemid]);
$aselect = new single_select($aurl, 'audience', $audienceopts, $audience, null);
$aselect->label = get_string('coursebulkaudience', 'tool_guardianlink') . ' ';
echo $OUTPUT->render($aselect);

$tplopts = [];
foreach ($templates as $id => $t) {
    $scope = $t->courseid ? get_string('templatescope_course', 'tool_guardianlink')
        : get_string('templatescope_global', 'tool_guardianlink');
    $tplopts[$id] = format_string($t->name) . ' [' . $scope . ']';
}
$pickid = $templateid ?: array_key_first($templates);
$turl = new moodle_url($baseurl, ['audience' => $audience, 'gradeitemid' => $gradeitemid]);
$tselect = new single_select($turl, 'templateid', $tplopts, $pickid, null);
$tselect->label = get_string('template', 'tool_guardianlink') . ' ';
echo $OUTPUT->render($tselect);

if (!empty($gradeitems)) {
    $gopts = [0 => get_string('notestselected', 'tool_guardianlink')] + $gradeitems;
    $gurl = new moodle_url($baseurl, ['audience' => $audience, 'templateid' => $pickid]);
    $gselect = new single_select($gurl, 'gradeitemid', $gopts, $gradeitemid, null);
    $gselect->label = get_string('specifictest', 'tool_guardianlink') . ' ';
    echo $OUTPUT->render($gselect);
}

echo html_writer::tag(
    'p',
    get_string('coursebulkcount', 'tool_guardianlink', count($learnerids)),
    ['class' => 'lead']
);

// Preview against a representative learner/adult.
if ($pickid && isset($templates[$pickid]) && $learnerids) {
    $samplelearner = (int)reset($learnerids);
    $reps = relationship_service::get_proxy_recipients($samplelearner, $courseid);
    $repadult = $reps ? (int)reset($reps)->id : (int)$USER->id;
    $ctx = template_service::context($repadult, $samplelearner, $courseid, true, $extra);
    $rendered = template_service::render($templates[$pickid], $ctx);
    echo $OUTPUT->heading(get_string('preview', 'tool_guardianlink'), 4);
    echo html_writer::div(html_writer::tag('strong', s($rendered['subject']))
        . html_writer::tag('div', format_text($rendered['body'], FORMAT_HTML)), 'card card-body bg-light mb-3');
}

if ($learnerids) {
    $sendurl = new moodle_url($baseurl, ['audience' => $audience, 'templateid' => $pickid,
        'gradeitemid' => $gradeitemid, 'send' => 1, 'sesskey' => sesskey()]);
    echo $OUTPUT->single_button($sendurl, get_string('coursebulksend', 'tool_guardianlink'));
} else {
    echo $OUTPUT->notification(get_string('coursebulknone', 'tool_guardianlink'), 'info');
}

echo $OUTPUT->footer();

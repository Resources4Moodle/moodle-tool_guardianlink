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
 * Teacher-managed message templates, scoped to one course.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/admin/tool/guardianlink/classes/form/template_form.php');

use tool_guardianlink\local\template_service;

$courseid = required_param('courseid', PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
require_capability('tool/guardianlink:sendproxymessages', $context);

$edit = optional_param('edit', 0, PARAM_INT);
$new = optional_param('new', 0, PARAM_BOOL);
$delete = optional_param('delete', 0, PARAM_INT);
$docopy = optional_param('docopy', 0, PARAM_BOOL);

$baseurl = new moodle_url('/admin/tool/guardianlink/teachertemplates.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_url($baseurl);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('managecoursetemplates', 'tool_guardianlink'));
$PAGE->set_heading(format_string($course->fullname));

// Guard: only act on templates that belong to THIS course (a teacher cannot touch another
// course's or the global admin templates from here).
$owncoursetemplate = function (int $id, int $courseid) {
    global $DB;
    if (!$id) {
        return null;
    }
    return $DB->get_record('tool_guardianlink_template', ['id' => $id, 'courseid' => $courseid]) ?: null;
};

if ($delete && confirm_sesskey()) {
    if ($owncoursetemplate($delete, $courseid)) {
        template_service::delete_template($delete);
    }
    redirect($baseurl, get_string('templatedeleted', 'tool_guardianlink'));
}

// Copy this course's templates into other courses the teacher teaches.
if ($docopy && confirm_sesskey()) {
    $sourceids = optional_param_array('sourceids', [], PARAM_INT);
    $targetids = optional_param_array('targetids', [], PARAM_INT);
    // Only allow copying into courses where the teacher actually holds the capability.
    $allowedtargets = [];
    foreach ($targetids as $tid) {
        if (has_capability('tool/guardianlink:sendproxymessages', context_course::instance((int)$tid))) {
            $allowedtargets[] = (int)$tid;
        }
    }
    // Only this course's own templates may be a source.
    $allowedsources = [];
    foreach ($sourceids as $sid) {
        if ($owncoursetemplate((int)$sid, $courseid)) {
            $allowedsources[] = (int)$sid;
        }
    }
    $n = ($allowedsources && $allowedtargets)
        ? template_service::copy_templates($allowedsources, $allowedtargets, (int)$USER->id) : 0;
    redirect($baseurl, get_string('templatescopied', 'tool_guardianlink', $n));
}

if ($edit || $new) {
    $form = new \tool_guardianlink\form\template_form($baseurl->out(false), [
        'activityplaceholders' => template_service::course_activity_placeholders($courseid),
    ]);
    if ($edit && ($rec = $owncoursetemplate($edit, $courseid))) {
        $form->set_data([
            'id' => $rec->id, 'courseid' => $courseid, 'shortname' => $rec->shortname, 'name' => $rec->name,
            'triggerkey' => $rec->triggerkey, 'enabled' => $rec->enabled, 'subject' => $rec->subject,
            'body' => ['text' => $rec->body, 'format' => $rec->bodyformat],
        ]);
    } else {
        $form->set_data(['courseid' => $courseid]);
    }
    if ($form->is_cancelled()) {
        redirect($baseurl);
    } else if ($data = $form->get_data()) {
        // Force the course scope no matter what the hidden field carried.
        $data->courseid = $courseid;
        template_service::save_template($data, (int)$USER->id);
        redirect($baseurl, get_string('templatesaved', 'tool_guardianlink'));
    }
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('managecoursetemplates', 'tool_guardianlink'));
    echo \tool_guardianlink\local\ui::help_link('coursetemplates');
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managecoursetemplates', 'tool_guardianlink'));
echo \tool_guardianlink\local\ui::help_link('coursetemplates');
echo $OUTPUT->notification(get_string('coursetemplatesintro', 'tool_guardianlink'), 'info');
echo $OUTPUT->single_button(new moodle_url($baseurl, ['new' => 1]), get_string('templateadd', 'tool_guardianlink'), 'get');

$templates = template_service::get_templates('', false, $courseid);
if ($templates) {
    $table = new html_table();
    $table->head = [get_string('templatename', 'tool_guardianlink'), get_string('templateshortname', 'tool_guardianlink'),
        get_string('templatetrigger', 'tool_guardianlink'), get_string('status', 'tool_guardianlink'), ''];
    foreach ($templates as $t) {
        $editurl = new moodle_url($baseurl, ['edit' => $t->id]);
        $delurl = new moodle_url($baseurl, ['delete' => $t->id, 'sesskey' => sesskey()]);
        $actions = html_writer::link($editurl, get_string('edit', 'tool_guardianlink'))
            . ' | ' . html_writer::link(
                $delurl,
                get_string('delete', 'tool_guardianlink'),
                ['onclick' => "return confirm('" . get_string('templatedeleteconfirm', 'tool_guardianlink') . "');"]
            );
        $table->data[] = [
            format_string($t->name), s($t->shortname),
            s(template_service::triggers()[$t->triggerkey] ?? $t->triggerkey),
            $t->enabled ? get_string('templateenabled', 'tool_guardianlink') : get_string('no'),
            $actions,
        ];
    }
    echo html_writer::table($table);

    // Copy/back-up these templates into other courses the teacher teaches.
    $othercourses = get_user_capability_course('tool/guardianlink:sendproxymessages', $USER->id, true, 'fullname');
    $targetopts = [];
    foreach ((array)$othercourses as $c) {
        if ((int)$c->id !== $courseid) {
            $targetopts[(int)$c->id] = format_string($c->fullname);
        }
    }
    if ($targetopts) {
        echo $OUTPUT->heading(get_string('copytemplatestitle', 'tool_guardianlink'), 3);
        echo html_writer::tag('p', get_string('copytemplatesintro', 'tool_guardianlink'));
        echo html_writer::start_tag('form', ['method' => 'post', 'action' => $baseurl->out(false)]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'docopy', 'value' => 1]);

        echo html_writer::tag(
            'label',
            get_string('copytemplatessource', 'tool_guardianlink'),
            ['class' => 'd-block font-weight-bold']
        );
        foreach ($templates as $t) {
            echo html_writer::div(
                html_writer::checkbox('sourceids[]', $t->id, false, ' ' . format_string($t->name))
            );
        }
        echo html_writer::tag(
            'label',
            get_string('copytemplatestargets', 'tool_guardianlink'),
            ['class' => 'd-block font-weight-bold mt-2']
        );
        foreach ($targetopts as $cid => $name) {
            echo html_writer::div(html_writer::checkbox('targetids[]', $cid, false, ' ' . $name));
        }
        echo html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-secondary mt-2',
            'value' => get_string('copytemplatesbutton', 'tool_guardianlink')]);
        echo html_writer::end_tag('form');
    }
} else {
    echo $OUTPUT->notification(get_string('coursetemplatesnone', 'tool_guardianlink'), 'info');
}

echo $OUTPUT->footer();

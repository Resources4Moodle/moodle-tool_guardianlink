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
 * Course-scoped relationship registry for faculty (admin-permitted).
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/admin/tool/guardianlink/classes/form/relationship_form.php');

use tool_guardianlink\local\relationship_service;

$courseid = required_param('courseid', PARAM_INT);
$edit = optional_param('edit', 0, PARAM_INT);
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);
require_capability('tool/guardianlink:managecourseregistry', $context);

$baseurl = new moodle_url('/admin/tool/guardianlink/registry.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_url($baseurl);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('courseregistry', 'tool_guardianlink'));
$PAGE->set_heading(format_string($course->fullname));

// The learners this faculty member may manage relationships for: those enrolled in this course.
$enrolledids = array_map('intval', array_keys(get_enrolled_users($context, '', 0, 'u.id', null, 0, 0, true)));
$enrolledset = array_fill_keys($enrolledids, true);

$form = new \tool_guardianlink\form\relationship_form($baseurl->out(false));

// Pre-fill on edit — only relationships whose learner is enrolled here.
if ($edit) {
    $rel = $DB->get_record('tool_guardianlink_rel', ['id' => $edit], '*', MUST_EXIST);
    if (!isset($enrolledset[(int)$rel->childid])) {
        throw new moodle_exception('registrylearnernotincourse', 'tool_guardianlink');
    }
    $phones = relationship_service::get_adult_phones((int)$rel->guardianid);
    $coursescope = null;
    foreach (relationship_service::get_scopes($edit) as $sc) {
        if ($sc->scopekind === 'course' && (int)$sc->courseid === $courseid) {
            $coursescope = $sc;
        }
    }
    $editdata = [
        'relationshipid' => $rel->id, 'adultid' => $rel->guardianid, 'learnerid' => $rel->childid,
        'adultphone1' => $phones['phone1'], 'adultphone2' => $phones['phone2'],
        'reltype' => $rel->reltype, 'authoritybasis' => $rel->authoritybasis, 'authoritystatus' => $rel->authoritystatus,
        'confidentiality' => $rel->confidentiality, 'legal' => $rel->legal, 'status' => $rel->status,
        'courseids' => [$courseid], 'categoryids' => [],
        'starttime' => $rel->starttime, 'endtime' => $rel->endtime, 'reviewtime' => $rel->reviewtime,
        'notes' => $rel->notes,
    ];
    if ($coursescope) {
        foreach (relationship_service::profile_fields() as $f) {
            $editdata[$f] = (int)$coursescope->{$f};
        }
    }
    $form->set_data($editdata);
} else {
    // New relationships from here are scoped to this course only.
    $form->set_data(['courseids' => [$courseid], 'categoryids' => []]);
}

if ($form->is_cancelled()) {
    redirect(new moodle_url('/admin/tool/guardianlink/course.php', ['courseid' => $courseid]));
} else if ($data = $form->get_data()) {
    require_sesskey();
    $learnerid = (int)$data->learnerid;
    if (!isset($enrolledset[$learnerid])) {
        redirect(
            $baseurl,
            get_string('registrylearnernotincourse', 'tool_guardianlink'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
    // Constrain the scope to this course no matter what the form carried.
    $data->courseids = (string)$courseid;
    $data->categoryids = '';
    relationship_service::add_or_update_relationship($data, (int)$USER->id, false);
    redirect(
        $baseurl,
        get_string('relationshipcreated', 'tool_guardianlink'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('courseregistry', 'tool_guardianlink'));
echo \tool_guardianlink\local\ui::help_link('registry');
echo $OUTPUT->notification(get_string('courseregistryintro', 'tool_guardianlink'), 'info');
$form->display();

// List relationships for this course's learners (names only — no contact detail).
echo $OUTPUT->heading(get_string('recentrelationships', 'tool_guardianlink'), 3);
if ($enrolledids) {
    [$insql, $inparams] = $DB->get_in_or_equal($enrolledids, SQL_PARAMS_NAMED);
    $rels = $DB->get_records_select('tool_guardianlink_rel', "childid $insql", $inparams, 'timemodified DESC', '*', 0, 200);
} else {
    $rels = [];
}
if ($rels) {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable';
    $table->head = ['ID', get_string('authorisedadult', 'tool_guardianlink'), get_string('selectlearner', 'tool_guardianlink'),
        get_string('reltype', 'tool_guardianlink'), get_string('authoritystatus', 'tool_guardianlink'),
        get_string('status', 'tool_guardianlink'), ''];
    foreach ($rels as $rel) {
        $adult = core_user::get_user((int)$rel->guardianid, '*', IGNORE_MISSING);
        $learner = core_user::get_user((int)$rel->childid, '*', IGNORE_MISSING);
        $isrestricted = ($rel->authoritystatus === 'restricted');
        $table->data[] = [
            (int)$rel->id,
            $adult ? fullname($adult) : (int)$rel->guardianid,
            $learner ? fullname($learner) : (int)$rel->childid,
            s($rel->reltype),
            $isrestricted
                ? html_writer::tag('strong', s($rel->authoritystatus), ['class' => 'text-danger'])
                : s($rel->authoritystatus),
            s($rel->status),
            html_writer::link(new moodle_url($baseurl, ['edit' => (int)$rel->id]), get_string('edit', 'tool_guardianlink')),
        ];
    }
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(get_string('courseregistrynone', 'tool_guardianlink'), 'info');
}

echo $OUTPUT->footer();

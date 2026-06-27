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
 * GuardianLink relationship registry.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/admin/tool/guardianlink/classes/form/relationship_form.php');

admin_externalpage_setup('tool_guardianlink_relationships');
$context = context_system::instance();
require_capability('tool/guardianlink:maprelationships', $context);

// Restricted-contact / safeguarding action (with a logged reason = dispute/evidence trail).
$restrictid = optional_param('restrictid', 0, PARAM_INT);
$doaction = optional_param('action', '', PARAM_ALPHA);
if ($restrictid && in_array($doaction, ['restrict', 'unrestrict'], true) && confirm_sesskey()) {
    $reason = optional_param('reason', '', PARAM_TEXT);
    if (trim($reason) === '') {
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('restrictedcontact', 'tool_guardianlink'));
        echo html_writer::start_tag('form', ['method' => 'get', 'action' => $PAGE->url->out(false)]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'restrictid', 'value' => $restrictid]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => $doaction]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::tag('p', get_string('restrictreasonprompt', 'tool_guardianlink'));
        echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'reason', 'size' => 70, 'class' => 'form-control mb-2']);
        echo html_writer::empty_tag('input', [
            'type' => 'submit',
            'class' => 'btn btn-primary',
            'value' => get_string('save', 'tool_guardianlink'),
        ]);
        echo html_writer::end_tag('form');
        echo $OUTPUT->footer();
        exit;
    }
    \tool_guardianlink\local\relationship_service::set_restricted($restrictid, $doaction === 'restrict', $reason, (int)$USER->id);
    redirect($PAGE->url, get_string('restrictsaved', 'tool_guardianlink'));
}

$form = new \tool_guardianlink\form\relationship_form();

// Load an existing relationship for editing.
$edit = optional_param('edit', 0, PARAM_INT);
if ($edit) {
    $rel = $DB->get_record('tool_guardianlink_rel', ['id' => $edit], '*', MUST_EXIST);
    $adultphones = \tool_guardianlink\local\relationship_service::get_adult_phones((int)$rel->guardianid);
    $courseids = [];
    $categoryids = [];
    $coursescope = null;
    foreach (\tool_guardianlink\local\relationship_service::get_scopes($edit) as $sc) {
        if ($sc->scopekind === 'course' && $sc->courseid) {
            $courseids[] = (int)$sc->courseid;
            $coursescope = $coursescope ?: $sc;
        }
        if ($sc->scopekind === 'category' && $sc->categoryid) {
            $categoryids[] = (int)$sc->categoryid;
        }
    }
    $editdata = [
        'relationshipid' => $rel->id, 'adultid' => $rel->guardianid, 'learnerid' => $rel->childid,
        'adultphone1' => $adultphones['phone1'], 'adultphone2' => $adultphones['phone2'],
        'reltype' => $rel->reltype, 'authoritybasis' => $rel->authoritybasis, 'authoritystatus' => $rel->authoritystatus,
        'confidentiality' => $rel->confidentiality, 'legal' => $rel->legal, 'status' => $rel->status,
        'courseids' => $courseids, 'categoryids' => $categoryids,
        'starttime' => $rel->starttime, 'endtime' => $rel->endtime, 'reviewtime' => $rel->reviewtime,
        'sourcecode' => $rel->sourcecode, 'externalid' => $rel->externalid,
        'householdkey' => $rel->householdkey, 'contactgroupkey' => $rel->contactgroupkey, 'notes' => $rel->notes,
    ];
    // Preserve the existing per-permission scope flags so an edit does not reset them.
    if ($coursescope) {
        foreach (\tool_guardianlink\local\relationship_service::profile_fields() as $f) {
            $editdata[$f] = (int)$coursescope->{$f};
        }
    }
    $form->set_data($editdata);
}

if ($form->is_cancelled()) {
    redirect(new moodle_url('/admin/tool/guardianlink/admin/index.php'));
} else if ($data = $form->get_data()) {
    require_sesskey();
    // The native course/category selectors return arrays; the service expects comma-separated ids.
    if (isset($data->courseids) && is_array($data->courseids)) {
        $data->courseids = implode(',', array_filter(array_map('intval', $data->courseids)));
    }
    if (isset($data->categoryids) && is_array($data->categoryids)) {
        $data->categoryids = implode(',', array_filter(array_map('intval', $data->categoryids)));
    }
    \tool_guardianlink\local\relationship_service::add_or_update_relationship($data, (int)$USER->id, false);
    redirect(
        new moodle_url('/admin/tool/guardianlink/admin/relationships.php'),
        get_string('relationshipcreated', 'tool_guardianlink')
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('admin_relationships', 'tool_guardianlink'));
echo \tool_guardianlink\local\ui::help_link('relationships');
echo $OUTPUT->notification(
    'Use the neutral authorised-adult/learner model. Avoid encoding private family conflict details in broad notes; use '
        . 'confidentiality and authority status fields, and keep high-risk documents in the school\'s governed record system.',
    'info'
);
$form->display();

$relationships = \tool_guardianlink\local\relationship_service::get_recent_relationships(100);
$table = new html_table();
$table->head = [
    'ID',
    get_string('authorisedadult', 'tool_guardianlink'),
    get_string('selectlearner', 'tool_guardianlink'),
    get_string('reltype', 'tool_guardianlink'),
    get_string('authoritystatus', 'tool_guardianlink'),
    get_string('confidentiality', 'tool_guardianlink'),
    get_string('status', 'tool_guardianlink'),
    get_string('restrictedcontact', 'tool_guardianlink'),
];
foreach ($relationships as $rel) {
    $adult = core_user::get_user(
        (int)$rel->guardianid,
        'id,firstname,lastname,firstnamephonetic,lastnamephonetic,middlename,alternatename',
        IGNORE_MISSING
    );
    $learner = core_user::get_user(
        (int)$rel->childid,
        'id,firstname,lastname,firstnamephonetic,lastnamephonetic,middlename,alternatename',
        IGNORE_MISSING
    );
    $isrestricted = ($rel->authoritystatus === 'restricted');
    $actionurl = new moodle_url($PAGE->url, [
        'restrictid' => (int)$rel->id,
        'action' => $isrestricted ? 'unrestrict' : 'restrict',
        'sesskey' => sesskey(),
    ]);
    $table->data[] = [
        (int)$rel->id,
        $adult ? fullname($adult) . ' (#' . (int)$rel->guardianid . ')' : (int)$rel->guardianid,
        $learner ? fullname($learner) . ' (#' . (int)$rel->childid . ')' : (int)$rel->childid,
        s($rel->reltype),
        $isrestricted
            ? html_writer::tag('strong', s($rel->authoritystatus), ['class' => 'text-danger'])
            : s($rel->authoritystatus),
        s($rel->confidentiality),
        s($rel->status),
        html_writer::link(new moodle_url($PAGE->url, ['edit' => (int)$rel->id]), get_string('edit', 'tool_guardianlink'))
            . ' | ' . html_writer::link($actionurl, get_string($isrestricted ? 'unrestrict' : 'restrict', 'tool_guardianlink'))
            . ' | ' . html_writer::link(
                new moodle_url('/admin/tool/guardianlink/admin/proof.php', ['relationshipid' => (int)$rel->id]),
                get_string('admin_proof', 'tool_guardianlink')
            ),
    ];
}
echo $OUTPUT->heading(get_string('recentrelationships', 'tool_guardianlink'), 3);
echo html_writer::table($table);
echo $OUTPUT->footer();

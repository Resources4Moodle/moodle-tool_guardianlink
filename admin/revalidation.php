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
 * Periodic re-validation of delegated relationships (custody/audit cycle).
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_guardianlink\local\relationship_service;

admin_externalpage_setup('tool_guardianlink_revalidation');
$context = context_system::instance();
require_capability('tool/guardianlink:maprelationships', $context);

$revalidate = optional_param('revalidate', 0, PARAM_INT);
$baseurl = new moodle_url('/admin/tool/guardianlink/admin/revalidation.php');
$PAGE->set_url($baseurl);

if ($revalidate && confirm_sesskey()) {
    $note = optional_param('note', '', PARAM_TEXT);
    if (trim($note) === '') {
        // Ask for a validation note (recorded as the custody/audit entry).
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('revalidate', 'tool_guardianlink'));
        echo html_writer::start_tag('form', ['method' => 'get', 'action' => $baseurl->out(false)]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'revalidate', 'value' => $revalidate]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo html_writer::tag('p', get_string('revalidatenoteprompt', 'tool_guardianlink'));
        echo html_writer::tag('textarea', '', ['name' => 'note', 'rows' => 3, 'cols' => 70, 'class' => 'form-control mb-2']);
        echo html_writer::empty_tag('input', [
            'type' => 'submit',
            'class' => 'btn btn-primary',
            'value' => get_string('revalidate', 'tool_guardianlink'),
        ]);
        echo html_writer::end_tag('form');
        echo $OUTPUT->footer();
        exit;
    }
    relationship_service::revalidate($revalidate, (int)$USER->id, $note);
    redirect($baseurl, get_string('revalidated', 'tool_guardianlink'));
}

$leaddays = (int)get_config('tool_guardianlink', 'reviewleaddays');
if ($leaddays <= 0) {
    $leaddays = 14;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('admin_revalidation', 'tool_guardianlink'));
$period = (int)get_config('tool_guardianlink', 'revalidationperiod');
$unit = get_config('tool_guardianlink', 'revalidationunit') ?: 'months';
echo $OUTPUT->notification(get_string(
    'revalidationintro',
    'tool_guardianlink',
    (object)['period' => $period, 'unit' => $unit, 'lead' => $leaddays]
), 'info');

$due = relationship_service::get_revalidation_due($leaddays);
if ($due) {
    $now = time();
    $table = new html_table();
    $table->head = [get_string('authorisedadult', 'tool_guardianlink'), get_string('selectlearner', 'tool_guardianlink'),
        get_string('reltype', 'tool_guardianlink'), get_string('reviewtime', 'tool_guardianlink'), ''];
    foreach ($due as $rel) {
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
        $overdue = $rel->reviewtime < $now;
        $datecell = ($overdue
            ? html_writer::tag('strong', userdate($rel->reviewtime), ['class' => 'text-danger'])
            : userdate($rel->reviewtime));
        $action = html_writer::link(
            new moodle_url($baseurl, ['revalidate' => $rel->id, 'sesskey' => sesskey()]),
            get_string('revalidate', 'tool_guardianlink')
        );
        $table->data[] = [
            $adult ? fullname($adult) : (int)$rel->guardianid,
            $learner ? fullname($learner) : (int)$rel->childid,
            s($rel->reltype), $datecell, $action,
        ];
    }
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(get_string('revalidationnone', 'tool_guardianlink'), 'success');
}

echo $OUTPUT->footer();

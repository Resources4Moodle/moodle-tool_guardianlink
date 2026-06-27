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
 * Record policy/consent on behalf of a learner (legal-responsibility holders only).
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');

use tool_guardianlink\local\relationship_service;
use tool_guardianlink\local\consent_service;

$childid = required_param('childid', PARAM_INT);
require_login();
$context = context_system::instance();

$relationship = relationship_service::get_active_relationship((int)$USER->id, $childid);
if (!$relationship || empty($relationship->legal)) {
    throw new moodle_exception('accessdenied', 'tool_guardianlink');
}
$child = core_user::get_user($childid, '*', MUST_EXIST);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/admin/tool/guardianlink/my/consent.php', ['childid' => $childid]));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('consent', 'tool_guardianlink'));
$PAGE->set_heading(get_string('pluginname', 'tool_guardianlink'));

// Process submitted consents.
if (($keys = optional_param_array('consent', [], PARAM_ALPHANUMEXT)) && confirm_sesskey()) {
    $count = 0;
    foreach ($keys as $key => $on) {
        if ($on) {
            consent_service::record((int)$USER->id, $childid, (string)$key);
            $count++;
        }
    }
    redirect(
        new moodle_url('/admin/tool/guardianlink/my/consent.php', ['childid' => $childid]),
        get_string('consentsaved', 'tool_guardianlink', $count),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('consent', 'tool_guardianlink') . ': ' . fullname($child));

$policies = consent_service::required_policies();
if (empty($policies)) {
    echo $OUTPUT->notification(get_string('consentnone', 'tool_guardianlink'), 'info');
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->notification(get_string('consentintro', 'tool_guardianlink'), 'info');

$outstanding = consent_service::outstanding((int)$USER->id, $childid);
if ($outstanding) {
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false)]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'childid', 'value' => $childid]);
    echo $OUTPUT->heading(get_string('consentoutstanding', 'tool_guardianlink'), 3);
    foreach ($outstanding as $key => $label) {
        echo html_writer::div(
            html_writer::checkbox('consent[' . $key . ']', 1, false, ' ' . format_string($label)),
            'mb-2'
        );
    }
    echo html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-primary',
        'value' => get_string('consentgive', 'tool_guardianlink')]);
    echo html_writer::end_tag('form');
}

// Already-recorded consents.
$recorded = array_diff_key($policies, $outstanding);
if ($recorded) {
    echo $OUTPUT->heading(get_string('consentrecorded', 'tool_guardianlink'), 3);
    $table = new html_table();
    $table->head = [get_string('consentpolicy', 'tool_guardianlink'), get_string('status', 'tool_guardianlink')];
    foreach ($recorded as $key => $label) {
        $table->data[] = [format_string($label), get_string('consentgiven', 'tool_guardianlink')];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();

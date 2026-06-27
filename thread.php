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
 * Proxy message thread view + reply (teacher <-> authorised adult), contact details never exposed.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use tool_guardianlink\local\message_service;

$id = required_param('id', PARAM_INT);
require_login();
$context = context_system::instance();

$thread = $DB->get_record('tool_guardianlink_msgthread', ['id' => $id], '*', MUST_EXIST);
// Only the thread's two participants may view/reply.
if (!in_array((int)$USER->id, [(int)$thread->teacherid, (int)$thread->guardianid], true)) {
    throw new moodle_exception('accessdenied', 'tool_guardianlink');
}

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/admin/tool/guardianlink/thread.php', ['id' => $id]));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('messagethread', 'tool_guardianlink'));
$PAGE->set_heading(get_string('pluginname', 'tool_guardianlink'));

$reply = optional_param('reply', '', PARAM_TEXT);
if ($reply !== '' && confirm_sesskey()) {
    message_service::reply_to_thread($id, (int)$USER->id, $reply);
    redirect(
        new moodle_url('/admin/tool/guardianlink/thread.php', ['id' => $id]),
        get_string('replysent', 'tool_guardianlink'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string((string)$thread->subject));
echo $OUTPUT->notification(get_string('threadprivacynote', 'tool_guardianlink'), 'info');
echo html_writer::tag('p', html_writer::tag('strong', get_string('lastmessage', 'tool_guardianlink') . ': ')
    . format_text((string)$thread->lastmessage, FORMAT_PLAIN));

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $id]);
echo html_writer::tag('label', get_string('replylabel', 'tool_guardianlink'), ['for' => 'gl_reply']);
echo html_writer::tag(
    'textarea',
    '',
    ['name' => 'reply', 'id' => 'gl_reply', 'rows' => 5, 'cols' => 80, 'class' => 'form-control mb-2']
);
echo html_writer::empty_tag(
    'input',
    ['type' => 'submit', 'class' => 'btn btn-primary', 'value' => get_string('send', 'tool_guardianlink')]
);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();

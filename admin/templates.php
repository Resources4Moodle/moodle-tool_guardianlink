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
 * Manage email/message templates.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_guardianlink\local\template_service;

admin_externalpage_setup('tool_guardianlink_templates');
$context = context_system::instance();
require_capability('tool/guardianlink:managedigests', $context);

$edit = optional_param('edit', 0, PARAM_INT);
$delete = optional_param('delete', 0, PARAM_INT);
$new = optional_param('new', 0, PARAM_BOOL);
$baseurl = new moodle_url('/admin/tool/guardianlink/admin/templates.php');
$PAGE->set_url($baseurl);

if ($delete && confirm_sesskey()) {
    template_service::delete_template($delete);
    redirect($baseurl, get_string('templatedeleted', 'tool_guardianlink'));
}

if ($edit || $new) {
    $form = new \tool_guardianlink\form\template_form();
    if ($edit) {
        global $DB;
        $rec = $DB->get_record('tool_guardianlink_template', ['id' => $edit], '*', MUST_EXIST);
        $form->set_data([
            'id' => $rec->id, 'shortname' => $rec->shortname, 'name' => $rec->name,
            'triggerkey' => $rec->triggerkey, 'enabled' => $rec->enabled, 'subject' => $rec->subject,
            'body' => ['text' => $rec->body, 'format' => $rec->bodyformat],
        ]);
    }
    if ($form->is_cancelled()) {
        redirect($baseurl);
    } else if ($data = $form->get_data()) {
        template_service::save_template($data, (int)$USER->id);
        redirect($baseurl, get_string('templatesaved', 'tool_guardianlink'));
    }
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('admin_templates', 'tool_guardianlink'));
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('admin_templates', 'tool_guardianlink'));
echo \tool_guardianlink\local\ui::help_link('templates');
echo $OUTPUT->notification(get_string('templatesintro', 'tool_guardianlink'), 'info');
echo $OUTPUT->single_button(new moodle_url($baseurl, ['new' => 1]), get_string('templateadd', 'tool_guardianlink'), 'get');

$templates = template_service::get_templates();
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
} else {
    echo $OUTPUT->notification(get_string('templatesnone', 'tool_guardianlink'), 'info');
}

echo $OUTPUT->footer();

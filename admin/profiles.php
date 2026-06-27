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
 * Manage admin-editable access profiles.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_guardianlink\local\relationship_service;

admin_externalpage_setup('tool_guardianlink_profiles');
$context = context_system::instance();
require_capability('tool/guardianlink:configureroles', $context);

$edit = optional_param('edit', 0, PARAM_INT);
$delete = optional_param('delete', 0, PARAM_INT);
$new = optional_param('new', 0, PARAM_BOOL);
$baseurl = new moodle_url('/admin/tool/guardianlink/admin/profiles.php');
$PAGE->set_url($baseurl);

if ($delete && confirm_sesskey()) {
    relationship_service::delete_profile($delete);
    redirect($baseurl, get_string('profiledeleted', 'tool_guardianlink'));
}

if ($edit || $new) {
    $form = new \tool_guardianlink\form\profile_form();
    if ($edit) {
        $rec = $DB->get_record('tool_guardianlink_profile', ['id' => $edit], '*', MUST_EXIST);
        $form->set_data($rec);
    }
    if ($form->is_cancelled()) {
        redirect($baseurl);
    } else if ($data = $form->get_data()) {
        relationship_service::save_profile($data, (int)$USER->id);
        redirect($baseurl, get_string('profilesaved', 'tool_guardianlink'));
    }
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('admin_profiles', 'tool_guardianlink'));
    $form->display();
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('admin_profiles', 'tool_guardianlink'));
echo $OUTPUT->notification(get_string('profilesintro', 'tool_guardianlink'), 'info');
echo $OUTPUT->single_button(new moodle_url($baseurl, ['new' => 1]), get_string('profileadd', 'tool_guardianlink'), 'get');

$profiles = relationship_service::get_profiles();
if ($profiles) {
    $fields = relationship_service::profile_fields();
    $table = new html_table();
    $head = [get_string('templatename', 'tool_guardianlink'), get_string('templateshortname', 'tool_guardianlink')];
    foreach ($fields as $f) {
        $head[] = get_string($f, 'tool_guardianlink');
    }
    $head[] = '';
    $table->head = $head;
    foreach ($profiles as $p) {
        $row = [format_string($p->name), s($p->shortname)];
        foreach ($fields as $f) {
            $row[] = $p->{$f} ? '✓' : '·';
        }
        $editurl = new moodle_url($baseurl, ['edit' => $p->id]);
        $actions = html_writer::link($editurl, get_string('edit', 'tool_guardianlink'));
        if (!$p->systemmanaged) {
            $delurl = new moodle_url($baseurl, ['delete' => $p->id, 'sesskey' => sesskey()]);
            $actions .= ' | ' . html_writer::link(
                $delurl,
                get_string('delete', 'tool_guardianlink'),
                ['onclick' => "return confirm('" . get_string('profiledeleteconfirm', 'tool_guardianlink') . "');"]
            );
        }
        $row[] = $actions;
        $table->data[] = $row;
    }
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(get_string('profilesnone', 'tool_guardianlink'), 'info');
}

echo $OUTPUT->footer();

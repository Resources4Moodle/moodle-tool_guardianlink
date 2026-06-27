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
 * Custody chain: HTML validation notes + proof attachments for a relationship (admin-only).
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_guardianlink\local\relationship_service;

$relationshipid = required_param('relationshipid', PARAM_INT);
admin_externalpage_setup('tool_guardianlink_relationships');
$context = context_system::instance();
// Sensitive custody material: restrict to admin-level managers only.
require_capability('tool/guardianlink:manage', $context);

$rel = $DB->get_record('tool_guardianlink_rel', ['id' => $relationshipid], '*', MUST_EXIST);
$baseurl = new moodle_url('/admin/tool/guardianlink/admin/proof.php', ['relationshipid' => $relationshipid]);
$PAGE->set_url($baseurl);
$PAGE->set_title(get_string('admin_proof', 'tool_guardianlink'));

$form = new \tool_guardianlink\form\proof_form(null, null, 'post', '', null, true, ['relationshipid' => $relationshipid]);
$form->set_data(['relationshipid' => $relationshipid, 'note' => ['text' => '', 'format' => FORMAT_HTML]]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/admin/tool/guardianlink/admin/relationships.php'));
} else if ($data = $form->get_data()) {
    relationship_service::add_proof($relationshipid, (int)$USER->id, (array)$data->note, (int)$data->attachments);
    redirect($baseurl, get_string('proofsaved', 'tool_guardianlink'));
}

$adult = core_user::get_user((int)$rel->guardianid, '*', IGNORE_MISSING);
$learner = core_user::get_user((int)$rel->childid, '*', IGNORE_MISSING);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('admin_proof', 'tool_guardianlink'));
echo $OUTPUT->notification(get_string('proofintro', 'tool_guardianlink'), 'info');
echo html_writer::tag('p', html_writer::tag('strong', get_string('relationship', 'tool_guardianlink') . ': ')
    . ($adult ? fullname($adult) : $rel->guardianid) . ' → ' . ($learner ? fullname($learner) : $rel->childid)
    . ' (' . s($rel->reltype) . ')');

// Existing custody entries (newest first).
$entries = relationship_service::get_proofs($relationshipid);
if ($entries) {
    echo $OUTPUT->heading(get_string('proofchain', 'tool_guardianlink'), 3);
    foreach ($entries as $entry) {
        $by = core_user::get_user((int)$entry->userid, '*', IGNORE_MISSING);
        $note = file_rewrite_pluginfile_urls(
            $entry->note,
            'pluginfile.php',
            $context->id,
            'tool_guardianlink',
            'proofnote',
            $entry->id
        );
        $files = [];
        foreach (relationship_service::get_proof_files((int)$entry->id) as $file) {
            $url = moodle_url::make_pluginfile_url(
                $context->id,
                'tool_guardianlink',
                'proof',
                $entry->id,
                $file->get_filepath(),
                $file->get_filename()
            );
            $files[] = html_writer::link($url, $file->get_filename());
        }
        $meta = userdate($entry->timecreated) . ' — ' . ($by ? fullname($by) : $entry->userid);
        $html = html_writer::tag('div', s($meta), ['class' => 'text-muted small'])
            . format_text($note, (int)$entry->noteformat, ['context' => $context])
            . ($files
                ? html_writer::tag('div', get_string('proofattachments', 'tool_guardianlink') . ': ' . implode(', ', $files))
                : '');
        echo html_writer::div($html, 'card card-body mb-2');
    }
}

echo $OUTPUT->heading(get_string('proofadd', 'tool_guardianlink'), 3);
$form->display();
echo $OUTPUT->footer();

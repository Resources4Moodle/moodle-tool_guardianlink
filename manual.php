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
 * GuardianLink help manual — one page documenting every GuardianLink page, for admins and faculty.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

require_login();
$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/admin/tool/guardianlink/manual.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('manualtitle', 'tool_guardianlink'));
$PAGE->set_heading(get_string('manualtitle', 'tool_guardianlink'));

// The manual content. Each entry is id => audience; the section title, body and audience label
// all come from lang strings (manual_<id>_title, manual_<id>_body, manualaud_<audience>), so the
// help text is translatable. Section ids match the tool_guardianlink_help_link() anchors.
$sections = [
    'overview' => 'everyone',
    'relationships' => 'admin',
    'registry' => 'teacher',
    'uploadparents' => 'admin',
    'roletypes' => 'admin',
    'profiles' => 'admin',
    'organisations' => 'admin',
    'health' => 'admin',
    'tutor_requests' => 'admin',
    'messaging' => 'admin',
    'templates' => 'admin',
    'coursetemplates' => 'teacher',
    'placeholders' => 'everyone',
    'bulkmail' => 'admin',
    'coursebulk' => 'teacher',
    'teachers' => 'teacher',
    'sendresults' => 'teacher',
    'coursedash' => 'teacher',
    'report' => 'admin',
    'audit' => 'admin',
    'revalidation' => 'admin',
    'integrations' => 'admin',
    'api' => 'developer',
    'assisted' => 'admin',
    'independent' => 'everyone',
    'settings' => 'admin',
    'privacy' => 'everyone',
];

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manualtitle', 'tool_guardianlink'));
echo $OUTPUT->notification(get_string('manualintro', 'tool_guardianlink'), 'info');

// Table of contents.
echo $OUTPUT->heading(get_string('manualcontents', 'tool_guardianlink'), 3);
echo html_writer::start_tag('ul');
foreach ($sections as $id => $audience) {
    $audiencebadge = html_writer::tag(
        'span',
        get_string('manualaud_' . $audience, 'tool_guardianlink'),
        ['class' => 'badge badge-light']
    );
    echo html_writer::tag(
        'li',
        html_writer::link(new moodle_url($PAGE->url, [], $id), get_string('manual_' . $id . '_title', 'tool_guardianlink'))
        . ' ' . $audiencebadge
    );
}
echo html_writer::end_tag('ul');

// Sections.
foreach ($sections as $id => $audience) {
    echo html_writer::start_div('card mb-3');
    echo html_writer::start_div('card-body');
    $audiencebadge = html_writer::tag(
        'span',
        get_string('manualaud_' . $audience, 'tool_guardianlink'),
        ['class' => 'badge badge-secondary']
    );
    echo html_writer::tag(
        'h3',
        get_string('manual_' . $id . '_title', 'tool_guardianlink') . ' ' . $audiencebadge,
        ['id' => $id]
    );
    echo get_string('manual_' . $id . '_body', 'tool_guardianlink');
    echo html_writer::link(
        new moodle_url($PAGE->url, [], 'top'),
        get_string('backtotop', 'tool_guardianlink'),
        ['class' => 'small']
    );
    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo $OUTPUT->footer();

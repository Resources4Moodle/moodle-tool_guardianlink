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
 * Authorised adult self-service area for linked learners.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');

require_login();
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/admin/tool/guardianlink/my/admin.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('childrenadmin', 'tool_guardianlink'));
$PAGE->set_heading(get_string('pluginname', 'tool_guardianlink'));

$learners = \tool_guardianlink\local\relationship_service::get_learners_for_adult((int)$USER->id);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('childrenadmin', 'tool_guardianlink'));
echo html_writer::tag('p', get_string('childadminintro', 'tool_guardianlink'));

if (empty($learners)) {
    echo $OUTPUT->notification(get_string('nochildren', 'tool_guardianlink'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [
    get_string('selectlearner', 'tool_guardianlink'),
    get_string('relationship', 'tool_guardianlink'),
    get_string('supportnetwork', 'tool_guardianlink'),
    get_string('authorisedadults', 'tool_guardianlink'),
    get_string('tutorrequests', 'tool_guardianlink'),
    get_string('digestpreferences', 'tool_guardianlink'),
];
foreach ($learners as $learner) {
    // Only a legal-responsibility holder may delegate/add other authorised adults or give consent.
    if (!empty($learner->legal)) {
        $glinks = [
            html_writer::link(
                new moodle_url('/admin/tool/guardianlink/my/guardians.php', ['childid' => $learner->childid]),
                get_string('addguardian', 'tool_guardianlink')
            ),
            html_writer::link(
                new moodle_url('/admin/tool/guardianlink/my/consent.php', ['childid' => $learner->childid]),
                get_string('consent', 'tool_guardianlink')
            ),
        ];
        $guardianscell = implode(' | ', $glinks);
    } else {
        $guardianscell = '-';
    }
    $table->data[] = [
        html_writer::link(new moodle_url('/admin/tool/guardianlink/child.php', ['id' => $learner->childid]), fullname($learner)),
        s($learner->reltype),
        s($learner->authoritystatus) . ' / ' . s($learner->confidentiality),
        $guardianscell,
        html_writer::link(
            new moodle_url('/admin/tool/guardianlink/my/tutors.php', ['childid' => $learner->childid]),
            get_string('tutorrequest', 'tool_guardianlink')
        ),
        html_writer::link(
            new moodle_url('/admin/tool/guardianlink/my/digest.php', ['childid' => $learner->childid]),
            get_string('managedigest', 'tool_guardianlink')
        ),
    ];
}
echo html_writer::table($table);
echo $OUTPUT->footer();

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
 * GuardianLink family/support dashboard.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

require_login();
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/admin/tool/guardianlink/index.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('familydashboard', 'tool_guardianlink'));
$PAGE->set_heading(get_string('pluginname', 'tool_guardianlink'));

$learners = \tool_guardianlink\local\relationship_service::get_learners_for_adult((int)$USER->id);
$canmanage = has_capability('tool/guardianlink:manage', $context);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('familydashboard', 'tool_guardianlink'));
echo $OUTPUT->notification(get_string('relationshipwarning', 'tool_guardianlink'), 'info');

$links = [];
if ($canmanage) {
    $links[] = html_writer::link(
        new moodle_url('/admin/tool/guardianlink/admin/index.php'),
        get_string('guardianlinkadmin', 'tool_guardianlink')
    );
}
$links[] = html_writer::link(
    new moodle_url('/admin/tool/guardianlink/my/admin.php'),
    get_string('childrenadmin', 'tool_guardianlink')
);
if (get_config('tool_guardianlink', 'enableassistedmode')) {
    $links[] = html_writer::link(
        new moodle_url('/admin/tool/guardianlink/my/assist.php'),
        get_string('assistedhub', 'tool_guardianlink')
    );
}
echo html_writer::div(implode(' | ', $links), 'mb-3');

if (empty($learners)) {
    echo $OUTPUT->notification(get_string('nochildren', 'tool_guardianlink'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [
    get_string('selectlearner', 'tool_guardianlink'),
    get_string('relationship', 'tool_guardianlink'),
    get_string('authoritystatus', 'tool_guardianlink'),
    get_string('endtime', 'tool_guardianlink'),
];
foreach ($learners as $learner) {
    $url = new moodle_url('/admin/tool/guardianlink/child.php', ['id' => $learner->childid]);
    $table->data[] = [
        html_writer::link($url, fullname($learner)),
        s($learner->reltype),
        s($learner->authoritystatus),
        !empty($learner->endtime) ? userdate($learner->endtime) : '-',
    ];
}
echo html_writer::table($table);
echo $OUTPUT->footer();

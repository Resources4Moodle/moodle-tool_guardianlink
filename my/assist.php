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
 * Assisted-access hub: explicit per-learner, per-course selection for adults with several learners.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/enrollib.php');

use tool_guardianlink\local\relationship_service;

require_login();
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/admin/tool/guardianlink/my/assist.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('assistedaccess', 'tool_guardianlink'));
$PAGE->set_heading(get_string('pluginname', 'tool_guardianlink'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('assistedhub', 'tool_guardianlink'));
echo $OUTPUT->notification(get_string('assistedhubintro', 'tool_guardianlink'), 'info');

$learners = relationship_service::get_learners_for_adult((int)$USER->id);
$table = new html_table();
$table->head = [
    get_string('selectlearner', 'tool_guardianlink'),
    get_string('course'),
    get_string('assistedaccess', 'tool_guardianlink'),
];

foreach ($learners as $learner) {
    $relationship = relationship_service::get_active_relationship((int)$USER->id, (int)$learner->childid);
    if (!$relationship) {
        continue;
    }
    $allcourses = enrol_get_users_courses((int)$learner->childid, true, 'id, fullname');
    foreach (relationship_service::get_scopes((int)$relationship->id) as $scope) {
        if (empty($scope->allowassisted) || empty($scope->courseid) || empty($allcourses[$scope->courseid])) {
            continue;
        }
        $status = relationship_service::assisted_access_status((int)$USER->id, (int)$learner->childid, (int)$scope->courseid);
        $cell = $status['allowed']
            ? html_writer::link(
                new moodle_url(
                    '/admin/tool/guardianlink/assist.php',
                    ['childid' => $learner->childid, 'courseid' => $scope->courseid]
                ),
                get_string('assistedstart', 'tool_guardianlink'),
                ['class' => 'btn btn-secondary btn-sm']
            )
            : html_writer::span(s($status['reason']), 'text-muted');
        $table->data[] = [fullname($learner), format_string($allcourses[$scope->courseid]->fullname), $cell];
    }
}

if (!empty($table->data)) {
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(get_string('assistednolearners', 'tool_guardianlink'), 'info');
}

echo $OUTPUT->footer();

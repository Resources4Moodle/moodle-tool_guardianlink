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
 * GuardianLink admin overview.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('tool_guardianlink_overview');
$context = context_system::instance();
require_capability('tool/guardianlink:manage', $context);

$counts = \tool_guardianlink\local\relationship_service::get_admin_counts();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('admin_overview', 'tool_guardianlink'));

// Safety self-check: the authorised-adult role must never hold login-as. Surface any violation.
foreach (\tool_guardianlink\local\setup::guardrail_warnings() as $warning) {
    echo $OUTPUT->notification($warning, 'warning');
}

echo html_writer::tag(
    'p',
    'GuardianLink is now structured around one admin category: overview, relationships, role types, organisations, '
        . 'health/care, tutor requests, messaging, integrations, audit, and settings. This avoids a single cluttered settings '
        . 'page and keeps high-risk areas separately permissioned.'
);

$table = new html_table();
$table->head = [get_string('overviewcounts', 'tool_guardianlink'), get_string('count', 'tool_guardianlink')];
foreach ($counts as $key => $value) {
    $table->data[] = [get_string($key, 'tool_guardianlink'), (int)$value];
}
echo html_writer::table($table);

$links = [
    html_writer::link(
        new moodle_url('/admin/tool/guardianlink/admin/relationships.php'),
        get_string('admin_relationships', 'tool_guardianlink')
    ),
    html_writer::link(
        new moodle_url('/admin/tool/guardianlink/admin/roletypes.php'),
        get_string('admin_roletypes', 'tool_guardianlink')
    ),
    html_writer::link(
        new moodle_url('/admin/tool/guardianlink/admin/integrations.php'),
        get_string('admin_integrations', 'tool_guardianlink')
    ),
    html_writer::link(
        new moodle_url('/admin/tool/guardianlink/admin/audit.php'),
        get_string('admin_audit', 'tool_guardianlink')
    ),
];
echo html_writer::alist($links);

echo $OUTPUT->footer();

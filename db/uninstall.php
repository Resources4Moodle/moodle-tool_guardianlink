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
 * Clean uninstall for GuardianLink.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Remove everything the plugin created that Moodle does not drop automatically.
 *
 * Moodle drops the plugin's own tables (db/install.xml), its config, capabilities, scheduled
 * tasks and message providers on uninstall. The one artefact it does NOT remove is the dedicated
 * "guardianlinkadult" role we provision on install (and its user/course-context assignments).
 * Deleting the role here removes the role, all its assignments and its capability rows, so the
 * plugin leaves no trace behind.
 *
 * @return bool
 */
function xmldb_tool_guardianlink_uninstall(): bool {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/lib/accesslib.php');

    // Use the literal shortname so this works even if class autoloading is unavailable at uninstall.
    $roleid = $DB->get_field('role', 'id', ['shortname' => 'guardianlinkadult']);
    if ($roleid) {
        delete_role((int)$roleid);
    }
    return true;
}

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
 * Post-install defaults for GuardianLink.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Seed default relationship type definitions after installation.
 *
 * @return bool
 */
function xmldb_tool_guardianlink_install(): bool {
    \tool_guardianlink\local\relationship_service::ensure_default_relationship_types();
    \tool_guardianlink\local\relationship_service::ensure_default_profiles();
    // Core creates this plugin's capabilities AFTER db/install.php runs, so force them to exist
    // now; otherwise the role provisioning below cannot assign the plugin's own read capabilities.
    update_capabilities('tool_guardianlink');
    // Provision the dedicated authorised-adult role (assignable in a learner's user
    // context, login-as prohibited). This is how the plugin "enables the parent role"
    // on install without ever touching or enabling Moodle's core roles.
    \tool_guardianlink\local\setup::ensure_guardian_role();
    return true;
}

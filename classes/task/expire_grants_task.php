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
 * Expire time-limited GuardianLink grants.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guardianlink\task;

/**
 * Expire ended relationship and scope grants.
 */
class expire_grants_task extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name(): string {
        return 'GuardianLink expire time-limited grants';
    }

    /**
     * Execute task.
     */
    public function execute(): void {
        $count = \tool_guardianlink\local\relationship_service::expire_due_grants();
        mtrace('GuardianLink expired grants count: ' . $count);
        // Backstop: revoke any course-context assisted-access grant left dangling by an abandoned
        // session (e.g. browser closed without logging out). Proactive revocation also happens on
        // the time-cap and on logout; this sweeps the remainder.
        $maxage = \tool_guardianlink\local\relationship_service::assisted_max_seconds();
        \tool_guardianlink\local\setup::cleanup_stale_assist_roles($maxage > 0 ? $maxage : HOURSECS);
        mtrace('GuardianLink stale assisted-access grants swept.');
    }
}

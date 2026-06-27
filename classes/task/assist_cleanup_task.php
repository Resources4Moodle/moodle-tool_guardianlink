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
 * Sweep stale assisted-access course grants.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guardianlink\task;

/**
 * Frequently revoke any course-context assisted-access grant whose live session has stopped
 * heartbeating (logout or browser-close). Live sessions refresh their grant each request, so this
 * only ever revokes grants belonging to ended sessions, bounding standing access to minutes.
 */
class assist_cleanup_task extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_assist_cleanup', 'tool_guardianlink');
    }

    /**
     * Execute task.
     */
    public function execute(): void {
        // 15-minute staleness: well above any request gap of a live session, low enough to bound exposure.
        \tool_guardianlink\local\setup::cleanup_stale_assist_roles(15 * MINSECS);
        mtrace('GuardianLink assisted-access grants swept (stale > 15 min).');
    }
}

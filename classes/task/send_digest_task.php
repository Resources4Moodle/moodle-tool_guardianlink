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
 * Send GuardianLink progress digests.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guardianlink\task;

use tool_guardianlink\local\message_service;
use tool_guardianlink\local\relationship_service;

/**
 * Scheduled task for adult progress digests.
 */
class send_digest_task extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_send_digest', 'tool_guardianlink');
    }

    /**
     * Send due digests through Moodle messaging without exposing contact details to teachers.
     */
    public function execute(): void {
        $preferences = relationship_service::get_due_digest_preferences(100);
        $sent = 0;
        $failed = 0;
        foreach ($preferences as $preference) {
            try {
                if (message_service::send_guardian_digest($preference)) {
                    $sent++;
                }
            } catch (\Throwable $e) {
                $failed++;
                relationship_service::log_access(
                    (int)$preference->guardianid,
                    (int)$preference->childid,
                    'digest_failed',
                    0,
                    'digestpref',
                    (int)$preference->id,
                    ['message' => $e->getMessage()],
                    'error'
                );
                mtrace('GuardianLink digest failed for preference ' . (int)$preference->id . ': ' . $e->getMessage());
            }
        }
        mtrace('GuardianLink digests sent: ' . $sent . '; failed: ' . $failed . '.');
    }
}

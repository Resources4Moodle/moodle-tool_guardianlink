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
 * Review upcoming GuardianLink grants and notify reviewers.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guardianlink\task;

use tool_guardianlink\local\relationship_service;

/**
 * Daily task: notify reviewers of delegated grants due for review within 14 days.
 */
class review_expiring_grants_task extends \core\task\scheduled_task {
    /**
     * Task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_review_expiring', 'tool_guardianlink');
    }

    /**
     * Execute task.
     */
    public function execute(): void {
        global $DB;
        $leaddays = (int)get_config('tool_guardianlink', 'reviewleaddays');
        if ($leaddays <= 0) {
            $leaddays = 14;
        }
        $soon = time() + ($leaddays * DAYSECS);
        $count = $DB->count_records_select('tool_guardianlink_rel', 'status = :status AND reviewtime > 0 AND reviewtime <= :soon', [
            'status' => relationship_service::STATUS_ACTIVE,
            'soon' => $soon,
        ]);
        mtrace('GuardianLink relationships needing review within 14 days: ' . $count);
        if ($count === 0) {
            return;
        }

        // Notify site-level reviewers (holders of the manage capability) once each.
        $context = \context_system::instance();
        $reviewers = get_users_by_capability($context, 'tool/guardianlink:manage', 'u.id, u.firstname, u.lastname');
        $url = new \moodle_url('/admin/tool/guardianlink/admin/relationships.php');
        $sent = 0;
        foreach ($reviewers as $reviewer) {
            $message = new \core\message\message();
            $message->component = 'tool_guardianlink';
            $message->name = 'access_review';
            $message->userfrom = \core_user::get_noreply_user();
            $message->userto = $reviewer;
            $message->subject = get_string('reviewsubject', 'tool_guardianlink', $count);
            $message->fullmessage = get_string('reviewbody', 'tool_guardianlink', $count);
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = format_text(get_string('reviewbody', 'tool_guardianlink', $count), FORMAT_PLAIN);
            $message->smallmessage = get_string('reviewsubject', 'tool_guardianlink', $count);
            $message->notification = 1;
            $message->contexturl = $url->out(false);
            $message->contexturlname = get_string('admin_relationships', 'tool_guardianlink');
            if (message_send($message)) {
                $sent++;
            }
        }
        mtrace('GuardianLink review reminders sent to ' . $sent . ' reviewer(s).');
    }
}

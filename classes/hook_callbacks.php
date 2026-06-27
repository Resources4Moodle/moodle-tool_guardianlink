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
 * Hook callbacks for GuardianLink.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guardianlink;

/**
 * Hook callbacks.
 */
class hook_callbacks {
    /**
     * Defence-in-depth for assisted sessions on non-page request types. Dedicated web-service
     * endpoints dispatch external functions via validate_context() and never call require_login(),
     * so the per-page assessment block never runs for them. This refuses the token web-service
     * server endpoints while a GuardianLink assisted session is active. UI AJAX is left intact.
     *
     * @param \core\hook\after_config $hook
     */
    public static function after_config(\core\hook\after_config $hook): void {
        global $SESSION;
        if (empty($SESSION) || empty($SESSION->tool_guardianlink_assisted)) {
            return;
        }
        if (defined('WS_SERVER') && WS_SERVER) {
            throw new \moodle_exception('assistedwsblocked', 'tool_guardianlink');
        }
    }
}

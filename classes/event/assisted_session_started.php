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
 * GuardianLink event: assisted_session_started.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guardianlink\event;

/**
 * Event: a governed assisted (parent/caregiver co-login as learner) session started.
 */
class assisted_session_started extends \core\event\base {
    /**
     * Init event metadata.
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Localised event name.
     * @return string
     */
    public static function get_name() {
        return get_string('event_assisted_session_started', 'tool_guardianlink');
    }

    /**
     * Human-readable description for the log.
     * @return string
     */
    public function get_description() {
        return "The user with id '{$this->userid}' started a governed assisted session, working as the learner " .
               "with id '{$this->relateduserid}' in course '{$this->courseid}'. Core login-as is used (real user recorded); " .
               "assessed activities are blocked for the session.";
    }
}

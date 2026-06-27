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
 * External function: read audit events.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guardianlink\external;

use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use tool_guardianlink\local\relationship_service;

/**
 * Read recent GuardianLink audit events for governance integrations.
 */
class get_audit_events extends base {
    /**
     * Define parameters for execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'since' => new external_value(PARAM_INT, 'Unix timestamp lower bound', VALUE_OPTIONAL),
            'limit' => new external_value(PARAM_INT, 'Maximum records, capped at 1000', VALUE_OPTIONAL),
        ]);
    }

    /**
     * Read recent audit events.
     *
     * @param int $since
     * @param int $limit
     * @return array
     */
    public static function execute(int $since = 0, int $limit = 100): array {
        $params = self::validate_parameters(self::execute_parameters(), ['since' => $since, 'limit' => $limit]);
        self::require_api_access();
        $records = relationship_service::get_recent_audit(max(1, min(1000, $params['limit'])), (int)$params['since']);
        $items = [];
        foreach ($records as $record) {
            $items[] = [
                'id' => (int)$record->id,
                'actorid' => (int)$record->actorid,
                'learnerid' => (int)$record->childid,
                'relationshipid' => (int)$record->relationshipid,
                'courseid' => (int)$record->courseid,
                'action' => (string)$record->action,
                'targettype' => (string)$record->targettype,
                'targetid' => (int)$record->targetid,
                'result' => (string)$record->result,
                'sourcecode' => (string)$record->sourcecode,
                'timecreated' => (int)$record->timecreated,
            ];
        }
        return ['events' => $items];
    }

    /**
     * Define the return structure for execute().
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'events' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Audit event id'),
                'actorid' => new external_value(PARAM_INT, 'Actor user id'),
                'learnerid' => new external_value(PARAM_INT, 'Learner user id'),
                'relationshipid' => new external_value(PARAM_INT, 'Relationship id'),
                'courseid' => new external_value(PARAM_INT, 'Course id'),
                'action' => new external_value(PARAM_TEXT, 'Action'),
                'targettype' => new external_value(PARAM_TEXT, 'Target type'),
                'targetid' => new external_value(PARAM_INT, 'Target id'),
                'result' => new external_value(PARAM_TEXT, 'Result'),
                'sourcecode' => new external_value(PARAM_TEXT, 'Source code'),
                'timecreated' => new external_value(PARAM_INT, 'Created time'),
            ])),
        ]);
    }
}

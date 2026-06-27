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
 * External function: return current adult user's linked learners.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guardianlink\external;

use context_system;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use tool_guardianlink\local\relationship_service;

/**
 * Mobile/dashboard function for the currently logged-in authorised adult.
 */
class get_my_learners extends external_api {
    /**
     * Define parameters for execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Return the linked learners for the current authorised adult.
     *
     * @return array
     */
    public static function execute(): array {
        global $USER;
        self::validate_context(context_system::instance());
        $learners = relationship_service::get_learners_for_adult((int)$USER->id);
        $items = [];
        foreach ($learners as $learner) {
            $items[] = [
                'relationshipid' => (int)$learner->relationshipid,
                'learnerid' => (int)$learner->childid,
                'fullname' => fullname($learner),
                'reltype' => (string)$learner->reltype,
                'authoritystatus' => (string)$learner->authoritystatus,
                'confidentiality' => (string)$learner->confidentiality,
            ];
        }
        return ['learners' => $items];
    }

    /**
     * Define the return structure for execute().
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'learners' => new external_multiple_structure(new external_single_structure([
                'relationshipid' => new external_value(PARAM_INT, 'Relationship id'),
                'learnerid' => new external_value(PARAM_INT, 'Learner user id'),
                'fullname' => new external_value(PARAM_TEXT, 'Learner full name'),
                'reltype' => new external_value(PARAM_ALPHANUMEXT, 'Relationship type'),
                'authoritystatus' => new external_value(PARAM_ALPHANUMEXT, 'Authority status'),
                'confidentiality' => new external_value(PARAM_ALPHANUMEXT, 'Confidentiality level'),
            ])),
        ]);
    }
}

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
 * External function: upsert health/care summaries.
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
 * Create or update restricted health and care summaries from ERP/SIS/welfare systems.
 */
class upsert_health_records extends base {
    /**
     * Define parameters for execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sourcecode' => new external_value(PARAM_ALPHANUMEXT, 'Source system code'),
            'records' => new external_multiple_structure(new external_single_structure([
                'learnerid' => new external_value(PARAM_INT, 'Learner Moodle user id', VALUE_OPTIONAL),
                'learneridnumber' => new external_value(
                    PARAM_TEXT,
                    'Learner idnumber, username, or email lookup value',
                    VALUE_OPTIONAL
                ),
                'childid' => new external_value(PARAM_INT, 'Legacy alias for learnerid', VALUE_OPTIONAL),
                'childidnumber' => new external_value(PARAM_TEXT, 'Legacy alias for learneridnumber', VALUE_OPTIONAL),
                'courseid' => new external_value(PARAM_INT, 'Optional related course id', VALUE_OPTIONAL),
                'externalid' => new external_value(PARAM_TEXT, 'External health/care item id', VALUE_OPTIONAL),
                'healthtype' => new external_value(
                    PARAM_ALPHANUMEXT,
                    'allergy, medication, care_plan, wellbeing, access_need, emergency, care_note',
                    VALUE_OPTIONAL
                ),
                'title' => new external_value(PARAM_TEXT, 'Short title'),
                'summary' => new external_value(PARAM_TEXT, 'Minimal support summary', VALUE_OPTIONAL),
                'severity' => new external_value(PARAM_ALPHANUMEXT, 'routine, important, urgent', VALUE_OPTIONAL),
                'visibility' => new external_value(
                    PARAM_ALPHANUMEXT,
                    'legal_guardian, authorised_care, emergency_only, restricted_staff',
                    VALUE_OPTIONAL
                ),
                'status' => new external_value(PARAM_ALPHANUMEXT, 'pending, active, suspended, revoked, expired', VALUE_OPTIONAL),
                'starttime' => new external_value(PARAM_INT, 'Start timestamp', VALUE_OPTIONAL),
                'endtime' => new external_value(PARAM_INT, 'End timestamp', VALUE_OPTIONAL),
                'reviewtime' => new external_value(PARAM_INT, 'Review timestamp', VALUE_OPTIONAL),
            ])),
        ]);
    }

    /**
     * Create or update health and care summaries from the supplied payload.
     *
     * @param string $sourcecode
     * @param array $records
     * @return array
     */
    public static function execute(string $sourcecode, array $records): array {
        global $USER;
        $params = self::validate_parameters(
            self::execute_parameters(),
            ['sourcecode' => $sourcecode, 'records' => $records]
        );
        self::require_api_access();
        $ids = [];
        $errors = [];
        $succeeded = 0;
        foreach ($params['records'] as $index => $record) {
            try {
                $record['sourcecode'] = $params['sourcecode'];
                $ids[] = relationship_service::upsert_health_record($record, (int)$USER->id);
                $succeeded++;
            } catch (\Throwable $e) {
                $errors[] = 'Row ' . $index . ': ' . $e->getMessage();
            }
        }
        relationship_service::log_sync_event(
            $params['sourcecode'],
            'health',
            'upsert',
            empty($errors) ? 'success' : 'partial',
            count($params['records']),
            $succeeded,
            count($errors),
            (int)$USER->id,
            implode('; ', $errors)
        );
        return [
            'processed' => count($params['records']),
            'succeeded' => $succeeded,
            'failed' => count($errors),
            'ids' => $ids,
            'errors' => $errors,
        ];
    }

    /**
     * Define the return structure for execute().
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return self::batch_result_structure();
    }
}

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
 * External function: read relationship records.
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
 * Read relationships for ERP reconciliation or governance reporting.
 */
class get_relationships extends base {
    /**
     * Define parameters for execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sourcecode' => new external_value(PARAM_ALPHANUMEXT, 'Source system filter', VALUE_OPTIONAL),
            'externalid' => new external_value(PARAM_TEXT, 'External relationship id filter', VALUE_OPTIONAL),
            'adultid' => new external_value(PARAM_INT, 'Adult Moodle user id filter', VALUE_OPTIONAL),
            'learnerid' => new external_value(PARAM_INT, 'Learner Moodle user id filter', VALUE_OPTIONAL),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Status filter', VALUE_OPTIONAL),
            'limit' => new external_value(PARAM_INT, 'Maximum records, capped at 1000', VALUE_OPTIONAL),
        ]);
    }

    /**
     * Read relationship records matching the supplied filters.
     *
     * @param string $sourcecode
     * @param string $externalid
     * @param int $adultid
     * @param int $learnerid
     * @param string $status
     * @param int $limit
     * @return array
     */
    public static function execute(
        string $sourcecode = '',
        string $externalid = '',
        int $adultid = 0,
        int $learnerid = 0,
        string $status = '',
        int $limit = 100
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'sourcecode' => $sourcecode,
            'externalid' => $externalid,
            'adultid' => $adultid,
            'learnerid' => $learnerid,
            'status' => $status,
            'limit' => $limit,
        ]);
        self::require_api_access();
        $filters = [
            'sourcecode' => $params['sourcecode'],
            'externalid' => $params['externalid'],
            'guardianid' => $params['adultid'],
            'childid' => $params['learnerid'],
            'status' => $params['status'],
        ];
        $records = relationship_service::get_relationships($filters, max(1, min(1000, $params['limit'])));
        $items = [];
        foreach ($records as $record) {
            $items[] = [
                'id' => (int)$record->id,
                'adultid' => (int)$record->guardianid,
                'learnerid' => (int)$record->childid,
                'reltype' => (string)$record->reltype,
                'relcategory' => (string)$record->relcategory,
                'legal' => (int)$record->legal,
                'authoritybasis' => (string)$record->authoritybasis,
                'authoritystatus' => (string)$record->authoritystatus,
                'confidentiality' => (string)$record->confidentiality,
                'status' => (string)$record->status,
                'sourcecode' => (string)$record->sourcecode,
                'externalid' => (string)$record->externalid,
                'starttime' => (int)$record->starttime,
                'endtime' => (int)$record->endtime,
                'reviewtime' => (int)$record->reviewtime,
                'timemodified' => (int)$record->timemodified,
            ];
        }
        return ['relationships' => $items];
    }

    /**
     * Define the return structure for execute().
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'relationships' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Relationship id'),
                'adultid' => new external_value(PARAM_INT, 'Adult Moodle user id'),
                'learnerid' => new external_value(PARAM_INT, 'Learner Moodle user id'),
                'reltype' => new external_value(PARAM_ALPHANUMEXT, 'Relationship type'),
                'relcategory' => new external_value(PARAM_ALPHANUMEXT, 'Relationship category'),
                'legal' => new external_value(PARAM_INT, 'Legal responsibility flag'),
                'authoritybasis' => new external_value(PARAM_ALPHANUMEXT, 'Authority basis'),
                'authoritystatus' => new external_value(PARAM_ALPHANUMEXT, 'Authority status'),
                'confidentiality' => new external_value(PARAM_ALPHANUMEXT, 'Confidentiality level'),
                'status' => new external_value(PARAM_ALPHANUMEXT, 'Status'),
                'sourcecode' => new external_value(PARAM_TEXT, 'Source code'),
                'externalid' => new external_value(PARAM_TEXT, 'External id'),
                'starttime' => new external_value(PARAM_INT, 'Start time'),
                'endtime' => new external_value(PARAM_INT, 'End time'),
                'reviewtime' => new external_value(PARAM_INT, 'Review time'),
                'timemodified' => new external_value(PARAM_INT, 'Modified time'),
            ])),
        ]);
    }
}

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
 * External function: upsert care/residential/tutoring organisations.
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
 * Create or update hostel, residential care, orphanage, welfare, and tutoring organisations.
 */
class upsert_organisations extends base {
    /**
     * Define parameters for execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sourcecode' => new external_value(PARAM_ALPHANUMEXT, 'Source system code'),
            'organisations' => new external_multiple_structure(new external_single_structure([
                'externalid' => new external_value(PARAM_TEXT, 'External organisation id', VALUE_OPTIONAL),
                'orgtype' => new external_value(
                    PARAM_ALPHANUMEXT,
                    'hostel, boarding_house, orphanage, childrens_home, troubled_home_support, tutoring, welfare, other',
                    VALUE_OPTIONAL
                ),
                'name' => new external_value(PARAM_TEXT, 'Organisation name'),
                'status' => new external_value(PARAM_ALPHANUMEXT, 'active, suspended, revoked', VALUE_OPTIONAL),
                'contactsummary' => new external_value(PARAM_TEXT, 'Minimal institutional contact summary', VALUE_OPTIONAL),
                'notes' => new external_value(PARAM_TEXT, 'Short governance note', VALUE_OPTIONAL),
            ])),
        ]);
    }

    /**
     * Create or update organisations from the supplied payload.
     *
     * @param string $sourcecode
     * @param array $organisations
     * @return array
     */
    public static function execute(string $sourcecode, array $organisations): array {
        global $USER;
        $params = self::validate_parameters(
            self::execute_parameters(),
            ['sourcecode' => $sourcecode, 'organisations' => $organisations]
        );
        self::require_api_access();
        $ids = [];
        $errors = [];
        $succeeded = 0;
        foreach ($params['organisations'] as $index => $record) {
            try {
                $record['sourcecode'] = $params['sourcecode'];
                $ids[] = relationship_service::upsert_organisation($record, (int)$USER->id);
                $succeeded++;
            } catch (\Throwable $e) {
                $errors[] = 'Row ' . $index . ': ' . $e->getMessage();
            }
        }
        relationship_service::log_sync_event(
            $params['sourcecode'],
            'organisation',
            'upsert',
            empty($errors) ? 'success' : 'partial',
            count($params['organisations']),
            $succeeded,
            count($errors),
            (int)$USER->id,
            implode('; ', $errors)
        );
        return [
            'processed' => count($params['organisations']),
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

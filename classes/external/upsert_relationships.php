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
 * External function: upsert relationships.
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
 * Create/update authorised adult-to-learner relationships from ERP/SIS systems.
 */
class upsert_relationships extends base {
    /**
     * Define parameters for execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sourcecode' => new external_value(PARAM_ALPHANUMEXT, 'Source system code, for example SIS or ERP'),
            'relationships' => new external_multiple_structure(self::relationship_structure()),
        ]);
    }

    /**
     * Create or update relationships from the supplied payload.
     *
     * @param string $sourcecode
     * @param array $relationships
     * @return array
     */
    public static function execute(string $sourcecode, array $relationships): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'sourcecode' => $sourcecode,
            'relationships' => $relationships,
        ]);
        self::require_api_access();
        $ids = [];
        $errors = [];
        $succeeded = 0;
        foreach ($params['relationships'] as $index => $record) {
            try {
                $record['sourcecode'] = $params['sourcecode'];
                $ids[] = relationship_service::add_or_update_relationship($record, (int)$USER->id, true);
                $succeeded++;
            } catch (\Throwable $e) {
                $errors[] = 'Row ' . $index . ': ' . $e->getMessage();
            }
        }
        relationship_service::log_sync_event(
            $params['sourcecode'],
            'relationship',
            'upsert',
            empty($errors) ? 'success' : 'partial',
            count($params['relationships']),
            $succeeded,
            count($errors),
            (int)$USER->id,
            implode('; ', $errors)
        );
        return [
            'processed' => count($params['relationships']),
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

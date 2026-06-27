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
 * External function: revoke relationships.
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
 * Revoke/suspend relationships by external identifier.
 */
class revoke_relationships extends base {
    /**
     * Define parameters for execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'sourcecode' => new external_value(PARAM_ALPHANUMEXT, 'Source system code'),
            'relationships' => new external_multiple_structure(new external_single_structure([
                'externalid' => new external_value(PARAM_TEXT, 'External relationship id'),
                'reason' => new external_value(PARAM_TEXT, 'Revocation reason', VALUE_OPTIONAL),
            ])),
        ]);
    }

    /**
     * Revoke or suspend relationships referenced by external identifier.
     *
     * @param string $sourcecode
     * @param array $relationships
     * @return array
     */
    public static function execute(string $sourcecode, array $relationships): array {
        global $USER;
        $params = self::validate_parameters(
            self::execute_parameters(),
            ['sourcecode' => $sourcecode, 'relationships' => $relationships]
        );
        self::require_api_access();
        $ids = [];
        $errors = [];
        $succeeded = 0;
        foreach ($params['relationships'] as $index => $record) {
            try {
                $revoked = relationship_service::revoke_relationship_by_externalid(
                    $params['sourcecode'],
                    $record['externalid'],
                    (int)$USER->id,
                    $record['reason'] ?? 'Revoked by API'
                );
                if ($revoked) {
                    $succeeded++;
                } else {
                    $errors[] = 'Row ' . $index . ': external relationship not found';
                }
            } catch (\Throwable $e) {
                $errors[] = 'Row ' . $index . ': ' . $e->getMessage();
            }
        }
        relationship_service::log_sync_event(
            $params['sourcecode'],
            'relationship',
            'revoke',
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

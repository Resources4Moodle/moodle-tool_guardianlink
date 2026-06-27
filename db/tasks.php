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
 * Scheduled task definitions for GuardianLink.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'tool_guardianlink\\task\\expire_grants_task',
        'blocking' => 0,
        'minute' => 'R',
        'hour' => '*/2',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
    [
        // Frequent sweep of stale assisted-access course grants (security-critical lifecycle).
        'classname' => 'tool_guardianlink\\task\\assist_cleanup_task',
        'blocking' => 0,
        'minute' => '*/10',
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
    [
        'classname' => 'tool_guardianlink\\task\\review_expiring_grants_task',
        'blocking' => 0,
        'minute' => 'R',
        'hour' => '5',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
    [
        'classname' => 'tool_guardianlink\\task\\send_digest_task',
        'blocking' => 0,
        'minute' => 'R',
        'hour' => '18',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*',
    ],
    [
        'classname' => 'tool_guardianlink\\task\\purge_audit_task',
        'blocking' => 0,
        'minute' => 'R',
        'hour' => '3',
        'day' => '1',
        'month' => '*',
        'dayofweek' => '*',
    ],
];

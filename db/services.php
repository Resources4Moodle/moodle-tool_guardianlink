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
 * External web service declarations for ERP/SIS/mobile integration.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'tool_guardianlink_upsert_relationships' => [
        'classname' => 'tool_guardianlink\\external\\upsert_relationships',
        'description' => 'Create or update authorised adult-to-learner relationships and course scopes from an ERP/SIS.',
        'type' => 'write',
        'ajax' => false,
    ],
    'tool_guardianlink_revoke_relationships' => [
        'classname' => 'tool_guardianlink\\external\\revoke_relationships',
        'description' => 'Revoke relationship grants by external identifiers.',
        'type' => 'write',
        'ajax' => false,
    ],
    'tool_guardianlink_get_relationships' => [
        'classname' => 'tool_guardianlink\\external\\get_relationships',
        'description' => 'Read relationship records for audit and ERP reconciliation.',
        'type' => 'read',
        'ajax' => false,
    ],
    'tool_guardianlink_upsert_health_records' => [
        'classname' => 'tool_guardianlink\\external\\upsert_health_records',
        'description' => 'Create or update restricted learner health and care summaries.',
        'type' => 'write',
        'ajax' => false,
    ],
    'tool_guardianlink_upsert_organisations' => [
        'classname' => 'tool_guardianlink\\external\\upsert_organisations',
        'description' => 'Create or update hostel, residential care, orphanage, welfare, and tutoring organisations.',
        'type' => 'write',
        'ajax' => false,
    ],
    'tool_guardianlink_get_audit_events' => [
        'classname' => 'tool_guardianlink\\external\\get_audit_events',
        'description' => 'Read GuardianLink audit events for governance integrations.',
        'type' => 'read',
        'ajax' => false,
    ],
    'tool_guardianlink_get_my_learners' => [
        'classname' => 'tool_guardianlink\\external\\get_my_learners',
        'description' => 'Return the current authorised adult user\'s linked learners for web/mobile dashboards.',
        'type' => 'read',
        'ajax' => true,
        'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'tool_guardianlink_get_bulk_recipients' => [
        'classname' => 'tool_guardianlink\\external\\get_bulk_recipients',
        'description' => 'Resolve the authorised-adult recipients of an audience (course/category/cohort) '
            . 'without exposing contact details.',
        'type' => 'read',
        'ajax' => false,
    ],
    'tool_guardianlink_send_bulk_message' => [
        'classname' => 'tool_guardianlink\\external\\send_bulk_message',
        'description' => 'Send a bulk message to the authorised adults of an audience; recipients and '
            . 'delivery are handled server-side.',
        'type' => 'write',
        'ajax' => false,
    ],
];

$services = [
    'GuardianLink ERP sync service' => [
        'functions' => [
            'tool_guardianlink_upsert_relationships',
            'tool_guardianlink_revoke_relationships',
            'tool_guardianlink_get_relationships',
            'tool_guardianlink_upsert_health_records',
            'tool_guardianlink_upsert_organisations',
            'tool_guardianlink_get_audit_events',
            'tool_guardianlink_get_bulk_recipients',
            'tool_guardianlink_send_bulk_message',
        ],
        'restrictedusers' => 1,
        'enabled' => 0,
        'shortname' => 'guardianlink_erp',
    ],
];

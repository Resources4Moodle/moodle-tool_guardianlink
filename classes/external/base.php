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
 * Shared external-service helpers for GuardianLink.
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

/**
 * Shared external API helpers.
 */
abstract class base extends external_api {
    /**
     * Validate system context and integration capability.
     */
    protected static function require_api_access(): void {
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('tool/guardianlink:sync', $context);
    }

    /**
     * Common import result structure.
     *
     * @return external_single_structure
     */
    protected static function batch_result_structure(): external_single_structure {
        return new external_single_structure([
            'processed' => new external_value(PARAM_INT, 'Rows received'),
            'succeeded' => new external_value(PARAM_INT, 'Rows successfully processed'),
            'failed' => new external_value(PARAM_INT, 'Rows that failed'),
            'ids' => new external_multiple_structure(new external_value(PARAM_INT, 'Created or updated local id')),
            'errors' => new external_multiple_structure(new external_value(PARAM_TEXT, 'Per-row error message')),
        ]);
    }

    /**
     * Common relationship payload definition.
     *
     * @return external_single_structure
     */
    protected static function relationship_structure(): external_single_structure {
        return new external_single_structure([
            'adultid' => new external_value(PARAM_INT, 'Moodle user id for authorised adult', VALUE_OPTIONAL),
            'adultidnumber' => new external_value(PARAM_TEXT, 'Adult idnumber, username, or email lookup value', VALUE_OPTIONAL),
            'guardianid' => new external_value(PARAM_INT, 'Legacy alias for adultid', VALUE_OPTIONAL),
            'guardianidnumber' => new external_value(PARAM_TEXT, 'Legacy alias for adultidnumber', VALUE_OPTIONAL),
            'learnerid' => new external_value(PARAM_INT, 'Moodle user id for learner', VALUE_OPTIONAL),
            'learneridnumber' => new external_value(
                PARAM_TEXT,
                'Learner idnumber, username, or email lookup value',
                VALUE_OPTIONAL
            ),
            'childid' => new external_value(PARAM_INT, 'Legacy alias for learnerid', VALUE_OPTIONAL),
            'childidnumber' => new external_value(PARAM_TEXT, 'Legacy alias for learneridnumber', VALUE_OPTIONAL),
            'sourcecode' => new external_value(PARAM_ALPHANUMEXT, 'ERP/SIS/source code', VALUE_OPTIONAL),
            'externalid' => new external_value(PARAM_TEXT, 'External relationship id', VALUE_OPTIONAL),
            'sourcerevision' => new external_value(PARAM_TEXT, 'External revision/version marker', VALUE_OPTIONAL),
            'tenantkey' => new external_value(PARAM_ALPHANUMEXT, 'Optional tenant/campus key', VALUE_OPTIONAL),
            'reltype' => new external_value(PARAM_ALPHANUMEXT, 'Relationship type code', VALUE_OPTIONAL),
            'relcategory' => new external_value(PARAM_ALPHANUMEXT, 'Relationship category', VALUE_OPTIONAL),
            'legal' => new external_value(PARAM_BOOL, 'Legal/parental responsibility flag where applicable', VALUE_OPTIONAL),
            'authoritybasis' => new external_value(
                PARAM_ALPHANUMEXT,
                'school_record, court_order, care_order, hostel_record, consent, contract, etc.',
                VALUE_OPTIONAL
            ),
            'authoritystatus' => new external_value(
                PARAM_ALPHANUMEXT,
                'verified, unverified, restricted, disputed, revoked',
                VALUE_OPTIONAL
            ),
            'confidentiality' => new external_value(
                PARAM_ALPHANUMEXT,
                'standard, restricted, sensitive, safeguarding',
                VALUE_OPTIONAL
            ),
            'householdkey' => new external_value(
                PARAM_TEXT,
                'Household grouping key, not displayed to other adults',
                VALUE_OPTIONAL
            ),
            'contactgroupkey' => new external_value(PARAM_TEXT, 'Safe contact grouping key', VALUE_OPTIONAL),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'pending, active, suspended, revoked, expired', VALUE_OPTIONAL),
            'consentstatus' => new external_value(PARAM_ALPHANUMEXT, 'Consent/policy status', VALUE_OPTIONAL),
            'accessprofile' => new external_value(PARAM_ALPHANUMEXT, 'Default access profile', VALUE_OPTIONAL),
            'starttime' => new external_value(PARAM_INT, 'Unix timestamp start', VALUE_OPTIONAL),
            'endtime' => new external_value(PARAM_INT, 'Unix timestamp end', VALUE_OPTIONAL),
            'reviewtime' => new external_value(PARAM_INT, 'Unix timestamp for review', VALUE_OPTIONAL),
            'restrictionsjson' => new external_value(PARAM_RAW, 'JSON restrictions metadata', VALUE_OPTIONAL),
            'rightsjson' => new external_value(PARAM_RAW, 'JSON rights/responsibilities metadata', VALUE_OPTIONAL),
            'notes' => new external_value(
                PARAM_TEXT,
                'Short internal note, not a place for full court/safeguarding files',
                VALUE_OPTIONAL
            ),
            'scopes' => new external_multiple_structure(self::scope_structure(), 'Course/category scopes', VALUE_OPTIONAL),
        ]);
    }

    /**
     * Course/category scope payload.
     *
     * @return external_single_structure
     */
    protected static function scope_structure(): external_single_structure {
        return new external_single_structure([
            'scopekind' => new external_value(PARAM_ALPHANUMEXT, 'course, category, learner, site', VALUE_OPTIONAL),
            'courseid' => new external_value(PARAM_INT, 'Course id', VALUE_OPTIONAL),
            'categoryid' => new external_value(PARAM_INT, 'Course category id', VALUE_OPTIONAL),
            'status' => new external_value(PARAM_ALPHANUMEXT, 'Scope status', VALUE_OPTIONAL),
            'allowoverview' => new external_value(PARAM_BOOL, 'Overview permission', VALUE_OPTIONAL),
            'allowgrades' => new external_value(PARAM_BOOL, 'Grades permission', VALUE_OPTIONAL),
            'allowcompletion' => new external_value(PARAM_BOOL, 'Completion permission', VALUE_OPTIONAL),
            'allowactivities' => new external_value(PARAM_BOOL, 'Activities permission', VALUE_OPTIONAL),
            'allowattendance' => new external_value(PARAM_BOOL, 'Attendance permission', VALUE_OPTIONAL),
            'allowcalendar' => new external_value(PARAM_BOOL, 'Calendar permission', VALUE_OPTIONAL),
            'allowteachercontact' => new external_value(PARAM_BOOL, 'Teacher contact permission', VALUE_OPTIONAL),
            'allowmessaging' => new external_value(PARAM_BOOL, 'Proxy messaging permission', VALUE_OPTIONAL),
            'allowassisted' => new external_value(PARAM_BOOL, 'Assisted view permission', VALUE_OPTIONAL),
            'allowhealthsummary' => new external_value(PARAM_BOOL, 'Health/care summary permission', VALUE_OPTIONAL),
            'allowtutormanagement' => new external_value(PARAM_BOOL, 'Tutor-management permission', VALUE_OPTIONAL),
            'allowpolicyconsent' => new external_value(PARAM_BOOL, 'Policy-consent permission', VALUE_OPTIONAL),
            'starttime' => new external_value(PARAM_INT, 'Scope start', VALUE_OPTIONAL),
            'endtime' => new external_value(PARAM_INT, 'Scope end', VALUE_OPTIONAL),
            'externalid' => new external_value(PARAM_TEXT, 'External scope id', VALUE_OPTIONAL),
            'policyjson' => new external_value(PARAM_RAW, 'Policy metadata JSON', VALUE_OPTIONAL),
        ]);
    }
}

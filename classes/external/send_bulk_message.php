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
 * External function: send a bulk message to an audience of authorised adults.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guardianlink\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use tool_guardianlink\local\bulk_message_service;

/**
 * Send a bulk message server-side so adult contact details are never disclosed to the caller.
 */
class send_bulk_message extends external_api {
    /**
     * Define parameters for execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'audiencetype' => new external_value(PARAM_ALPHA, 'course, category or cohort'),
            'subject' => new external_value(PARAM_TEXT, 'Message subject'),
            'message' => new external_value(PARAM_RAW, 'Message body (plain text)'),
            'courseid' => new external_value(PARAM_INT, 'Course id (for course audience)', VALUE_DEFAULT, 0),
            'categoryid' => new external_value(PARAM_INT, 'Category id (for category audience)', VALUE_DEFAULT, 0),
            'cohortid' => new external_value(PARAM_INT, 'Cohort id (for cohort audience)', VALUE_DEFAULT, 0),
            'legalonly' => new external_value(PARAM_BOOL, 'Legal-responsibility holders only', VALUE_DEFAULT, false),
            'verifiedonly' => new external_value(PARAM_BOOL, 'Verified relationships only', VALUE_DEFAULT, true),
            'excluderestricted' => new external_value(PARAM_BOOL, 'Exclude restricted-contact adults', VALUE_DEFAULT, true),
        ]);
    }

    /**
     * Send a bulk message to the resolved audience server-side.
     *
     * @param string $audiencetype
     * @param string $subject
     * @param string $message
     * @param int $courseid
     * @param int $categoryid
     * @param int $cohortid
     * @param bool $legalonly
     * @param bool $verifiedonly
     * @param bool $excluderestricted
     * @return array
     */
    public static function execute(
        string $audiencetype,
        string $subject,
        string $message,
        int $courseid = 0,
        int $categoryid = 0,
        int $cohortid = 0,
        bool $legalonly = false,
        bool $verifiedonly = true,
        bool $excluderestricted = true
    ): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'audiencetype' => $audiencetype, 'subject' => $subject, 'message' => $message,
            'courseid' => $courseid, 'categoryid' => $categoryid, 'cohortid' => $cohortid,
            'legalonly' => $legalonly, 'verifiedonly' => $verifiedonly, 'excluderestricted' => $excluderestricted,
        ]);
        $context = \context_system::instance();
        self::validate_context($context);
        require_capability('tool/guardianlink:sendbulkmessages', $context);

        $criteria = bulk_message_service::normalise_criteria($params);
        $result = bulk_message_service::send_bulk_message((int)$USER->id, $criteria, $params['subject'], $params['message']);
        return $result;
    }

    /**
     * Define the return structure for execute().
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'sent' => new external_value(PARAM_INT, 'Messages successfully sent'),
            'failed' => new external_value(PARAM_INT, 'Messages that failed'),
            'recipients' => new external_value(PARAM_INT, 'Total unique recipients resolved'),
        ]);
    }
}

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
 * Message providers for GuardianLink.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$messageproviders = [
    // Sent by staff/the system TO authorised adults (parents/guardians). The send-side authorisation
    // is enforced in the pages/services (require_capability sendproxymessages); the provider must NOT
    // gate the recipient by that capability, or the intended adult recipients could never receive it.
    'proxy_message' => [],
    'guardian_digest' => [],
    'access_review' => [
        'capability' => 'tool/guardianlink:manage',
    ],
    'tutor_request' => [],
    'bulk_message' => [],
];

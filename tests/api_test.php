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
 * Unit tests for the public GuardianLink integration API.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guardianlink;

use tool_guardianlink\local\relationship_service;

/**
 * Tests for {@see \tool_guardianlink\api}.
 *
 * @covers \tool_guardianlink\api
 */
final class api_test extends \advanced_testcase {
    /**
     * Build a course, an authorised adult and a learner with an active scoped relationship.
     *
     * @return array{course:\stdClass,adult:\stdClass,learner:\stdClass}
     */
    private function fixture(): array {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $adult = $gen->create_user(['firstname' => 'Pat', 'lastname' => 'Carer', 'email' => 'pat@example.com']);
        $learner = $gen->create_user();
        $gen->enrol_user($learner->id, $course->id);
        relationship_service::ensure_default_profiles();
        relationship_service::add_or_update_relationship([
            'adultid' => $adult->id, 'learnerid' => $learner->id, 'reltype' => 'legal_parent',
            'status' => 'active', 'accessprofile' => 'family_full', 'courseids' => (string)$course->id,
        ], 2);
        return ['course' => $course, 'adult' => $adult, 'learner' => $learner];
    }

    /**
     * Guardian lookups return identity only — never an email or phone field.
     */
    public function test_get_guardians_returns_no_contact_details(): void {
        $this->resetAfterTest();
        $f = $this->fixture();
        $guardians = api::get_guardians_for_learner($f['learner']->id, $f['course']->id);
        $this->assertCount(1, $guardians);
        $g = reset($guardians);
        $this->assertSame((int)$f['adult']->id, (int)$g->id);
        $this->assertSame('Pat Carer', $g->fullname);
        $this->assertFalse(property_exists($g, 'email'), 'API must not expose adult email.');
        $this->assertFalse(property_exists($g, 'phone1'), 'API must not expose adult phone.');
    }

    /**
     * Access and relationship predicates reflect the scope model.
     */
    public function test_access_predicates(): void {
        $this->resetAfterTest();
        $f = $this->fixture();
        $this->assertTrue(api::has_relationship($f['adult']->id, $f['learner']->id));
        $this->assertTrue(api::can_access($f['adult']->id, $f['learner']->id, $f['course']->id, 'grades'));
        $stranger = $this->getDataGenerator()->create_user();
        $this->assertFalse(api::has_relationship($stranger->id, $f['learner']->id));
    }

    /**
     * Notifying guardians dispatches through the audited messaging layer and records a thread.
     */
    public function test_notify_guardians_sends(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $f = $this->fixture();
        $sink = $this->redirectMessages();

        $result = api::notify_guardians(
            2,
            $f['learner']->id,
            $f['course']->id,
            'Subject for {learnerfullname}',
            '<p>Body for {coursename}</p>',
            false
        );

        // The contract: one reachable adult, one notice sent, and one audited thread recorded.
        $this->assertSame(1, $result['recipients']);
        $this->assertSame(1, $result['sent']);
        $this->assertSame(1, $DB->count_records('tool_guardianlink_msgthread', [
            'childid' => $f['learner']->id,
            'courseid' => $f['course']->id,
        ]));
        $sink->close();
    }
}

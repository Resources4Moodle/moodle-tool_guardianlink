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
 * Unit tests for the GuardianLink relationship/scope authorisation model.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guardianlink;

use tool_guardianlink\local\relationship_service;

/**
 * Tests for {@see \tool_guardianlink\local\relationship_service}.
 *
 * @covers \tool_guardianlink\local\relationship_service
 */
final class relationship_service_test extends \advanced_testcase {
    /**
     * Create an active "family_full" relationship scoped to one course and return the ids.
     *
     * @return array{course:\stdClass,other:\stdClass,adult:\stdClass,learner:\stdClass,relid:int}
     */
    private function make_relationship(): array {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $other = $gen->create_course();
        $adult = $gen->create_user();
        $learner = $gen->create_user();
        $gen->enrol_user($learner->id, $course->id);
        relationship_service::ensure_default_relationship_types();
        relationship_service::ensure_default_profiles();
        $relid = relationship_service::add_or_update_relationship([
            'adultid' => $adult->id,
            'learnerid' => $learner->id,
            'reltype' => 'legal_parent',
            'status' => 'active',
            'accessprofile' => 'family_full',
            'courseids' => (string)$course->id,
            'adultphone1' => '+44 7700 900111',
        ], 2);
        return ['course' => $course, 'other' => $other, 'adult' => $adult, 'learner' => $learner, 'relid' => $relid];
    }

    /**
     * A scoped relationship grants access only within its course scope, and only for permitted fields.
     */
    public function test_scope_grants_and_denies_access(): void {
        $this->resetAfterTest();
        $f = $this->make_relationship();
        $stranger = $this->getDataGenerator()->create_user();

        $this->assertGreaterThan(0, $f['relid']);
        // Overview and grades are permitted on the scoped course (family_full profile).
        $this->assertTrue(relationship_service::can_access_child($f['adult']->id, $f['learner']->id, $f['course']->id, 'overview'));
        $this->assertTrue(relationship_service::can_access_child($f['adult']->id, $f['learner']->id, $f['course']->id, 'grades'));
        // No access to a course outside the scope.
        $this->assertFalse(relationship_service::can_access_child($f['adult']->id, $f['learner']->id, $f['other']->id, 'grades'));
        // A stranger has no access at all.
        $this->assertFalse(relationship_service::can_access_child($stranger->id, $f['learner']->id, $f['course']->id, 'overview'));
        // An adult cannot "access" themselves as a learner.
        $this->assertFalse(relationship_service::can_access_child($f['adult']->id, $f['adult']->id, $f['course']->id, 'overview'));
    }

    /**
     * Proxy recipients include the authorised adult, and a restriction removes all access.
     */
    public function test_restriction_revokes_access_and_messaging(): void {
        $this->resetAfterTest();
        $f = $this->make_relationship();

        $recipients = relationship_service::get_proxy_recipients($f['learner']->id, $f['course']->id);
        $ids = array_map(fn($r) => (int)$r->id, $recipients);
        $this->assertContains((int)$f['adult']->id, $ids);

        // Restricting the relationship must cut off both access and messaging.
        relationship_service::set_restricted($f['relid'], true, 'Safeguarding hold', 2);
        $this->assertFalse(relationship_service::can_access_child($f['adult']->id, $f['learner']->id, $f['course']->id, 'grades'));
        $this->assertSame([], relationship_service::get_proxy_recipients($f['learner']->id, $f['course']->id));
    }

    /**
     * Editing the same relationship updates the record in place rather than creating a duplicate.
     */
    public function test_edit_updates_in_place(): void {
        global $DB;
        $this->resetAfterTest();
        $f = $this->make_relationship();
        $before = $DB->count_records('tool_guardianlink_rel');

        $newid = relationship_service::add_or_update_relationship([
            'relationshipid' => $f['relid'],
            'adultid' => $f['adult']->id,
            'learnerid' => $f['learner']->id,
            'reltype' => 'legal_parent',
            'status' => 'active',
            'accessprofile' => 'family_full',
            'courseids' => (string)$f['course']->id,
            'notes' => 'Updated note',
        ], 2);

        $this->assertSame($f['relid'], $newid);
        $this->assertSame($before, $DB->count_records('tool_guardianlink_rel'));
        $this->assertSame('Updated note', $DB->get_field('tool_guardianlink_rel', 'notes', ['id' => $f['relid']]));
    }

    /**
     * The adult's phone captured with the mapping is retrievable via the service (never via a page).
     */
    public function test_adult_phone_capture(): void {
        $this->resetAfterTest();
        $f = $this->make_relationship();
        $phones = relationship_service::get_adult_phones($f['adult']->id);
        $this->assertSame('+44 7700 900111', $phones['phone1']);
    }

    /**
     * The default access profiles are seeded and exposed for selection.
     */
    public function test_default_profiles_seeded(): void {
        $this->resetAfterTest();
        relationship_service::ensure_default_profiles();
        $options = relationship_service::get_profile_options();
        $this->assertArrayHasKey('family_full', $options);
        $this->assertArrayHasKey('family_basic', $options);
    }
}

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
 * Tests for GuardianLink external-identity and scope uniqueness.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guardianlink;

use tool_guardianlink\local\relationship_service as rs;

/**
 * Tests that external source identities and scopes are unique, while manual rows do not collide.
 *
 * @covers \tool_guardianlink\local\relationship_service
 */
final class schema_integrity_test extends \advanced_testcase {
    /**
     * Many manual relationships (no external ref) coexist, and one external identity maps to one row.
     */
    public function test_external_identity_uniqueness(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $learner = $gen->create_user();
        $a1 = $gen->create_user();
        $a2 = $gen->create_user();
        rs::ensure_default_profiles();

        // Two manual relationships (empty external) must both exist with NULL external refs.
        $r1 = rs::add_or_update_relationship(['adultid' => $a1->id, 'learnerid' => $learner->id,
            'reltype' => 'legal_parent', 'status' => 'active', 'accessprofile' => 'family_full'], 2);
        $r2 = rs::add_or_update_relationship(['adultid' => $a2->id, 'learnerid' => $learner->id,
            'reltype' => 'carer', 'status' => 'active', 'accessprofile' => 'family_basic'], 2);
        $this->assertNotEquals($r1, $r2);
        $this->assertNull($DB->get_field('tool_guardianlink_rel', 'sourcecode', ['id' => $r1]));

        // The same external source identity upserts the SAME relationship row (no duplicate).
        $r3 = rs::add_or_update_relationship(['adultid' => $a1->id, 'learnerid' => $learner->id,
            'reltype' => 'legal_parent', 'status' => 'active', 'accessprofile' => 'family_full',
            'sourcecode' => 'SIS', 'externalid' => 'EXT1'], 2, true);
        $r4 = rs::add_or_update_relationship(['adultid' => $a1->id, 'learnerid' => $learner->id,
            'reltype' => 'legal_parent', 'status' => 'active', 'accessprofile' => 'family_full',
            'sourcecode' => 'SIS', 'externalid' => 'EXT1'], 2, true);
        $this->assertSame($r3, $r4);
        $this->assertSame(1, $DB->count_records('tool_guardianlink_rel', ['sourcecode' => 'SIS', 'externalid' => 'EXT1']));
    }

    /**
     * Re-applying the same scope set does not create duplicate scope rows.
     */
    public function test_scope_identity_uniqueness(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $adult = $gen->create_user();
        $learner = $gen->create_user();
        rs::ensure_default_profiles();
        $relid = rs::add_or_update_relationship(['adultid' => $adult->id, 'learnerid' => $learner->id,
            'reltype' => 'legal_parent', 'status' => 'active', 'accessprofile' => 'family_full',
            'courseids' => (string)$course->id], 2);

        // Re-apply the same course scope twice — must remain a single row.
        rs::set_scopes($relid, [['scopekind' => 'course', 'courseid' => $course->id]], 2, 'family_full');
        rs::set_scopes($relid, [['scopekind' => 'course', 'courseid' => $course->id]], 2, 'family_full');
        $this->assertSame(1, $DB->count_records(
            'tool_guardianlink_scope',
            ['relationshipid' => $relid, 'scopekind' => 'course', 'courseid' => $course->id, 'categoryid' => 0]
        ));
    }

    /**
     * Course references in CSV/forms resolve by short name, ID number, or numeric id; unknown refs map to 0.
     */
    public function test_resolve_course_ref(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['shortname' => 'BIOL-101', 'idnumber' => 'SIS-BIO-1']);

        $this->assertSame((int)$course->id, rs::resolve_course_ref((string)$course->id));
        $this->assertSame((int)$course->id, rs::resolve_course_ref('BIOL-101'));
        $this->assertSame((int)$course->id, rs::resolve_course_ref('  SIS-BIO-1 '));
        $this->assertSame(0, rs::resolve_course_ref('NO-SUCH-COURSE'));
        $this->assertSame(0, rs::resolve_course_ref('999999'));
        $this->assertSame(0, rs::resolve_course_ref(''));
    }

    /**
     * A bulk/CSV grant scoped by course short name lands on the right course.
     */
    public function test_csv_courseids_accept_shortname(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['shortname' => 'CHEM-7']);
        $adult = $gen->create_user();
        $learner = $gen->create_user();
        rs::ensure_default_profiles();
        $relid = rs::add_or_update_relationship(['adultid' => $adult->id, 'learnerid' => $learner->id,
            'reltype' => 'legal_parent', 'status' => 'active', 'accessprofile' => 'family_full'], 2);

        rs::set_scopes_from_csv($relid, ['courseids' => 'CHEM-7'], 2, 'family_full');
        $this->assertSame(1, $DB->count_records(
            'tool_guardianlink_scope',
            ['relationshipid' => $relid, 'scopekind' => 'course', 'courseid' => $course->id]
        ));
    }
}

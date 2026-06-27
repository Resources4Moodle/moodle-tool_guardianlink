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
 * Unit tests for independent (unsupervised) access acknowledgements and the higher-ed observer profile.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guardianlink;

use tool_guardianlink\local\relationship_service;
use tool_guardianlink\local\supervision_service;

/**
 * Tests for {@see \tool_guardianlink\local\supervision_service} and the higher-ed observer profile.
 *
 * @covers \tool_guardianlink\local\supervision_service
 */
final class supervision_service_test extends \advanced_testcase {
    /**
     * Course, adult and learner with an active family_full relationship scoped to the course.
     *
     * @return array{course:\stdClass,adult:\stdClass,learner:\stdClass}
     */
    private function fixture(): array {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $adult = $gen->create_user();
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
     * The three keys (admin switch, teacher course switch, parent acknowledgement) must all turn.
     */
    public function test_three_key_workflow(): void {
        $this->resetAfterTest();
        $f = $this->fixture();

        // Key 1 off: nothing is allowed.
        set_config('allowindependentaccess', 0, 'tool_guardianlink');
        $this->assertFalse(supervision_service::feature_enabled());
        $this->assertFalse(supervision_service::course_allows_independent($f['course']->id));
        $this->assertFalse(supervision_service::is_independent_allowed($f['learner']->id, $f['course']->id));

        // Key 1 on, key 2 off: still not allowed.
        set_config('allowindependentaccess', 1, 'tool_guardianlink');
        $this->assertFalse(supervision_service::course_allows_independent($f['course']->id));

        // Key 2 on (teacher marks the course safe): course allows, but no acknowledgement yet.
        relationship_service::save_course_config($f['course']->id, ['allowindependentaccess' => 1], 2);
        $this->assertTrue(supervision_service::course_allows_independent($f['course']->id));
        $this->assertFalse(supervision_service::is_independent_allowed($f['learner']->id, $f['course']->id));

        // Key 3 on (parent acknowledges): now allowed.
        supervision_service::acknowledge($f['adult']->id, $f['learner']->id, $f['course']->id, true, $f['adult']->id);
        $this->assertTrue(supervision_service::is_independent_allowed($f['learner']->id, $f['course']->id));

        // Revoking returns the learner to supervised.
        supervision_service::acknowledge($f['adult']->id, $f['learner']->id, $f['course']->id, false, $f['adult']->id);
        $this->assertFalse(supervision_service::is_independent_allowed($f['learner']->id, $f['course']->id));
    }

    /**
     * A learner with no authorised-adult relationship is never treated as supervised.
     */
    public function test_no_guardian_means_not_supervised(): void {
        $this->resetAfterTest();
        set_config('allowindependentaccess', 1, 'tool_guardianlink');
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $learner = $gen->create_user();
        $gen->enrol_user($learner->id, $course->id);

        $this->assertFalse(supervision_service::child_has_guardian($learner->id));
        $this->assertTrue(supervision_service::is_independent_allowed($learner->id, $course->id));
    }

    /**
     * The parent's offered-courses list reflects the per-course switch and the acknowledgement status.
     */
    public function test_offered_courses(): void {
        $this->resetAfterTest();
        set_config('allowindependentaccess', 1, 'tool_guardianlink');
        $f = $this->fixture();

        // Not offered until the teacher marks the course.
        $this->assertSame([], supervision_service::offered_courses($f['adult']->id, $f['learner']->id));

        relationship_service::save_course_config($f['course']->id, ['allowindependentaccess' => 1], 2);
        $offered = supervision_service::offered_courses($f['adult']->id, $f['learner']->id);
        $this->assertArrayHasKey((int)$f['course']->id, $offered);
        $this->assertSame('none', $offered[(int)$f['course']->id]->status);

        supervision_service::acknowledge($f['adult']->id, $f['learner']->id, $f['course']->id, true, $f['adult']->id);
        $offered = supervision_service::offered_courses($f['adult']->id, $f['learner']->id);
        $this->assertSame('allowed', $offered[(int)$f['course']->id]->status);
    }

    /**
     * Only an adult with an active relationship to the learner may acknowledge.
     */
    public function test_acknowledge_requires_relationship(): void {
        $this->resetAfterTest();
        $f = $this->fixture();
        $stranger = $this->getDataGenerator()->create_user();
        $this->expectException(\moodle_exception::class);
        supervision_service::acknowledge($stranger->id, $f['learner']->id, $f['course']->id, true, $stranger->id);
    }

    /**
     * Feature B: the higher-ed observer profile grants grades + teacher contact only.
     */
    public function test_highered_observer_profile(): void {
        $this->resetAfterTest();
        relationship_service::ensure_default_profiles();
        relationship_service::ensure_default_relationship_types();
        $byshort = [];
        foreach (relationship_service::get_profiles() as $profile) {
            $byshort[$profile->shortname] = $profile;
        }
        $this->assertArrayHasKey('highered_observer', $byshort);
        $observer = $byshort['highered_observer'];
        $this->assertSame(1, (int)$observer->allowgrades);
        $this->assertSame(1, (int)$observer->allowteachercontact);
        $this->assertSame(1, (int)$observer->allowmessaging);
        $this->assertSame(0, (int)$observer->allowactivities);
        $this->assertSame(0, (int)$observer->allowhealthsummary);
        $this->assertArrayHasKey('observer_parent', relationship_service::get_relationship_type_options(true));
    }
}

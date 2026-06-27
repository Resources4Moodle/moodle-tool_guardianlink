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
 * Unit tests for GuardianLink read-only progress/grade helpers.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guardianlink;

use tool_guardianlink\local\progress_service;

/**
 * Tests for {@see \tool_guardianlink\local\progress_service}.
 *
 * @covers \tool_guardianlink\local\progress_service
 */
final class progress_service_test extends \advanced_testcase {
    /**
     * A graded activity yields per-activity tokens, a course grade and a class average.
     */
    public function test_grade_helpers_with_a_graded_activity(): void {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $learner = $gen->create_user();
        $gen->enrol_user($learner->id, $course->id);
        $assign = $gen->create_module('assign', ['course' => $course->id]);

        $gradeitem = \grade_item::fetch([
            'itemtype' => 'mod', 'itemmodule' => 'assign',
            'iteminstance' => $assign->id, 'courseid' => $course->id,
        ]);
        $this->assertNotEmpty($gradeitem);
        $gradeitem->update_final_grade($learner->id, 80);

        // Per-activity tokens expose this item's grade.
        $tokens = progress_service::activity_grade_tokens($course->id, $learner->id);
        $this->assertArrayHasKey('grade_' . $gradeitem->id, $tokens);
        $this->assertArrayHasKey('activity_' . $gradeitem->id, $tokens);
        $this->assertNotSame('-', $tokens['grade_' . $gradeitem->id]);

        // The picker options list the gradable item.
        $this->assertArrayHasKey((int)$gradeitem->id, progress_service::gradeitem_options($course->id));

        // Course total and class average are populated.
        $this->assertNotNull(progress_service::course_grade_raw($course->id, $learner->id));
        $this->assertNotNull(progress_service::class_average($course->id));
    }

    /**
     * With no gradebook data the helpers degrade safely to null / a dash.
     */
    public function test_grade_helpers_without_grades(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $learner = $gen->create_user();
        $gen->enrol_user($learner->id, $course->id);

        $this->assertNull(progress_service::course_grade_raw($course->id, $learner->id));
        $this->assertSame('-', progress_service::format_course_grade($course->id, null));
        $this->assertSame([], progress_service::gradeitem_options($course->id));
    }
}

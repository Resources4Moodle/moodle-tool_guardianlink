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
 * Unit tests for GuardianLink message/email templates and placeholder rendering.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guardianlink;

use tool_guardianlink\local\template_service;
use tool_guardianlink\local\relationship_service;

/**
 * Tests for {@see \tool_guardianlink\local\template_service}.
 *
 * @covers \tool_guardianlink\local\template_service
 */
final class template_service_test extends \advanced_testcase {
    /**
     * The placeholder catalogue advertises the grade/performance/per-activity tokens.
     */
    public function test_placeholder_catalogue(): void {
        $this->resetAfterTest();
        $ph = template_service::placeholders();
        foreach (
            ['learnerfullname', 'coursename', 'grade', 'classaverage', 'relativeperformance',
                'activitygrades', 'testname', 'testresult'] as $key
        ) {
            $this->assertArrayHasKey($key, $ph);
        }
    }

    /**
     * Course-scoped templates are saved, listed (with globals) and copied/overwritten between courses.
     */
    public function test_course_template_save_list_and_copy(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $c1 = $gen->create_course();
        $c2 = $gen->create_course();

        $id = template_service::save_template([
            'shortname' => 'unittest',
            'name' => 'Unit Test Template',
            'triggerkey' => 'manual',
            'subject' => 'Hi {learnerfullname}',
            'body' => ['text' => 'Course {coursename}', 'format' => FORMAT_HTML],
            'enabled' => 1,
            'courseid' => $c1->id,
        ], 2);
        $this->assertGreaterThan(0, $id);

        // The course's own templates include it.
        $own = template_service::get_templates('', false, $c1->id);
        $this->assertArrayHasKey($id, $own);

        // Copy it into course 2, then re-copy (must overwrite, never duplicate).
        $this->assertSame(1, template_service::copy_templates([$id], [$c2->id], 2));
        $this->assertSame(1, template_service::copy_templates([$id], [$c2->id], 2));
        $this->assertCount(1, template_service::get_templates('', false, $c2->id));
    }

    /**
     * Rendering substitutes the learner/course placeholders for a real relationship context.
     */
    public function test_context_render_substitutes_placeholders(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['fullname' => 'Algebra 101']);
        $adult = $gen->create_user();
        $learner = $gen->create_user(['firstname' => 'Sam', 'lastname' => 'Lee']);
        $gen->enrol_user($learner->id, $course->id);
        relationship_service::ensure_default_profiles();
        relationship_service::add_or_update_relationship([
            'adultid' => $adult->id, 'learnerid' => $learner->id, 'reltype' => 'legal_parent',
            'status' => 'active', 'accessprofile' => 'family_full', 'courseids' => (string)$course->id,
        ], 2);

        $template = (object)[
            'subject' => 'Update on {learnerfullname}',
            'body' => 'In {coursename}, grade is {grade}.',
            'bodyformat' => FORMAT_HTML,
        ];
        $ctx = template_service::context($adult->id, $learner->id, $course->id, true);
        $rendered = template_service::render($template, $ctx);

        $this->assertSame('Update on Sam Lee', $rendered['subject']);
        $this->assertStringContainsString('Algebra 101', $rendered['body']);
        // No graded items yet => grade renders as a dash, not a literal placeholder.
        $this->assertStringNotContainsString('{grade}', $rendered['body']);
    }
}

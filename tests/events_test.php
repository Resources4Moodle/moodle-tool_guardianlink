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
 * Unit tests for GuardianLink events (objectid/objecttable integrity).
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guardianlink;

use tool_guardianlink\local\relationship_service;

/**
 * Tests that GuardianLink events carrying an objectid declare their objecttable.
 *
 * @covers \tool_guardianlink\event\proxy_message_sent
 * @covers \tool_guardianlink\event\tutor_request_created
 */
final class events_test extends \advanced_testcase {
    /**
     * An object-bearing event triggers cleanly (no debugging) and is captured with its object id.
     */
    public function test_proxy_message_sent_event_records_objectid(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $learner = $gen->create_user();
        $context = \context_course::instance($course->id);

        $sink = $this->redirectEvents();
        relationship_service::trigger_event(
            'proxy_message_sent',
            $context,
            $learner->id,
            4242,
            ['courseid' => $course->id]
        );
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(\tool_guardianlink\event\proxy_message_sent::class, $events[0]);
        $this->assertSame(4242, (int)$events[0]->objectid);
    }

    /**
     * The tutor-request event likewise declares its object table and records the id.
     */
    public function test_tutor_request_created_event_records_objectid(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $learner = $gen->create_user();
        $context = \context_user::instance($learner->id);

        $sink = $this->redirectEvents();
        relationship_service::trigger_event('tutor_request_created', $context, $learner->id, 77, ['tutorid' => 9]);
        $events = $sink->get_events();
        $sink->close();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(\tool_guardianlink\event\tutor_request_created::class, $events[0]);
        $this->assertSame(77, (int)$events[0]->objectid);
    }
}

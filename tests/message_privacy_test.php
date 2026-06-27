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
 * PHPUnit: GuardianLink never lets a real participant email reach outbound message headers.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guardianlink;

/**
 * Tests that GuardianLink outbound messages never leak a real participant email.
 *
 * @covers \tool_guardianlink\local\message_service
 * @covers \tool_guardianlink\local\bulk_message_service
 */
final class message_privacy_test extends \advanced_testcase {
    /**
     * Proxy and bulk messages must be sent from the no-reply user with Reply-To forced to no-reply,
     * so a sender's real email can never reach the email From/Reply-To headers regardless of config.
     */
    public function test_outbound_messages_use_noreply(): void {
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $sink = $this->redirectMessages();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user(['email' => 'teacher@school.test']);
        $parent = $this->getDataGenerator()->create_user(['email' => 'parent@home.test']);
        $learner = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($learner->id, $course->id, 'student');
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');

        // Active relationship parent->learner with messaging scope on the course.
        $relid = local\relationship_service::add_or_update_relationship([
            'adultid' => $parent->id, 'learnerid' => $learner->id, 'reltype' => 'legal_parent',
            'legal' => 1, 'status' => 'active', 'authoritystatus' => 'verified', 'accessprofile' => 'family_full',
            'scopes' => [['scopekind' => 'course', 'courseid' => $course->id]],
        ], get_admin()->id, false);
        $this->assertGreaterThan(0, $relid);

        $noreply = \core_user::get_noreply_user();

        local\message_service::send_proxy_message($teacher->id, $learner->id, $course->id, 'Subject', 'Body');
        $messages = $sink->get_messages();
        $this->assertNotEmpty($messages);
        foreach ($messages as $m) {
            $this->assertEquals($noreply->id, $m->useridfrom, 'message must come from the no-reply user');
            $this->assertNotEquals($teacher->id, $m->useridfrom);
            $this->assertStringNotContainsString('teacher@school.test', (string)$m->fullmessage);
            $this->assertStringNotContainsString('parent@home.test', (string)$m->fullmessage);
        }
        $sink->close();
    }

    /**
     * The get_bulk_recipients external return must expose no contact/identity field.
     */
    public function test_bulk_recipients_returns_no_contact_fields(): void {
        $structure = \tool_guardianlink\external\get_bulk_recipients::execute_returns();
        $recipient = $structure->keys['recipients']->content;
        $this->assertArrayHasKey('userid', $recipient->keys);
        $this->assertArrayNotHasKey('fullname', $recipient->keys);
        foreach (array_keys($recipient->keys) as $k) {
            $this->assertDoesNotMatchRegularExpression('/email|phone|address|username|mail/i', $k);
        }
    }
}

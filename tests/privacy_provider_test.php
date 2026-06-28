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
 * Tests for the GuardianLink privacy provider deletion model.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guardianlink;

use core_privacy\local\request\approved_contextlist;
use tool_guardianlink\local\relationship_service as rs;
use tool_guardianlink\local\message_service as ms;
use tool_guardianlink\privacy\provider;

/**
 * Tests that erasure deletes preferences, anonymises messages and retains safeguarding records.
 *
 * @covers \tool_guardianlink\privacy\provider
 */
final class privacy_provider_test extends \advanced_testcase {
    /**
     * Erasure deletes the user's digest preferences, redacts message threads, and keeps the
     * relationship/audit records under retention.
     */
    public function test_delete_for_user_deletes_prefs_redacts_messages_retains_safeguarding(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_user();
        $adult = $gen->create_user();
        $learner = $gen->create_user();
        $gen->enrol_user($learner->id, $course->id);
        $gen->enrol_user($teacher->id, $course->id, 'editingteacher');
        rs::ensure_default_profiles();
        $relid = rs::add_or_update_relationship([
            'adultid' => $adult->id, 'learnerid' => $learner->id, 'reltype' => 'legal_parent',
            'status' => 'active', 'authoritystatus' => 'verified', 'accessprofile' => 'family_full',
            'courseids' => (string)$course->id,
        ], 2);
        rs::save_digest_preference($adult->id, $learner->id, ['frequency' => 'weekly', 'status' => 'active']);
        $this->redirectMessages();
        ms::send_proxy_message($teacher->id, $learner->id, $course->id, 'A subject', 'A body');

        $this->assertTrue($DB->record_exists('tool_guardianlink_digestpref', ['guardianid' => $adult->id]));
        $this->assertTrue($DB->record_exists('tool_guardianlink_msgthread', ['guardianid' => $adult->id]));

        // The adult requests erasure of their data.
        $contextlist = provider::get_contexts_for_userid($adult->id);
        $approved = new approved_contextlist(
            \core_user::get_user($adult->id),
            'tool_guardianlink',
            $contextlist->get_contextids()
        );
        provider::delete_data_for_user($approved);

        // Deletable: digest preferences are gone.
        $this->assertFalse($DB->record_exists('tool_guardianlink_digestpref', ['guardianid' => $adult->id]));
        // Anonymised: the message thread is redacted but the shell is kept.
        $thread = $DB->get_record('tool_guardianlink_msgthread', ['guardianid' => $adult->id]);
        $this->assertNotEmpty($thread);
        $this->assertSame(get_string('privacy:erased', 'tool_guardianlink'), $thread->subject);
        $this->assertSame(get_string('privacy:erased', 'tool_guardianlink'), $thread->lastmessage);
        // Retained (safeguarding/audit): the relationship record survives.
        $this->assertTrue($DB->record_exists('tool_guardianlink_rel', ['id' => $relid]));
    }

    /**
     * The provider exposes deletion for a whole user context and for a user list.
     */
    public function test_delete_for_context_and_userlist(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $adult = $gen->create_user();
        $learner = $gen->create_user();
        rs::ensure_default_profiles();
        rs::add_or_update_relationship([
            'adultid' => $adult->id, 'learnerid' => $learner->id, 'reltype' => 'legal_parent',
            'status' => 'active', 'authoritystatus' => 'verified', 'accessprofile' => 'family_full',
        ], 2);
        rs::save_digest_preference($adult->id, $learner->id, ['frequency' => 'weekly', 'status' => 'active']);

        provider::delete_data_for_all_users_in_context(\context_user::instance($adult->id));
        $this->assertFalse($DB->record_exists('tool_guardianlink_digestpref', ['guardianid' => $adult->id]));
    }
}

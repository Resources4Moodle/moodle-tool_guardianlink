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
 * Regression tests for the GuardianLink safeguarding/privacy access-control fixes.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guardianlink;

use tool_guardianlink\local\relationship_service as rs;
use tool_guardianlink\local\message_service as ms;
use tool_guardianlink\local\bulk_message_service as bms;
use tool_guardianlink\local\progress_service as ps;
use tool_guardianlink\local\setup;

/**
 * Tests for restriction handling, scope expiry, replace-safe sync, health visibility and thread locking.
 *
 * @covers \tool_guardianlink\local\relationship_service
 * @covers \tool_guardianlink\local\message_service
 * @covers \tool_guardianlink\local\bulk_message_service
 */
final class security_test extends \advanced_testcase {
    /**
     * Active, verified, legal family_full relationship scoped to one course.
     *
     * @return array{0:\stdClass,1:\stdClass,2:\stdClass,3:int}
     */
    private function base(): array {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $adult = $gen->create_user();
        $learner = $gen->create_user();
        $gen->enrol_user($learner->id, $course->id);
        rs::ensure_default_profiles();
        $relid = rs::add_or_update_relationship([
            'adultid' => $adult->id, 'learnerid' => $learner->id, 'reltype' => 'legal_parent',
            'status' => 'active', 'authoritystatus' => 'verified', 'legal' => 1,
            'accessprofile' => 'family_full', 'courseids' => (string)$course->id,
        ], 2);
        return [$course, $adult, $learner, $relid];
    }

    /**
     * An active-but-unverified relationship must not convey access, appear in lists, or resolve as active.
     */
    public function test_unverified_relationship_conveys_no_access(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $adult, $learner, $relid] = $this->base();
        // Sanity: verified relationship grants overview/calendar and shows in lists.
        $this->assertNotNull(rs::get_active_relationship($adult->id, $learner->id));
        $this->assertTrue(rs::can_access_child($adult->id, $learner->id, 0, 'overview'));

        // Flip authority to unverified while leaving status active (the dangerous anomaly).
        $DB->set_field('tool_guardianlink_rel', 'authoritystatus', 'unverified', ['id' => $relid]);

        $this->assertNull(rs::get_active_relationship($adult->id, $learner->id));
        $ids = array_map(fn($r) => (int)$r->childid, rs::get_learners_for_adult($adult->id));
        $this->assertNotContains((int)$learner->id, $ids);
        $this->assertFalse(rs::can_access_child($adult->id, $learner->id, 0, 'overview'));
        $this->assertFalse(rs::can_access_child($adult->id, $learner->id, 0, 'calendar'));
        $this->assertFalse(rs::can_access_child($adult->id, $learner->id, $course->id, 'grades'));
    }

    /**
     * A restricted relationship must vanish from adult-facing lists and lookups (no linkage leak).
     */
    public function test_restricted_relationship_hidden_from_lists(): void {
        $this->resetAfterTest();
        [$course, $adult, $learner, $relid] = $this->base();
        $this->assertNotNull(rs::get_active_relationship($adult->id, $learner->id));
        $ids = array_map(fn($r) => (int)$r->childid, rs::get_learners_for_adult($adult->id));
        $this->assertContains((int)$learner->id, $ids);

        rs::set_restricted($relid, true, 'Safeguarding hold', 2);

        $this->assertNull(rs::get_active_relationship($adult->id, $learner->id));
        $ids = array_map(fn($r) => (int)$r->childid, rs::get_learners_for_adult($adult->id));
        $this->assertNotContains((int)$learner->id, $ids);
        $this->assertFalse(rs::can_access_child($adult->id, $learner->id, $course->id, 'overview'));
    }

    /**
     * An expired category scope must not keep conveying access.
     */
    public function test_category_scope_expiry_denies(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $category = $gen->create_category();
        $course = $gen->create_course(['category' => $category->id]);
        $adult = $gen->create_user();
        $learner = $gen->create_user();
        $gen->enrol_user($learner->id, $course->id);
        rs::ensure_default_profiles();
        $relid = rs::add_or_update_relationship([
            'adultid' => $adult->id, 'learnerid' => $learner->id, 'reltype' => 'legal_parent',
            'status' => 'active', 'authoritystatus' => 'verified', 'accessprofile' => 'family_full',
        ], 2);

        rs::set_scopes(
            $relid,
            [['scopekind' => 'category', 'categoryid' => $category->id, 'endtime' => time() - 3600, 'allowoverview' => 1]],
            2,
            'family_full'
        );
        $this->assertFalse(rs::can_access_child($adult->id, $learner->id, $course->id, 'overview'));

        // A non-expired category scope still grants access (control).
        rs::set_scopes(
            $relid,
            [['scopekind' => 'category', 'categoryid' => $category->id, 'endtime' => 0, 'allowoverview' => 1]],
            2,
            'family_full'
        );
        $this->assertTrue(rs::can_access_child($adult->id, $learner->id, $course->id, 'overview'));
    }

    /**
     * Replace mode revokes scopes the incoming set no longer includes (ERP narrowing).
     */
    public function test_scope_replace_revokes_missing_courses(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $coursea = $gen->create_course();
        $courseb = $gen->create_course();
        $adult = $gen->create_user();
        $learner = $gen->create_user();
        rs::ensure_default_profiles();
        $relid = rs::add_or_update_relationship([
            'adultid' => $adult->id, 'learnerid' => $learner->id, 'reltype' => 'legal_parent',
            'status' => 'active', 'authoritystatus' => 'verified', 'accessprofile' => 'family_full',
            'courseids' => $coursea->id . ',' . $courseb->id,
        ], 2);
        $this->assertTrue(rs::can_access_child($adult->id, $learner->id, $coursea->id, 'overview'));
        $this->assertTrue(rs::can_access_child($adult->id, $learner->id, $courseb->id, 'overview'));

        // Narrow to course A only, via an authoritative (fromapi) sync => replace.
        rs::add_or_update_relationship([
            'relationshipid' => $relid, 'adultid' => $adult->id, 'learnerid' => $learner->id,
            'reltype' => 'legal_parent', 'status' => 'active', 'authoritystatus' => 'verified',
            'accessprofile' => 'family_full', 'courseids' => (string)$coursea->id,
        ], 2, true);

        $this->assertTrue(rs::can_access_child($adult->id, $learner->id, $coursea->id, 'overview'));
        $this->assertFalse(rs::can_access_child($adult->id, $learner->id, $courseb->id, 'overview'));
    }

    /**
     * Health records honour the visibility allowlist, the legal-holder gate and per-record scope.
     */
    public function test_health_visibility_filtering(): void {
        $this->resetAfterTest();
        set_config('enablehealthrecords', 1, 'tool_guardianlink');
        set_config('requirehealthapproval', 0, 'tool_guardianlink');
        $gen = $this->getDataGenerator();
        $learner = $gen->create_user();
        $legaladult = $gen->create_user();
        $nonlegaladult = $gen->create_user();
        rs::ensure_default_profiles();

        $legalrel = rs::add_or_update_relationship([
            'adultid' => $legaladult->id, 'learnerid' => $learner->id, 'reltype' => 'legal_parent',
            'status' => 'active', 'authoritystatus' => 'verified', 'legal' => 1, 'accessprofile' => 'family_full',
        ], 2);
        $nonlegalrel = rs::add_or_update_relationship([
            'adultid' => $nonlegaladult->id, 'learnerid' => $learner->id, 'reltype' => 'carer',
            'status' => 'active', 'authoritystatus' => 'verified', 'legal' => 0, 'accessprofile' => 'family_basic',
        ], 2);
        // Grant both a learner-level health-summary scope.
        rs::set_scopes($legalrel, [['scopekind' => 'learner', 'allowhealthsummary' => 1, 'allowoverview' => 1]], 2);
        rs::set_scopes($nonlegalrel, [['scopekind' => 'learner', 'allowhealthsummary' => 1, 'allowoverview' => 1]], 2);

        rs::upsert_health_record(['childid' => $learner->id, 'title' => 'Allergy',
            'visibility' => 'emergency_only', 'status' => 'active', 'courseid' => 0], 2);
        rs::upsert_health_record(['childid' => $learner->id, 'title' => 'StaffOnly',
            'visibility' => 'restricted_staff', 'status' => 'active', 'courseid' => 0], 2);
        rs::upsert_health_record(['childid' => $learner->id, 'title' => 'LegalOnly',
            'visibility' => 'legal_guardian', 'status' => 'active', 'courseid' => 0], 2);

        $legaltitles = array_map(fn($r) => $r->title, rs::get_health_records_for_adult($legaladult->id, $learner->id));
        $this->assertContains('Allergy', $legaltitles);
        $this->assertContains('LegalOnly', $legaltitles);
        $this->assertNotContains('StaffOnly', $legaltitles, 'restricted_staff must never be shown to adults');

        $nonlegaltitles = array_map(
            fn($r) => $r->title,
            rs::get_health_records_for_adult($nonlegaladult->id, $learner->id)
        );
        $this->assertContains('Allergy', $nonlegaltitles);
        $this->assertNotContains('StaffOnly', $nonlegaltitles);
        $this->assertNotContains('LegalOnly', $nonlegaltitles, 'legal_guardian record needs a legal holder');
    }

    /**
     * A course-specific health scope must not satisfy a learner-level health check (#5).
     */
    public function test_course_health_scope_does_not_expose_learner_level_health(): void {
        $this->resetAfterTest();
        set_config('enablehealthrecords', 1, 'tool_guardianlink');
        set_config('requirehealthapproval', 0, 'tool_guardianlink');
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $learner = $gen->create_user();
        $adult = $gen->create_user();
        rs::ensure_default_profiles();
        $rel = rs::add_or_update_relationship([
            'adultid' => $adult->id, 'learnerid' => $learner->id, 'reltype' => 'legal_parent',
            'status' => 'active', 'authoritystatus' => 'verified', 'legal' => 1, 'accessprofile' => 'family_full',
        ], 2);
        // Only a COURSE-scoped health permission — no learner/site health scope.
        rs::set_scopes($rel, [['scopekind' => 'course', 'courseid' => $course->id, 'allowhealthsummary' => 1]], 2);

        // A learner-level (courseid = 0) health record must NOT be exposed by the course scope.
        rs::upsert_health_record(['childid' => $learner->id, 'title' => 'LearnerWideAllergy',
            'visibility' => 'emergency_only', 'status' => 'active', 'courseid' => 0], 2);

        $this->assertFalse(rs::can_access_child($adult->id, $learner->id, 0, 'healthsummary'));
        $titles = array_map(fn($r) => $r->title, rs::get_health_records_for_adult($adult->id, $learner->id));
        $this->assertNotContains('LearnerWideAllergy', $titles);
    }

    /**
     * A proxy thread locks once the adult is no longer an eligible recipient (restricted).
     */
    public function test_thread_locks_after_restriction(): void {
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
        $this->redirectMessages();
        $result = ms::send_proxy_message($teacher->id, $learner->id, $course->id, 'Subject', 'Body');
        $threadid = $result['threads'][0];

        $thread = $DB->get_record('tool_guardianlink_msgthread', ['id' => $threadid]);
        $this->assertTrue(ms::thread_relationship_live($thread));

        rs::set_restricted($relid, true, 'Safeguarding hold', 2);
        $thread = $DB->get_record('tool_guardianlink_msgthread', ['id' => $threadid]);
        $this->assertFalse(ms::thread_relationship_live($thread));

        $this->expectException(\moodle_exception::class);
        ms::reply_to_thread($threadid, $teacher->id, 'A late reply that must be refused');
    }

    /**
     * Digest eligibility requires a verified relationship; a restriction drops the learner.
     */
    public function test_digest_excludes_restricted(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $adult, $learner, $relid] = $this->base();
        $prefid = rs::save_digest_preference($adult->id, $learner->id, ['frequency' => 'weekly', 'status' => 'active']);
        // Force it due now.
        $DB->set_field('tool_guardianlink_digestpref', 'nextsend', 1, ['id' => $prefid]);

        $due = rs::get_due_digest_preferences();
        $this->assertContains((int)$adult->id, array_map(fn($d) => (int)$d->guardianid, $due));

        rs::set_restricted($relid, true, 'Safeguarding hold', 2);
        $due = rs::get_due_digest_preferences();
        $this->assertNotContains((int)$adult->id, array_map(fn($d) => (int)$d->guardianid, $due));
    }

    /**
     * Proxy recipients honour the SCOPE time window: an expired messaging scope receives nothing (#3).
     */
    public function test_proxy_recipients_honour_scope_expiry(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $adult = $gen->create_user();
        $learner = $gen->create_user();
        $gen->enrol_user($learner->id, $course->id);
        rs::ensure_default_profiles();
        $relid = rs::add_or_update_relationship(['adultid' => $adult->id, 'learnerid' => $learner->id,
            'reltype' => 'legal_parent', 'status' => 'active', 'authoritystatus' => 'verified',
            'accessprofile' => 'family_full'], 2);

        // Active messaging scope: adult is a recipient.
        rs::set_scopes($relid, [['scopekind' => 'course', 'courseid' => $course->id,
            'allowteachercontact' => 1, 'allowmessaging' => 1]], 2, 'family_full');
        $ids = array_map(fn($r) => (int)$r->guardianid, rs::get_proxy_recipients($learner->id, $course->id));
        $this->assertContains((int)$adult->id, $ids);

        // Expire the scope: the adult must drop out (previously only the relationship window was checked).
        rs::set_scopes($relid, [['scopekind' => 'course', 'courseid' => $course->id,
            'allowteachercontact' => 1, 'allowmessaging' => 1, 'endtime' => time() - 3600]], 2, 'family_full', '', true);
        $ids = array_map(fn($r) => (int)$r->guardianid, rs::get_proxy_recipients($learner->id, $course->id));
        $this->assertNotContains((int)$adult->id, $ids);
    }

    /**
     * Proxy recipients include a valid category-scoped adult for a course in that category (#3).
     */
    public function test_proxy_recipients_include_category_scope(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $category = $gen->create_category();
        $course = $gen->create_course(['category' => $category->id]);
        $adult = $gen->create_user();
        $learner = $gen->create_user();
        $gen->enrol_user($learner->id, $course->id);
        rs::ensure_default_profiles();
        $relid = rs::add_or_update_relationship(['adultid' => $adult->id, 'learnerid' => $learner->id,
            'reltype' => 'legal_parent', 'status' => 'active', 'authoritystatus' => 'verified',
            'accessprofile' => 'family_full'], 2);
        rs::set_scopes($relid, [['scopekind' => 'category', 'categoryid' => $category->id,
            'allowteachercontact' => 1, 'allowmessaging' => 1]], 2, 'family_full');

        $ids = array_map(fn($r) => (int)$r->guardianid, rs::get_proxy_recipients($learner->id, $course->id));
        $this->assertContains((int)$adult->id, $ids, 'category-scoped adult must be a valid recipient');
    }

    /**
     * Bulk messaging always excludes restricted relationships, even when "verified only" is off (#4).
     */
    public function test_bulk_recipients_exclude_restricted_even_without_verifiedonly(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $adult = $gen->create_user();
        $learner = $gen->create_user();
        $gen->enrol_user($learner->id, $course->id);
        rs::ensure_default_profiles();
        $relid = rs::add_or_update_relationship(['adultid' => $adult->id, 'learnerid' => $learner->id,
            'reltype' => 'legal_parent', 'status' => 'active', 'authoritystatus' => 'verified',
            'accessprofile' => 'family_full', 'courseids' => (string)$course->id], 2);
        rs::set_scopes($relid, [['scopekind' => 'course', 'courseid' => $course->id,
            'allowteachercontact' => 1, 'allowmessaging' => 1]], 2, 'family_full');

        // Deliberately disable the verified-only filter; eligibility must still require verified.
        $criteria = bms::normalise_criteria(['audiencetype' => 'course', 'courseid' => $course->id,
            'verifiedonly' => 0, 'excluderestricted' => 0]);
        $ids = array_map(fn($r) => (int)$r->id, bms::resolve_recipients($criteria));
        $this->assertContains((int)$adult->id, $ids);

        rs::set_restricted($relid, true, 'Safeguarding hold', 2);
        $ids = array_map(fn($r) => (int)$r->id, bms::resolve_recipients($criteria));
        $this->assertNotContains((int)$adult->id, $ids, 'restricted adult must never receive bulk messages');
    }

    /**
     * An invalid scope payload aborts the whole relationship upsert — no partial state (#10).
     */
    public function test_invalid_scope_kind_rolls_back_upsert(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $adult = $gen->create_user();
        $learner = $gen->create_user();
        rs::ensure_default_profiles();
        $before = $DB->count_records('tool_guardianlink_rel');

        try {
            rs::add_or_update_relationship([
                'adultid' => $adult->id, 'learnerid' => $learner->id, 'reltype' => 'legal_parent',
                'status' => 'active', 'authoritystatus' => 'verified', 'accessprofile' => 'family_full',
                'scopes' => [['scopekind' => 'bogus', 'courseid' => $course->id]],
            ], 2);
            $this->fail('Expected invalid_parameter_exception for an unknown scope kind');
        } catch (\invalid_parameter_exception $e) {
            $this->assertStringContainsString('scope kind', $e->getMessage());
        }

        // The relationship row inserted before set_scopes() threw must have been rolled back.
        $this->assertSame($before, $DB->count_records('tool_guardianlink_rel'));
        $this->assertSame(0, $DB->count_records('tool_guardianlink_scope', ['courseid' => $course->id]));
    }

    /**
     * The optional auto-assigned role is stripped immediately on restriction and on expiry (#11).
     */
    public function test_status_downgrade_strips_auto_assigned_role(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('autoassignrole', 1, 'tool_guardianlink');
        setup::ensure_guardian_role();
        $roleid = setup::guardian_role_id();
        $this->assertGreaterThan(0, $roleid);
        $gen = $this->getDataGenerator();
        $adult = $gen->create_user();
        $learner = $gen->create_user();
        rs::ensure_default_profiles();
        $usercontext = \context_user::instance($learner->id);

        $haskey = ['roleid' => $roleid, 'userid' => $adult->id, 'contextid' => $usercontext->id,
            'component' => 'tool_guardianlink'];

        // An active, verified grant assigns the role at the learner's user context.
        $relid = rs::add_or_update_relationship(['adultid' => $adult->id, 'learnerid' => $learner->id,
            'reltype' => 'legal_parent', 'status' => 'active', 'authoritystatus' => 'verified',
            'accessprofile' => 'family_full'], 2);
        $this->assertTrue($DB->record_exists('role_assignments', $haskey));

        // Restriction must strip it immediately (not wait for a cleanup task).
        rs::set_restricted($relid, true, 'Safeguarding hold', 2);
        $this->assertFalse($DB->record_exists('role_assignments', $haskey));

        // Re-instate, then let it expire: the role must be stripped by the expiry run.
        rs::set_restricted($relid, false, 'Cleared', 2);
        $this->assertTrue($DB->record_exists('role_assignments', $haskey));
        $DB->set_field('tool_guardianlink_rel', 'endtime', time() - 3600, ['id' => $relid]);
        rs::expire_due_grants();
        $this->assertFalse($DB->record_exists('role_assignments', $haskey));
    }

    /**
     * A tutor request stores only courses the learner is enrolled in and that are in the requester's scope.
     */
    public function test_tutor_request_filters_courses_by_scope_and_enrolment(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $inscope = $gen->create_course();
        $notenrolled = $gen->create_course();
        $outofscope = $gen->create_course();
        $parent = $gen->create_user();
        $tutor = $gen->create_user();
        $learner = $gen->create_user();
        $gen->enrol_user($learner->id, $inscope->id);
        $gen->enrol_user($learner->id, $outofscope->id);
        rs::ensure_default_profiles();
        rs::ensure_default_relationship_types();
        // Legal parent scoped to $inscope only (so they can request tutoring there, not in $outofscope).
        rs::add_or_update_relationship(['adultid' => $parent->id, 'learnerid' => $learner->id,
            'reltype' => 'legal_parent', 'status' => 'active', 'authoritystatus' => 'verified', 'legal' => 1,
            'accessprofile' => 'family_full', 'courseids' => (string)$inscope->id], 2);

        $reqid = rs::create_tutor_request([
            'tutorid' => $tutor->id, 'learnerid' => $learner->id,
            'courseids' => $inscope->id . ',' . $notenrolled->id . ',' . $outofscope->id,
        ], $parent->id);
        $stored = $DB->get_field('tool_guardianlink_tutorreq', 'courseids', ['id' => $reqid]);
        $ids = array_filter(array_map('intval', explode(',', (string)$stored)));
        $this->assertContains((int)$inscope->id, $ids);
        $this->assertNotContains((int)$notenrolled->id, $ids, 'learner not enrolled — must be dropped');
        $this->assertNotContains((int)$outofscope->id, $ids, 'course not in requester scope — must be dropped');
    }

    /**
     * A course-specific health record requires the learner to be enrolled in that course.
     */
    public function test_health_record_requires_enrolment_for_course_scope(): void {
        $this->resetAfterTest();
        set_config('enablehealthrecords', 1, 'tool_guardianlink');
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $learner = $gen->create_user();
        // Learner is NOT enrolled in $course.
        $this->expectException(\invalid_parameter_exception::class);
        rs::upsert_health_record(['childid' => $learner->id, 'title' => 'Bad',
            'visibility' => 'emergency_only', 'status' => 'active', 'courseid' => $course->id], 2);
    }

    /**
     * Broad scopes expand into concrete visible course cards for the adult (GATE-07).
     */
    public function test_visible_courses_expand_broad_scopes(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $category = $gen->create_category();
        $course = $gen->create_course(['category' => $category->id]);
        $other = $gen->create_course();
        $adult = $gen->create_user();
        $learner = $gen->create_user();
        $gen->enrol_user($learner->id, $course->id);
        $gen->enrol_user($learner->id, $other->id);
        rs::ensure_default_profiles();
        $relid = rs::add_or_update_relationship(['adultid' => $adult->id, 'learnerid' => $learner->id,
            'reltype' => 'legal_parent', 'status' => 'active', 'authoritystatus' => 'verified',
            'accessprofile' => 'family_full'], 2);
        // Only a CATEGORY scope — it must still surface the enrolled course in that category.
        rs::set_scopes($relid, [['scopekind' => 'category', 'categoryid' => $category->id,
            'allowoverview' => 1]], 2, 'family_full');

        $visible = array_keys(rs::visible_courses_for_adult($adult->id, $learner->id));
        $this->assertContains((int)$course->id, $visible, 'category scope must expand to its course');
        $this->assertNotContains((int)$other->id, $visible, 'course in another category must not appear');
        $this->assertSame('category', rs::access_reason_for_course($adult->id, $learner->id, (int)$course->id));

        // Expired category scope → no visible courses.
        rs::set_scopes($relid, [['scopekind' => 'category', 'categoryid' => $category->id,
            'allowoverview' => 1, 'endtime' => time() - 3600]], 2, 'family_full', '', true);
        $this->assertEmpty(rs::visible_courses_for_adult($adult->id, $learner->id));
    }

    /**
     * A grade item from another course must not resolve when rendering for a given course (#6).
     */
    public function test_activity_grade_by_item_is_course_bound(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $coursea = $gen->create_course();
        $courseb = $gen->create_course();
        $learner = $gen->create_user();
        $assignb = $gen->create_module('assign', ['course' => $courseb->id]);
        $itemb = \grade_item::fetch([
            'itemtype' => 'mod', 'itemmodule' => 'assign',
            'iteminstance' => $assignb->id, 'courseid' => $courseb->id,
        ]);
        $this->assertNotEmpty($itemb);

        // Rendering for course B (the item's own course) resolves; rendering for course A must not.
        $this->assertNotNull(ps::activity_grade_by_item((int)$itemb->id, (int)$learner->id, (int)$courseb->id));
        $this->assertNull(ps::activity_grade_by_item((int)$itemb->id, (int)$learner->id, (int)$coursea->id));
    }

    /**
     * A course that disables teacher proxy messaging blocks proxy sends at the service layer (GATE-01).
     */
    public function test_course_proxy_policy_blocks_teacher_sends(): void {
        $this->resetAfterTest();
        $this->redirectMessages();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_user();
        $adult = $gen->create_user();
        $learner = $gen->create_user();
        $gen->enrol_user($learner->id, $course->id);
        rs::ensure_default_profiles();
        $relid = rs::add_or_update_relationship(['adultid' => $adult->id, 'learnerid' => $learner->id,
            'reltype' => 'legal_parent', 'status' => 'active', 'authoritystatus' => 'verified',
            'accessprofile' => 'family_full'], 2);
        rs::set_scopes($relid, [['scopekind' => 'course', 'courseid' => $course->id,
            'allowmessaging' => 1, 'allowteachercontact' => 1]], 2, 'family_full');

        // Default (no policy row) allows proxy messaging.
        $this->assertTrue(rs::course_allows_teacher_proxy($course->id));
        $this->assertGreaterThan(0, ms::send_proxy_message($teacher->id, $learner->id, $course->id, 'S', 'B')['sent']);

        // Disabling the course policy blocks both the bulk-style and one-off send paths.
        rs::save_course_config($course->id, ['allowteacherproxy' => 0], 2);
        $this->assertFalse(rs::course_allows_teacher_proxy($course->id));
        $this->assertSame(0, ms::send_proxy_message($teacher->id, $learner->id, $course->id, 'S', 'B')['sent']);
        $this->assertFalse(ms::send_one_off($adult->id, $learner->id, $course->id, 'S', 'B', $teacher->id));
    }

    /**
     * send_one_off() fails closed unless the adult has a live messaging scope for the learner/course (GATE-03).
     */
    public function test_send_one_off_requires_messaging_eligibility(): void {
        $this->resetAfterTest();
        $this->redirectMessages();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_user();
        $adult = $gen->create_user();
        $stranger = $gen->create_user();
        $learner = $gen->create_user();
        $gen->enrol_user($learner->id, $course->id);
        rs::ensure_default_profiles();
        $relid = rs::add_or_update_relationship(['adultid' => $adult->id, 'learnerid' => $learner->id,
            'reltype' => 'legal_parent', 'status' => 'active', 'authoritystatus' => 'verified',
            'accessprofile' => 'family_full'], 2);
        rs::set_scopes($relid, [['scopekind' => 'course', 'courseid' => $course->id,
            'allowmessaging' => 1, 'allowteachercontact' => 1]], 2, 'family_full');

        // Unrelated adult: rejected. Related adult with messaging scope: accepted.
        $this->assertFalse(ms::send_one_off($stranger->id, $learner->id, $course->id, 'S', 'B', $teacher->id));
        $this->assertTrue(ms::send_one_off($adult->id, $learner->id, $course->id, 'S', 'B', $teacher->id));

        // Remove the messaging permission from the scope: now rejected.
        rs::set_scopes($relid, [['scopekind' => 'course', 'courseid' => $course->id,
            'allowmessaging' => 0, 'allowteachercontact' => 1]], 2, 'family_full', '', true);
        $this->assertFalse(ms::send_one_off($adult->id, $learner->id, $course->id, 'S', 'B', $teacher->id));
    }

    /**
     * A category bulk send only reaches adults whose messaging scope intersects that category (GATE-04).
     */
    public function test_bulk_category_audience_intersects_scope(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $gen = $this->getDataGenerator();
        $cata = $gen->create_category();
        $catb = $gen->create_category();
        $coursea = $gen->create_course(['category' => $cata->id]);
        $courseb = $gen->create_course(['category' => $catb->id]);
        $learner = $gen->create_user();
        $gen->enrol_user($learner->id, $coursea->id);
        $gen->enrol_user($learner->id, $courseb->id);
        $inadult = $gen->create_user();   // Scoped to Course A (in Category A) — should receive.
        $outadult = $gen->create_user();  // Scoped to Course B (in Category B) — should NOT receive.
        rs::ensure_default_profiles();
        $rin = rs::add_or_update_relationship(['adultid' => $inadult->id, 'learnerid' => $learner->id,
            'reltype' => 'legal_parent', 'status' => 'active', 'authoritystatus' => 'verified',
            'accessprofile' => 'family_full'], 2);
        rs::set_scopes($rin, [['scopekind' => 'course', 'courseid' => $coursea->id,
            'allowmessaging' => 1, 'allowteachercontact' => 1]], 2, 'family_full');
        $rout = rs::add_or_update_relationship(['adultid' => $outadult->id, 'learnerid' => $learner->id,
            'reltype' => 'carer', 'status' => 'active', 'authoritystatus' => 'verified',
            'accessprofile' => 'family_full'], 2);
        rs::set_scopes($rout, [['scopekind' => 'course', 'courseid' => $courseb->id,
            'allowmessaging' => 1, 'allowteachercontact' => 1]], 2, 'family_full');

        $criteria = bms::normalise_criteria(['audiencetype' => 'category', 'categoryid' => $cata->id,
            'excluderestricted' => 0]);
        $ids = array_map(fn($r) => (int)$r->id, bms::resolve_recipients($criteria));
        $this->assertContains((int)$inadult->id, $ids, 'Category-A-scoped adult must receive');
        $this->assertNotContains((int)$outadult->id, $ids, 'Course-B/Category-B-scoped adult must not receive a Category-A send');
    }

    /**
     * Grade tokens are not rendered for a sender who cannot view the gradebook, even if the adult is
     * scoped for grades (GATE-02).
     */
    public function test_proxy_template_excludes_grades_without_sender_capability(): void {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_user();
        $adult = $gen->create_user();
        $learner = $gen->create_user();
        $gen->enrol_user($learner->id, $course->id);
        rs::ensure_default_profiles();
        $relid = rs::add_or_update_relationship(['adultid' => $adult->id, 'learnerid' => $learner->id,
            'reltype' => 'legal_parent', 'status' => 'active', 'authoritystatus' => 'verified',
            'accessprofile' => 'family_full'], 2);
        rs::set_scopes($relid, [['scopekind' => 'course', 'courseid' => $course->id,
            'allowmessaging' => 1, 'allowteachercontact' => 1, 'allowgrades' => 1]], 2, 'family_full');
        // Give the learner a real course grade.
        $gi = new \grade_item($gen->create_grade_item([
            'courseid' => $course->id, 'itemtype' => 'manual', 'itemname' => 'Task', 'grademax' => 100,
        ]), false);
        $gi->update_final_grade($learner->id, 77);
        grade_regrade_final_grades($course->id);
        // The {testresult} token reflects the specific grade item; pass it via $extra for a deterministic value.
        $template = (object)['subject' => 'Re {learnerfullname}', 'body' => 'Score: {testresult}', 'bodyformat' => FORMAT_HTML];
        $extra = ['gradeitemid' => (int)$gi->id];

        // Sender CANNOT view grades: the grade must not appear.
        $sink = $this->redirectMessages();
        ms::send_proxy_template($teacher->id, $learner->id, $course->id, $template, $extra, false);
        $msgs = $sink->get_messages();
        $this->assertNotEmpty($msgs);
        $this->assertStringNotContainsString('77', $msgs[0]->fullmessagehtml, 'grade leaked without sender capability');
        $sink->close();

        // Sender CAN view grades and the adult is scoped for grades: the grade appears.
        $sink = $this->redirectMessages();
        ms::send_proxy_template($teacher->id, $learner->id, $course->id, $template, $extra, true);
        $msgs = $sink->get_messages();
        $this->assertNotEmpty($msgs);
        $this->assertStringContainsString('77', $msgs[0]->fullmessagehtml);
    }

    /**
     * Assisted access (experimental) stays inert unless BOTH the master switch and the
     * experimental-risk acknowledgement are set — a single toggle must never enable it.
     */
    public function test_assisted_access_requires_double_optin(): void {
        $this->resetAfterTest();
        [$course, $adult, $learner, $relid] = $this->base();
        // Grant an explicit assisted scope so only the org-level gating is under test.
        rs::set_scopes($relid, [['scopekind' => 'course', 'courseid' => $course->id, 'allowassisted' => 1]], 2, 'family_full');

        // Neither switch set: off.
        $this->assertFalse(rs::assisted_feature_enabled());
        $this->assertFalse(rs::can_access_child($adult->id, $learner->id, $course->id, 'assisted'));

        // Master switch alone: still off (the accidental-single-toggle case).
        set_config('enableassistedmode', 1, 'tool_guardianlink');
        $this->assertFalse(rs::assisted_feature_enabled());
        $this->assertFalse(rs::can_access_child($adult->id, $learner->id, $course->id, 'assisted'));

        // Acknowledgement alone: still off.
        set_config('enableassistedmode', 0, 'tool_guardianlink');
        set_config('assistedexperimentalack', 1, 'tool_guardianlink');
        $this->assertFalse(rs::assisted_feature_enabled());
        $this->assertFalse(rs::can_access_child($adult->id, $learner->id, $course->id, 'assisted'));

        // Both set: feature becomes available.
        set_config('enableassistedmode', 1, 'tool_guardianlink');
        $this->assertTrue(rs::assisted_feature_enabled());
        $this->assertTrue(rs::can_access_child($adult->id, $learner->id, $course->id, 'assisted'));
    }
}

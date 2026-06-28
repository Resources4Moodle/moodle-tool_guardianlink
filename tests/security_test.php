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

/**
 * Tests for restriction handling, scope expiry, replace-safe sync, health visibility and thread locking.
 *
 * @covers \tool_guardianlink\local\relationship_service
 * @covers \tool_guardianlink\local\message_service
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

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
 * Independent (unsupervised) learner-access acknowledgements for GuardianLink.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guardianlink\local;

/**
 * Models the "a parent allows the child to use a course unsupervised" workflow.
 *
 * The chain is deliberately three-keyed so no single party can unlock independent access:
 *  1. The admin enables the capability site-wide ({@see self::feature_enabled()}).
 *  2. A teacher marks an individual course safe for independent access (course config).
 *  3. An authorised adult acknowledges/allows it for their learner ({@see self::acknowledge()}).
 *
 * When a learner has no authorised-adult relationship at all, supervision simply does not apply.
 */
class supervision_service {
    /**
     * Whether the admin has enabled the independent-access capability site-wide.
     *
     * @return bool
     */
    public static function feature_enabled(): bool {
        return (bool)get_config('tool_guardianlink', 'allowindependentaccess');
    }

    /**
     * Whether the school's posture is "supervision required by default" (drives the soft notice).
     *
     * @return bool
     */
    public static function supervision_required(): bool {
        return (bool)get_config('tool_guardianlink', 'requiresupervision');
    }

    /**
     * Whether a teacher has marked this course safe for independent learner access.
     *
     * @param int $courseid
     * @return bool
     */
    public static function course_allows_independent(int $courseid): bool {
        if (!self::feature_enabled() || $courseid <= 0) {
            return false;
        }
        $cfg = relationship_service::get_course_config($courseid);
        return $cfg && !empty($cfg->allowindependentaccess);
    }

    /**
     * Whether independent access may currently be offered for this adult/learner/course.
     *
     * The course must opt in (teacher switch), the learner must be actively enrolled, and the adult
     * must hold course access. Mirrors the per-course eligibility used by offered_courses() so the
     * acknowledgement action cannot be forced for an arbitrary or out-of-scope course id.
     *
     * @param int $guardianid
     * @param int $childid
     * @param int $courseid
     * @return bool
     */
    public static function course_offer_valid(int $guardianid, int $childid, int $courseid): bool {
        global $CFG;
        require_once($CFG->libdir . '/enrollib.php');
        if (!self::course_allows_independent($courseid)) {
            return false;
        }
        if (!relationship_service::can_access_child($guardianid, $childid, $courseid, 'overview')) {
            return false;
        }
        $context = \context_course::instance($courseid, IGNORE_MISSING);
        return $context && is_enrolled($context, $childid, '', true);
    }

    /**
     * Whether the learner has any active authorised-adult relationship (i.e. supervision applies).
     *
     * @param int $childid
     * @return bool
     */
    public static function child_has_guardian(int $childid): bool {
        global $DB;
        return $DB->record_exists('tool_guardianlink_rel', ['childid' => $childid, 'status' => 'active']);
    }

    /**
     * Record (or update) an authorised adult's acknowledgement for one learner and course.
     *
     * @param int $guardianid Authorised adult.
     * @param int $childid Learner.
     * @param int $courseid Course.
     * @param bool $allow True to allow independent access, false to revoke it.
     * @param int $userid Acting user (for audit).
     * @param string $note Optional note.
     * @return int Acknowledgement record id.
     */
    public static function acknowledge(
        int $guardianid,
        int $childid,
        int $courseid,
        bool $allow,
        int $userid,
        string $note = ''
    ): int {
        global $DB;
        if (!relationship_service::get_active_relationship($guardianid, $childid)) {
            throw new \moodle_exception('accessdenied', 'tool_guardianlink');
        }
        // Granting independent access must re-validate the course server-side: the submitted course id
        // could be arbitrary. The course must opt in, the learner must be actively enrolled, and the
        // adult must have course access. (Revoking is always permitted, even if the offer was withdrawn.)
        if ($allow && !self::course_offer_valid($guardianid, $childid, $courseid)) {
            throw new \moodle_exception('accessdenied', 'tool_guardianlink');
        }
        $now = time();
        $existing = $DB->get_record(
            'tool_guardianlink_indack',
            ['guardianid' => $guardianid, 'childid' => $childid, 'courseid' => $courseid]
        );
        $record = (object)[
            'guardianid' => $guardianid,
            'childid' => $childid,
            'courseid' => $courseid,
            'status' => $allow ? 'allowed' : 'revoked',
            'note' => clean_param($note, PARAM_TEXT),
            'acknowledgedby' => $userid,
            'timemodified' => $now,
        ];
        if ($existing) {
            $record->id = (int)$existing->id;
            $record->timecreated = (int)$existing->timecreated;
            $DB->update_record('tool_guardianlink_indack', $record);
            $id = (int)$existing->id;
        } else {
            $record->timecreated = $now;
            $id = (int)$DB->insert_record('tool_guardianlink_indack', $record);
        }
        relationship_service::log_access(
            $userid,
            $childid,
            $allow ? 'independent_access_allowed' : 'independent_access_revoked',
            $courseid,
            'indack',
            $id,
            ['guardianid' => $guardianid]
        );
        return $id;
    }

    /**
     * Whether the learner is currently allowed independent (unsupervised) access to the course.
     *
     * @param int $childid
     * @param int $courseid
     * @return bool
     */
    public static function is_independent_allowed(int $childid, int $courseid): bool {
        global $DB;
        // A learner with no authorised-adult relationship is not supervised by this plugin.
        if (!self::child_has_guardian($childid)) {
            return true;
        }
        if (!self::course_allows_independent($courseid)) {
            return false;
        }
        return $DB->record_exists(
            'tool_guardianlink_indack',
            ['childid' => $childid, 'courseid' => $courseid, 'status' => 'allowed']
        );
    }

    /**
     * The acknowledgement record for one adult/learner/course, if any.
     *
     * @param int $guardianid
     * @param int $childid
     * @param int $courseid
     * @return object|null
     */
    public static function get_acknowledgement(int $guardianid, int $childid, int $courseid): ?object {
        global $DB;
        $rec = $DB->get_record(
            'tool_guardianlink_indack',
            ['guardianid' => $guardianid, 'childid' => $childid, 'courseid' => $courseid]
        );
        return $rec ?: null;
    }

    /**
     * A consolidated independent-access status for a learner in a course.
     *
     * @param int $childid
     * @param int $courseid
     * @return array
     */
    public static function status_for(int $childid, int $courseid): array {
        return [
            'featureon' => self::feature_enabled(),
            'supervisionrequired' => self::supervision_required(),
            'hasguardian' => self::child_has_guardian($childid),
            'courseallows' => self::course_allows_independent($courseid),
            'allowed' => self::is_independent_allowed($childid, $courseid),
        ];
    }

    /**
     * Courses (the adult may oversee) that offer independent access for a learner, with ack status.
     *
     * @param int $guardianid
     * @param int $childid
     * @return array [courseid => {course:object, status:string}]
     */
    public static function offered_courses(int $guardianid, int $childid): array {
        global $CFG;
        require_once($CFG->libdir . '/enrollib.php');
        $out = [];
        if (!self::feature_enabled() || !relationship_service::get_active_relationship($guardianid, $childid)) {
            return $out;
        }
        foreach (enrol_get_users_courses($childid, true, 'id, fullname, visible') as $course) {
            if (!self::course_allows_independent((int)$course->id)) {
                continue;
            }
            if (!relationship_service::can_access_child($guardianid, $childid, (int)$course->id, 'overview')) {
                continue;
            }
            $ack = self::get_acknowledgement($guardianid, $childid, (int)$course->id);
            $out[(int)$course->id] = (object)['course' => $course, 'status' => $ack ? $ack->status : 'none'];
        }
        return $out;
    }

    /**
     * All acknowledgements recorded for a course (teacher/admin oversight).
     *
     * @param int $courseid
     * @return array
     */
    public static function get_acknowledgements(int $courseid): array {
        global $DB;
        return $DB->get_records('tool_guardianlink_indack', ['courseid' => $courseid], 'timemodified DESC');
    }
}

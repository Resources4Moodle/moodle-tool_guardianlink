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
 * Public API for other plugins to integrate with GuardianLink.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guardianlink;

use tool_guardianlink\local\relationship_service;
use tool_guardianlink\local\message_service;
use tool_guardianlink\local\template_service;
use tool_guardianlink\local\progress_service;

/**
 * Stable, supported entry points for other plugins (e.g. a results plugin, a SIS bridge, a
 * report) to read GuardianLink relationships and send notices through GuardianLink's
 * privacy-preserving, no-reply messaging channel.
 *
 * Design rules honoured by every method here:
 *  - Never returns an adult's email or phone to the caller.
 *  - Messaging always goes out through the audited no-reply path; the caller cannot address an
 *    adult directly.
 *  - Read methods are scope-aware: grades are only ever included when the relationship's course
 *    scope permits grades.
 *
 * This class is the ONLY part of the plugin other components should call. The classes under
 * \tool_guardianlink\local\* are internal and may change without notice.
 */
class api {
    /**
     * Authorised adults (guardians/tutors) who have an active relationship to a learner.
     *
     * Returns minimal identity only — id and display name — never contact details.
     *
     * @param int $learnerid
     * @param int $courseid Optional: restrict to adults whose scope covers this course.
     * @param string $permission Permission the adult must hold (default 'overview').
     * @return array list of objects {id:int, fullname:string, reltype:string}
     */
    public static function get_guardians_for_learner(
        int $learnerid,
        int $courseid = 0,
        string $permission = 'overview'
    ): array {
        global $DB;
        $out = [];
        $rels = relationship_service::get_relationships(['childid' => $learnerid, 'status' => 'active'], 500);
        $seen = [];
        foreach ($rels as $rel) {
            $adultid = (int)$rel->guardianid;
            if (isset($seen[$adultid])) {
                continue;
            }
            if (!relationship_service::can_access_child($adultid, $learnerid, $courseid, $permission)) {
                continue;
            }
            $adult = \core_user::get_user($adultid, '*', IGNORE_MISSING);
            if (!$adult) {
                continue;
            }
            $seen[$adultid] = true;
            $out[] = (object)[
                'id' => $adultid,
                'fullname' => fullname($adult),
                'reltype' => (string)$rel->reltype,
            ];
        }
        return $out;
    }

    /**
     * Learners an authorised adult may currently access.
     *
     * @param int $adultid
     * @return array list of objects {id:int, fullname:string}
     */
    public static function get_learners_for_guardian(int $adultid): array {
        $out = [];
        foreach (relationship_service::get_learners_for_adult($adultid) as $learner) {
            $out[] = (object)[
                'id' => (int)$learner->id,
                'fullname' => fullname($learner),
            ];
        }
        return $out;
    }

    /**
     * Whether an adult has an active relationship to a learner (optionally scoped to a course
     * and a specific permission such as 'grades').
     *
     * @param int $adultid
     * @param int $learnerid
     * @param int $courseid
     * @param string $permission
     * @return bool
     */
    public static function can_access(
        int $adultid,
        int $learnerid,
        int $courseid = 0,
        string $permission = 'overview'
    ): bool {
        return relationship_service::can_access_child($adultid, $learnerid, $courseid, $permission);
    }

    /**
     * Whether any active relationship links the adult and learner (no scope check).
     *
     * @param int $adultid
     * @param int $learnerid
     * @return bool
     */
    public static function has_relationship(int $adultid, int $learnerid): bool {
        return relationship_service::get_active_relationship($adultid, $learnerid) !== null;
    }

    /**
     * Send a one-off notice to every authorised adult of a learner, through GuardianLink's
     * audited no-reply channel. The caller never learns the adults' addresses.
     *
     * Placeholders ({learnerfullname}, {coursename}, {grade}, {classaverage},
     * {relativeperformance}, {activitygrades}, ...) in the subject/body are substituted per
     * recipient. Grade placeholders are only filled when $includegrades is true AND the
     * recipient's scope permits grades.
     *
     * @param int $senderid Acting user (teacher/system) — used for audit, not as a reply-to.
     * @param int $learnerid
     * @param int $courseid
     * @param string $subject Subject, may contain placeholders.
     * @param string $body Body (HTML), may contain placeholders.
     * @param bool $includegrades Permit grade placeholders to be populated.
     * @return array ['sent' => int, 'recipients' => int]
     */
    public static function notify_guardians(
        int $senderid,
        int $learnerid,
        int $courseid,
        string $subject,
        string $body,
        bool $includegrades = false
    ): array {
        $template = (object)[
            'subject' => $subject,
            'body' => $body,
            'bodyformat' => FORMAT_HTML,
        ];
        $recipients = relationship_service::get_proxy_recipients($learnerid, $courseid);
        $sent = 0;
        foreach ($recipients as $recipient) {
            $adultid = (int)$recipient->id;
            $grades = $includegrades && relationship_service::can_access_child(
                $adultid,
                $learnerid,
                $courseid,
                'grades'
            );
            $ctx = template_service::context($adultid, $learnerid, $courseid, $grades);
            $rendered = template_service::render($template, $ctx);
            if (
                message_service::send_one_off(
                    $adultid,
                    $learnerid,
                    $courseid,
                    $rendered['subject'],
                    $rendered['body'],
                    $senderid
                )
            ) {
                $sent++;
            }
        }
        return ['sent' => $sent, 'recipients' => count($recipients)];
    }

    /**
     * Render a stored template (by shortname) for an adult/learner/course context without
     * sending it — useful for previews or for a plugin that wants the text.
     *
     * @param string $shortname
     * @param int $adultid
     * @param int $learnerid
     * @param int $courseid
     * @param bool $includegrades
     * @return array|null ['subject' => string, 'body' => string, 'format' => int] or null if no such template
     */
    public static function render_template(
        string $shortname,
        int $adultid,
        int $learnerid,
        int $courseid = 0,
        bool $includegrades = false
    ): ?array {
        $template = template_service::get_template($shortname);
        if (!$template) {
            return null;
        }
        $ctx = template_service::context($adultid, $learnerid, $courseid, $includegrades);
        return template_service::render($template, $ctx);
    }

    /**
     * Read-only progress summary for a learner in a course (completion + optional grade).
     * Callers must pass $includegrade only when their own authorisation permits grades.
     *
     * @param int $courseid
     * @param int $learnerid
     * @param bool $includegrade
     * @return \stdClass
     */
    public static function get_progress(int $courseid, int $learnerid, bool $includegrade = false): \stdClass {
        return progress_service::course_progress($courseid, $learnerid, $includegrade);
    }

    /**
     * The placeholder catalogue (key => human description) so an integrating plugin can present
     * the same {placeholders} GuardianLink supports.
     *
     * @return array
     */
    public static function placeholders(): array {
        return template_service::placeholders();
    }

    /**
     * Whether a learner is currently allowed independent (unsupervised) access to a course
     * (a parent acknowledged it for a course a teacher marked safe). A learner with no
     * authorised-adult relationship is always allowed.
     *
     * @param int $learnerid
     * @param int $courseid
     * @return bool
     */
    public static function is_independent_access_allowed(int $learnerid, int $courseid): bool {
        return \tool_guardianlink\local\supervision_service::is_independent_allowed($learnerid, $courseid);
    }

    /**
     * Consolidated independent-access status for a learner in a course (feature/course/ack flags).
     *
     * @param int $learnerid
     * @param int $courseid
     * @return array
     */
    public static function independent_access_status(int $learnerid, int $courseid): array {
        return \tool_guardianlink\local\supervision_service::status_for($learnerid, $courseid);
    }
}

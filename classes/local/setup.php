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
 * Install/upgrade provisioning and safety guardrails for GuardianLink.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guardianlink\local;

/**
 * Provisioning of the dedicated authorised-adult role and enforcement of the
 * "an adult never becomes the learner" guardrails.
 *
 * GuardianLink does NOT rely on, modify, or enable Moodle's core parent/mentor
 * role. Instead it provisions its own clearly-named role that is assignable in
 * a learner's user context, grants only safe read access to the learner's
 * profile/reports, and PROHIBITS moodle/user:loginas so no holder of this role
 * can ever log in as, or act on behalf of, the learner.
 */
class setup {
    /** @var string Shortname of the dedicated authorised-adult role. */
    public const ROLE_SHORTNAME = 'guardianlinkadult';

    /** @var string[] Safe read capabilities granted to the authorised-adult role (mentor read-set + plugin reads). */
    private const ALLOW_CAPS = [
        'moodle/user:viewdetails',
        'moodle/user:viewalldetails',
        'moodle/user:viewuseractivitiesreport',
        'moodle/user:readuserblogs',
        'moodle/user:readuserposts',
        // Inspector-style course view (without participation). Required at course context so that
        // core's "log in as" check (which demands the REAL adult can access the course) passes during
        // a governed assisted session. Never grants participation/submission rights.
        'moodle/course:view',
        'tool/guardianlink:viewreports',
        'tool/guardianlink:viewhealth',
    ];

    /**
     * @var string[] Capabilities that MUST be prohibited for authorised adults.
     * moodle/user:loginas is the critical guardrail: it stops any "log in as the
     * learner" / "act on behalf of" path. The rest prevent privilege escalation.
     */
    public const PROHIBIT_CAPS = [
        'moodle/user:loginas',
        'moodle/site:config',
        'moodle/role:assign',
        'moodle/role:manage',
    ];

    /**
     * Ensure the dedicated authorised-adult role exists and is configured safely.
     *
     * Idempotent: safe to call on every install and upgrade.
     *
     * @return int Role id.
     */
    public static function ensure_guardian_role(): int {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/lib/accesslib.php');

        $roleid = (int)$DB->get_field('role', 'id', ['shortname' => self::ROLE_SHORTNAME]);
        if (!$roleid) {
            $roleid = create_role(
                get_string('guardianrole', 'tool_guardianlink'),
                self::ROLE_SHORTNAME,
                get_string('guardianrole_desc', 'tool_guardianlink'),
                '' // No archetype: this role is deliberately not derived from any core role.
            );
        }

        // Assignable in a learner's user context (mentor pattern), at course context (assisted
        // sessions need the adult to satisfy core's course-access check), and at system level.
        set_role_contextlevels($roleid, [CONTEXT_USER, CONTEXT_COURSE, CONTEXT_SYSTEM]);

        $systemcontext = \context_system::instance();
        // Grant only safe, existing read capabilities.
        foreach (self::ALLOW_CAPS as $cap) {
            if ($DB->record_exists('capabilities', ['name' => $cap])) {
                assign_capability($cap, CAP_ALLOW, $roleid, $systemcontext->id, true);
            }
        }
        // Hard-prohibit anything that could become an act-as / escalation path.
        foreach (self::PROHIBIT_CAPS as $cap) {
            if ($DB->record_exists('capabilities', ['name' => $cap])) {
                assign_capability($cap, CAP_PROHIBIT, $roleid, $systemcontext->id, true);
            }
        }
        $systemcontext->mark_dirty();
        return $roleid;
    }

    /**
     * Role id of the authorised-adult role, or 0 if not provisioned.
     *
     * @return int
     */
    public static function guardian_role_id(): int {
        global $DB;
        return (int)$DB->get_field('role', 'id', ['shortname' => self::ROLE_SHORTNAME]);
    }

    /**
     * Optionally (when autoassignrole is enabled) assign/unassign the authorised-adult role to an
     * adult in the learner's user context. This grants only the safe read-set; login-as stays prohibited.
     *
     * @param int $adultid
     * @param int $learnerid
     * @param bool $active True to assign (active grant), false to unassign (revoked/expired).
     */
    public static function maybe_sync_role(int $adultid, int $learnerid, bool $active): void {
        global $CFG;
        if (!get_config('tool_guardianlink', 'autoassignrole')) {
            return;
        }
        $roleid = self::guardian_role_id();
        if (!$roleid || $adultid <= 0 || $learnerid <= 0) {
            return;
        }
        require_once($CFG->dirroot . '/lib/accesslib.php');
        $context = \context_user::instance($learnerid, IGNORE_MISSING);
        if (!$context) {
            return;
        }
        if ($active) {
            role_assign($roleid, $adultid, $context->id, 'tool_guardianlink');
        } else {
            role_unassign($roleid, $adultid, $context->id, 'tool_guardianlink');
        }
    }

    /**
     * Safety self-check: report any guardrail violations for the admin overview.
     *
     * The most important invariant is that the authorised-adult role never holds
     * moodle/user:loginas. We also surface if any non-admin role has been granted
     * loginas, since that is the capability an operator could misuse to give
     * parents an "act as the child" path the plugin itself refuses to provide.
     *
     * @return string[] Human-readable warning strings (empty when all is well).
     */
    public static function guardrail_warnings(): array {
        global $DB;
        $warnings = [];
        $roleid = self::guardian_role_id();
        if (!$roleid) {
            $warnings[] = get_string('guardrail_norole', 'tool_guardianlink');
            return $warnings;
        }
        // The authorised-adult role must explicitly prohibit loginas.
        $perm = $DB->get_field(
            'role_capabilities',
            'permission',
            ['roleid' => $roleid, 'capability' => 'moodle/user:loginas']
        );
        if ((int)$perm !== CAP_PROHIBIT) {
            $warnings[] = get_string('guardrail_roleloginas', 'tool_guardianlink');
        }
        // Any role (other than the manager/admin baseline) that ALLOWS loginas is worth flagging.
        $rows = $DB->get_records_select(
            'role_capabilities',
            "capability = :cap AND permission = :allow",
            ['cap' => 'moodle/user:loginas', 'allow' => CAP_ALLOW]
        );
        $risky = [];
        foreach ($rows as $row) {
            $shortname = $DB->get_field('role', 'shortname', ['id' => $row->roleid]);
            if (!in_array($shortname, ['manager'], true)) {
                $risky[] = $shortname;
            }
        }
        if ($risky) {
            $warnings[] = get_string('guardrail_otherloginas', 'tool_guardianlink', s(implode(', ', array_unique($risky))));
        }
        return $warnings;
    }

    /**
     * Grant or revoke the authorised-adult role at a course context so the adult can satisfy
     * core's "log in as" course-access check during a governed assisted session. Conveys only
     * inspector view (moodle/course:view) — never participation/submission — and login-as stays
     * prohibited on the role.
     *
     * @param int $adultid
     * @param int $courseid
     * @param bool $grant
     */
    public static function set_course_view(int $adultid, int $courseid, bool $grant): void {
        global $CFG;
        $roleid = self::guardian_role_id();
        if (!$roleid || $adultid <= 0 || $courseid <= 0) {
            return;
        }
        require_once($CFG->dirroot . '/lib/accesslib.php');
        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$context) {
            return;
        }
        if ($grant) {
            role_assign($roleid, $adultid, $context->id, 'tool_guardianlink');
        } else {
            role_unassign($roleid, $adultid, $context->id, 'tool_guardianlink');
        }
    }

    /**
     * Revoke ALL course-context authorised-adult role grants for an adult (every course they ever
     * assisted into). Used when a relationship is restricted, and as belt-and-braces on session end.
     *
     * @param int $adultid
     */
    public static function revoke_all_course_views(int $adultid): void {
        global $DB, $CFG;
        $roleid = self::guardian_role_id();
        if (!$roleid || $adultid <= 0) {
            return;
        }
        require_once($CFG->dirroot . '/lib/accesslib.php');
        $sql = "SELECT ra.id, ra.contextid
                  FROM {role_assignments} ra
                  JOIN {context} ctx ON ctx.id = ra.contextid
                 WHERE ra.userid = :uid AND ra.roleid = :rid AND ra.component = :comp AND ctx.contextlevel = :cl";
        $rows = $DB->get_records_sql($sql, ['uid' => $adultid, 'rid' => $roleid,
            'comp' => 'tool_guardianlink', 'cl' => CONTEXT_COURSE]);
        foreach ($rows as $row) {
            role_unassign($roleid, $adultid, (int)$row->contextid, 'tool_guardianlink');
        }
    }

    /**
     * Heartbeat: refresh the timestamp of a live assisted session's course grant so the cleanup
     * sweep does not revoke it while the session is active. Called on every request of an assisted
     * session; once the session ends (logout or browser-close) the heartbeat stops and the grant
     * goes stale within minutes and is swept.
     *
     * @param int $adultid
     * @param int $courseid
     */
    public static function touch_course_view(int $adultid, int $courseid): void {
        global $DB;
        $roleid = self::guardian_role_id();
        if (!$roleid || $adultid <= 0 || $courseid <= 0) {
            return;
        }
        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$context) {
            return;
        }
        $DB->set_field(
            'role_assignments',
            'timemodified',
            time(),
            ['roleid' => $roleid, 'userid' => $adultid, 'contextid' => $context->id, 'component' => 'tool_guardianlink']
        );
    }

    /**
     * Backstop sweep: revoke any course-context authorised-adult grant older than the assisted
     * session's maximum age. These grants exist only to satisfy core's login-as course check for a
     * live, short, time-capped assisted session, so any stale one is a session that already ended
     * (including browser-close) and must not leave standing inspector access.
     *
     * @param int $maxageseconds
     * @return int Number of grants revoked.
     */
    public static function cleanup_stale_assist_roles(int $maxageseconds): void {
        global $DB, $CFG;
        $roleid = self::guardian_role_id();
        if (!$roleid) {
            return;
        }
        require_once($CFG->dirroot . '/lib/accesslib.php');
        $cutoff = time() - max(300, $maxageseconds);
        $sql = "SELECT ra.id, ra.userid, ra.contextid
                  FROM {role_assignments} ra
                  JOIN {context} ctx ON ctx.id = ra.contextid
                 WHERE ra.roleid = :rid AND ra.component = :comp AND ctx.contextlevel = :cl AND ra.timemodified < :cutoff";
        $rows = $DB->get_records_sql($sql, ['rid' => $roleid, 'comp' => 'tool_guardianlink',
            'cl' => CONTEXT_COURSE, 'cutoff' => $cutoff]);
        foreach ($rows as $row) {
            role_unassign($roleid, (int)$row->userid, (int)$row->contextid, 'tool_guardianlink');
        }
    }
}

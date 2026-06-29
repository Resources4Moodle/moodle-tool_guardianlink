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
 * Privacy provider for GuardianLink.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guardianlink\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;

/**
 * Privacy provider.
 *
 * GuardianLink stores relationship, communication, health/care, organisation,
 * and access-audit data. Deletion deliberately preserves audit by default so
 * the institution can apply statutory retention and safeguarding review.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe personal data stored by this plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('tool_guardianlink_rel', [
            'guardianid' => 'privacy:metadata:tool_guardianlink_rel:guardianid',
            'childid' => 'privacy:metadata:tool_guardianlink_rel:childid',
            'reltype' => 'privacy:metadata:tool_guardianlink_rel:reltype',
            'authoritybasis' => 'privacy:metadata:tool_guardianlink_rel:authoritybasis',
            'authoritystatus' => 'privacy:metadata:tool_guardianlink_rel:authoritystatus',
            'confidentiality' => 'privacy:metadata:tool_guardianlink_rel:confidentiality',
            'legal' => 'privacy:metadata:tool_guardianlink_rel:legal',
            'status' => 'privacy:metadata:tool_guardianlink_rel:status',
            'notes' => 'privacy:metadata:tool_guardianlink_rel:notes',
        ], 'privacy:metadata:tool_guardianlink_rel');
        $collection->add_database_table('tool_guardianlink_scope', [
            'relationshipid' => 'privacy:metadata:tool_guardianlink_rel',
            'courseid' => 'course',
            'categoryid' => 'coursecategory',
        ], 'privacy:metadata:tool_guardianlink_scope');
        $collection->add_database_table('tool_guardianlink_accesslog', [
            'actorid' => 'userid',
            'childid' => 'privacy:metadata:tool_guardianlink_rel:childid',
            'action' => 'action',
            'ip' => 'ip',
            'sourcecode' => 'source',
            'otherjson' => 'other',
        ], 'privacy:metadata:tool_guardianlink_accesslog');
        $collection->add_database_table('tool_guardianlink_health', [
            'childid' => 'privacy:metadata:tool_guardianlink_rel:childid',
            'healthtype' => 'privacy:metadata:tool_guardianlink_health',
            'title' => 'privacy:metadata:tool_guardianlink_health',
            'summary' => 'privacy:metadata:tool_guardianlink_health',
            'visibility' => 'privacy:metadata:tool_guardianlink_health',
        ], 'privacy:metadata:tool_guardianlink_health');
        $collection->add_database_table('tool_guardianlink_msgthread', [
            'teacherid' => 'teacher',
            'guardianid' => 'privacy:metadata:tool_guardianlink_rel:guardianid',
            'childid' => 'privacy:metadata:tool_guardianlink_rel:childid',
            'subject' => 'subject',
            'lastmessage' => 'privacy:metadata:tool_guardianlink_msgthread:lastmessage',
        ], 'privacy:metadata:tool_guardianlink_msgthread');
        $collection->add_database_table('tool_guardianlink_courseconfig', [
            'createdby' => 'userid',
        ], 'privacy:metadata:tool_guardianlink_courseconfig');
        $collection->add_database_table('tool_guardianlink_template', [
            'createdby' => 'userid',
        ], 'privacy:metadata:tool_guardianlink_template');
        $collection->add_database_table('tool_guardianlink_proof', [
            'userid' => 'userid',
            'note' => 'privacy:metadata:tool_guardianlink_proof',
        ], 'privacy:metadata:tool_guardianlink_proof');
        $collection->add_database_table('tool_guardianlink_tutorreq', [
            'requesterid' => 'requester',
            'tutorid' => 'tutor',
            'childid' => 'privacy:metadata:tool_guardianlink_rel:childid',
            'purpose' => 'purpose',
        ], 'privacy:metadata:tool_guardianlink_tutorreq');
        $collection->add_database_table('tool_guardianlink_orgmember', [
            'adultid' => 'privacy:metadata:tool_guardianlink_rel:guardianid',
            'rolecode' => 'role',
        ], 'privacy:metadata:tool_guardianlink_orgmember');
        $collection->add_database_table('tool_guardianlink_policy', [
            'userid' => 'userid',
            'childid' => 'privacy:metadata:tool_guardianlink_rel:childid',
            'policykey' => 'privacy:metadata:tool_guardianlink_policy',
            'status' => 'privacy:metadata:tool_guardianlink_policy',
        ], 'privacy:metadata:tool_guardianlink_policy');
        $collection->add_database_table('tool_guardianlink_extmap', [
            'moodleuserid' => 'userid',
        ], 'privacy:metadata:tool_guardianlink_extmap');
        $collection->add_database_table('tool_guardianlink_erpsync', [
            'userid' => 'userid',
        ], 'privacy:metadata:tool_guardianlink_erpsync');
        $collection->add_database_table('tool_guardianlink_digestpref', [
            'guardianid' => 'privacy:metadata:tool_guardianlink_rel:guardianid',
            'childid' => 'privacy:metadata:tool_guardianlink_rel:childid',
            'frequency' => 'frequency',
            'channels' => 'channels',
            'includegrades' => 'includegrades',
            'includeattendance' => 'includeattendance',
            'includehealth' => 'includehealth',
            'lastsent' => 'lastsent',
            'nextsend' => 'nextsend',
        ], 'privacy:metadata:tool_guardianlink_digestpref');
        $collection->add_database_table('tool_guardianlink_indack', [
            'guardianid' => 'privacy:metadata:tool_guardianlink_rel:guardianid',
            'childid' => 'privacy:metadata:tool_guardianlink_rel:childid',
            'status' => 'privacy:metadata:tool_guardianlink_indack',
            'note' => 'privacy:metadata:tool_guardianlink_indack',
        ], 'privacy:metadata:tool_guardianlink_indack');
        return $collection;
    }

    /**
     * Get contexts containing data for a user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                 WHERE ctx.contextlevel = :usercontext
                   AND ctx.instanceid IN (
                       SELECT childid FROM {tool_guardianlink_rel} WHERE childid = :userid1 OR guardianid = :userid2
                       UNION SELECT childid FROM {tool_guardianlink_accesslog} WHERE childid = :userid3 OR actorid = :userid4
                       UNION SELECT childid FROM {tool_guardianlink_msgthread}
                              WHERE childid = :userid5 OR guardianid = :userid6 OR teacherid = :userid7
                       UNION SELECT childid FROM {tool_guardianlink_tutorreq}
                              WHERE childid = :userid8 OR requesterid = :userid9 OR tutorid = :userid10
                       UNION SELECT childid FROM {tool_guardianlink_health}
                              WHERE childid = :userid11 OR createdby = :userid12 OR approvedby = :userid13
                       UNION SELECT childid FROM {tool_guardianlink_digestpref} WHERE childid = :userid14 OR guardianid = :userid15
                       UNION SELECT childid FROM {tool_guardianlink_policy} WHERE childid = :userid16 OR userid = :userid17
                       UNION SELECT childid FROM {tool_guardianlink_indack} WHERE childid = :userid18 OR guardianid = :userid19
                   )";
        $contextlist->add_from_sql($sql, [
            'usercontext' => CONTEXT_USER,
            'userid1' => $userid,
            'userid2' => $userid,
            'userid3' => $userid,
            'userid4' => $userid,
            'userid5' => $userid,
            'userid6' => $userid,
            'userid7' => $userid,
            'userid8' => $userid,
            'userid9' => $userid,
            'userid10' => $userid,
            'userid11' => $userid,
            'userid12' => $userid,
            'userid13' => $userid,
            'userid14' => $userid,
            'userid15' => $userid,
            'userid16' => $userid,
            'userid17' => $userid,
            'userid18' => $userid,
            'userid19' => $userid,
        ]);
        return $contextlist;
    }

    /**
     * Export user data.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = (int)$contextlist->get_user()->id;
        // Every declared context is a LEARNER user context (instanceid = childid). The requesting user
        // may be that learner OR an adult/teacher/actor linked to them, so we export, per learner
        // context, all rows where the requester participates AND the row concerns that learner.
        // (The previous implementation skipped any context whose instanceid was not the requester, so
        // adults' and teachers' data — stored against learners' contexts — never exported.)
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_USER) {
                continue;
            }
            $learnerid = (int)$context->instanceid;
            $data = (object)[
                'relationships' => self::export_rows(
                    'tool_guardianlink_rel',
                    'childid = :l AND (guardianid = :u1 OR childid = :u2)',
                    ['l' => $learnerid, 'u1' => $userid, 'u2' => $userid]
                ),
                'recentaccesslog' => self::export_rows(
                    'tool_guardianlink_accesslog',
                    'childid = :l AND (actorid = :u1 OR childid = :u2)',
                    ['l' => $learnerid, 'u1' => $userid, 'u2' => $userid],
                    'timecreated DESC',
                    500
                ),
                'healthrecords' => self::export_rows(
                    'tool_guardianlink_health',
                    'childid = :l AND (childid = :u1 OR createdby = :u2 OR approvedby = :u3)',
                    ['l' => $learnerid, 'u1' => $userid, 'u2' => $userid, 'u3' => $userid],
                    'timemodified DESC'
                ),
                'tutorrequests' => self::export_rows(
                    'tool_guardianlink_tutorreq',
                    'childid = :l AND (requesterid = :u1 OR tutorid = :u2 OR childid = :u3)',
                    ['l' => $learnerid, 'u1' => $userid, 'u2' => $userid, 'u3' => $userid],
                    'timemodified DESC'
                ),
                'digestpreferences' => self::export_rows(
                    'tool_guardianlink_digestpref',
                    'childid = :l AND (guardianid = :u1 OR childid = :u2)',
                    ['l' => $learnerid, 'u1' => $userid, 'u2' => $userid]
                ),
                'policyacknowledgements' => self::export_rows(
                    'tool_guardianlink_policy',
                    'childid = :l AND (userid = :u1 OR childid = :u2)',
                    ['l' => $learnerid, 'u1' => $userid, 'u2' => $userid]
                ),
                'messagethreads' => self::export_rows(
                    'tool_guardianlink_msgthread',
                    'childid = :l AND (teacherid = :u1 OR guardianid = :u2 OR childid = :u3)',
                    ['l' => $learnerid, 'u1' => $userid, 'u2' => $userid, 'u3' => $userid],
                    'timemodified DESC'
                ),
                'independentaccess' => self::export_rows(
                    'tool_guardianlink_indack',
                    'childid = :l AND (guardianid = :u1 OR childid = :u2)',
                    ['l' => $learnerid, 'u1' => $userid, 'u2' => $userid],
                    'timemodified DESC'
                ),
            ];
            \core_privacy\local\request\writer::with_context($context)->export_data(
                [get_string('pluginname', 'tool_guardianlink')],
                $data
            );
        }
    }

    /**
     * Fetch and normalise a set of rows for export.
     *
     * @param string $table Table name (without prefix)
     * @param string $select SQL WHERE fragment
     * @param array $params Named parameters
     * @param string $sort Optional ORDER BY clause
     * @param int $limit Optional row cap (0 = no cap)
     * @return array Exportable records
     */
    private static function export_rows(
        string $table,
        string $select,
        array $params,
        string $sort = '',
        int $limit = 0
    ): array {
        global $DB;
        $records = $DB->get_records_select($table, $select, $params, $sort, '*', 0, $limit);
        return array_values(array_map([self::class, 'exportable_record'], $records));
    }

    /**
     * Erase a user's deletable GuardianLink personal data and anonymise their messages.
     *
     * The deletion model has three documented tiers:
     *  - Deleted now (no retention basis): the user's digest preferences and policy/consent records.
     *  - Anonymised now (free-text communications): the subject/body of message threads the user took
     *    part in are redacted (the thread shell is kept so the other party's record stays coherent;
     *    GuardianLink never stored contact details in it).
     *  - Retained (statutory safeguarding / audit / legal): relationships, scopes, the access log,
     *    health/care summaries, tutor requests, proof, independent-access acknowledgements and
     *    organisation membership are kept under the institution's documented retention policy and are
     *    anonymised or purged through that governed workflow, not by an automatic erasure request.
     *
     * @param int $userid
     */
    protected static function erase_user_data(int $userid): void {
        global $DB;
        $DB->delete_records_select(
            'tool_guardianlink_digestpref',
            'guardianid = :a OR childid = :b',
            ['a' => $userid, 'b' => $userid]
        );
        $DB->delete_records_select(
            'tool_guardianlink_policy',
            'userid = :a OR childid = :b',
            ['a' => $userid, 'b' => $userid]
        );
        $redacted = get_string('privacy:erased', 'tool_guardianlink');
        $threads = $DB->get_records_select(
            'tool_guardianlink_msgthread',
            'teacherid = :a OR guardianid = :b OR childid = :c',
            ['a' => $userid, 'b' => $userid, 'c' => $userid]
        );
        foreach ($threads as $thread) {
            $thread->subject = $redacted;
            $thread->lastmessage = $redacted;
            $thread->timemodified = time();
            $DB->update_record('tool_guardianlink_msgthread', $thread);
        }
    }

    /**
     * Delete deletable data for everyone in a (user) context; retains safeguarding/audit records.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        if ($context->contextlevel === CONTEXT_USER) {
            self::erase_user_data((int)$context->instanceid);
        }
    }

    /**
     * Delete deletable data for the requesting user; retains safeguarding/audit records.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        self::erase_user_data((int)$contextlist->get_user()->id);
    }

    /**
     * Add users in a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_USER) {
            return;
        }
        $userid = (int)$context->instanceid;
        $sql = "SELECT guardianid AS userid FROM {tool_guardianlink_rel} WHERE childid = :userid1
                UNION SELECT childid AS userid FROM {tool_guardianlink_rel} WHERE guardianid = :userid2
                UNION SELECT actorid AS userid FROM {tool_guardianlink_accesslog} WHERE childid = :userid3
                UNION SELECT childid AS userid FROM {tool_guardianlink_accesslog} WHERE actorid = :userid4
                UNION SELECT requesterid AS userid FROM {tool_guardianlink_tutorreq} WHERE childid = :userid5
                UNION SELECT tutorid AS userid FROM {tool_guardianlink_tutorreq} WHERE childid = :userid6
                UNION SELECT guardianid AS userid FROM {tool_guardianlink_msgthread} WHERE childid = :userid7
                UNION SELECT teacherid AS userid FROM {tool_guardianlink_msgthread} WHERE childid = :userid8
                UNION SELECT userid AS userid FROM {tool_guardianlink_policy} WHERE childid = :userid9
                UNION SELECT guardianid AS userid FROM {tool_guardianlink_indack} WHERE childid = :userid10
                UNION SELECT childid AS userid FROM {tool_guardianlink_indack} WHERE guardianid = :userid11";
        $userlist->add_from_sql('userid', $sql, [
            'userid1' => $userid,
            'userid2' => $userid,
            'userid3' => $userid,
            'userid4' => $userid,
            'userid5' => $userid,
            'userid6' => $userid,
            'userid7' => $userid,
            'userid8' => $userid,
            'userid9' => $userid,
            'userid10' => $userid,
            'userid11' => $userid,
        ]);
    }

    /**
     * Delete data for a list of users. Audit-preserving by default.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        if ($userlist->get_context()->contextlevel !== CONTEXT_USER) {
            return;
        }
        foreach ($userlist->get_userids() as $userid) {
            self::erase_user_data((int)$userid);
        }
    }

    /**
     * Prepare record for export.
     *
     * @param object $record
     * @return object
     */
    private static function exportable_record(object $record): object {
        $copy = clone $record;
        $timefields = [
            'timecreated', 'timemodified', 'starttime', 'endtime',
            'reviewtime', 'lastsynced', 'timeaccepted', 'expires',
        ];
        foreach ($timefields as $field) {
            if (!empty($copy->{$field})) {
                $copy->{$field} = transform::datetime($copy->{$field});
            }
        }
        return $copy;
    }
}

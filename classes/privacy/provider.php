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
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_USER || (int)$context->instanceid !== (int)$userid) {
                continue;
            }
            $data = (object)[
                'relationships' => array_values(array_map([self::class, 'exportable_record'], $DB->get_records_select(
                    'tool_guardianlink_rel',
                    'guardianid = :userid1 OR childid = :userid2',
                    ['userid1' => $userid, 'userid2' => $userid]
                ))),
                'recentaccesslog' => array_values(array_map([self::class, 'exportable_record'], $DB->get_records_select(
                    'tool_guardianlink_accesslog',
                    'actorid = :userid1 OR childid = :userid2',
                    ['userid1' => $userid, 'userid2' => $userid],
                    'timecreated DESC',
                    '*',
                    0,
                    500
                ))),
                'healthrecords' => array_values(array_map([self::class, 'exportable_record'], $DB->get_records(
                    'tool_guardianlink_health',
                    ['childid' => $userid],
                    'timemodified DESC'
                ))),
                'tutorrequests' => array_values(array_map([self::class, 'exportable_record'], $DB->get_records_select(
                    'tool_guardianlink_tutorreq',
                    'requesterid = :userid1 OR tutorid = :userid2 OR childid = :userid3',
                    ['userid1' => $userid, 'userid2' => $userid, 'userid3' => $userid],
                    'timemodified DESC'
                ))),
                'digestpreferences' => array_values(array_map([self::class, 'exportable_record'], $DB->get_records_select(
                    'tool_guardianlink_digestpref',
                    'guardianid = :userid1 OR childid = :userid2',
                    ['userid1' => $userid, 'userid2' => $userid]
                ))),
                'policyacknowledgements' => array_values(array_map([self::class, 'exportable_record'], $DB->get_records_select(
                    'tool_guardianlink_policy',
                    'userid = :userid1 OR childid = :userid2',
                    ['userid1' => $userid, 'userid2' => $userid]
                ))),
                'messagethreads' => array_values(array_map([self::class, 'exportable_record'], $DB->get_records_select(
                    'tool_guardianlink_msgthread',
                    'teacherid = :userid1 OR guardianid = :userid2 OR childid = :userid3',
                    ['userid1' => $userid, 'userid2' => $userid, 'userid3' => $userid],
                    'timemodified DESC'
                ))),
                'independentaccess' => array_values(array_map([self::class, 'exportable_record'], $DB->get_records_select(
                    'tool_guardianlink_indack',
                    'guardianid = :userid1 OR childid = :userid2',
                    ['userid1' => $userid, 'userid2' => $userid],
                    'timemodified DESC'
                ))),
            ];
            \core_privacy\local\request\writer::with_context($context)->export_data(
                [get_string('pluginname', 'tool_guardianlink')],
                $data
            );
        }
    }

    /**
     * Delete all data in a context. Audit-preserving by default.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        // Do not automatically delete records from a user context. Relationship,
        // access, and health/care summaries may be legal or safeguarding records.
    }

    /**
     * Delete data for a user. Audit-preserving by default.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        // Retention and anonymisation should be performed through an approved
        // institutional workflow, not automatic user-context deletion.
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
        // Same audit-preserving policy as delete_data_for_user().
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

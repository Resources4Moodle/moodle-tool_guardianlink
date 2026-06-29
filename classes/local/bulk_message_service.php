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
 * Bulk audience messaging for GuardianLink.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guardianlink\local;

/**
 * Privacy-preserving bulk message integration.
 *
 * Resolves an audience (course, category, or cohort) to the set of authorised,
 * verified adults who are permitted to be contacted, then sends one message to
 * each through Moodle's Message API. Senders never see adult contact details;
 * recipients are resolved entirely server-side and every send is audited.
 */
class bulk_message_service {
    /** @var string[] Confidentiality tiers excluded when "exclude restricted contact" is on. */
    private const RESTRICTED_TIERS = ['restricted', 'sensitive', 'safeguarding'];

    /**
     * Normalise raw criteria into a predictable shape.
     *
     * @param object|array $criteria
     * @return array
     */
    public static function normalise_criteria(object|array $criteria): array {
        $get = function ($key, $default = null) use ($criteria) {
            if (is_array($criteria)) {
                return array_key_exists($key, $criteria) ? $criteria[$key] : $default;
            }
            return property_exists($criteria, $key) ? $criteria->{$key} : $default;
        };
        $type = clean_param((string)$get('audiencetype', 'course'), PARAM_ALPHA);
        if (!in_array($type, ['course', 'category', 'cohort', 'overdue'], true)) {
            $type = 'course';
        }
        return [
            'audiencetype' => $type,
            'courseid' => (int)$get('courseid', 0),
            'categoryid' => (int)$get('categoryid', 0),
            'cohortid' => (int)$get('cohortid', 0),
            'legalonly' => (int)$get('legalonly', 0) ? 1 : 0,
            'verifiedonly' => $get('verifiedonly', 1) === null ? 1 : ((int)$get('verifiedonly', 1) ? 1 : 0),
            'excluderestricted' => (int)$get('excluderestricted', 1) ? 1 : 0,
        ];
    }

    /**
     * Resolve the learner user ids that make up an audience.
     *
     * @param array $criteria Normalised criteria.
     * @return int[] Unique learner user ids.
     */
    public static function get_learner_ids_for_audience(array $criteria): array {
        global $DB, $CFG;
        $ids = [];
        switch ($criteria['audiencetype']) {
            case 'course':
                if ($criteria['courseid'] > 0 && $DB->record_exists('course', ['id' => $criteria['courseid']])) {
                    $ids = self::enrolled_learner_ids($criteria['courseid']);
                }
                break;
            case 'category':
                if ($criteria['categoryid'] > 0) {
                    $category = \core_course_category::get($criteria['categoryid'], IGNORE_MISSING, true);
                    if ($category) {
                        foreach ($category->get_courses(['recursive' => true, 'idonly' => true]) as $courseid) {
                            $ids = array_merge($ids, self::enrolled_learner_ids((int)$courseid));
                        }
                    }
                }
                break;
            case 'cohort':
                if ($criteria['cohortid'] > 0 && $DB->record_exists('cohort', ['id' => $criteria['cohortid']])) {
                    $ids = $DB->get_fieldset_select('cohort_members', 'userid', 'cohortid = ?', [$criteria['cohortid']]);
                }
                break;
            case 'overdue':
                // At-risk audience: learners in the course who currently have overdue work.
                if ($criteria['courseid'] > 0 && $DB->record_exists('course', ['id' => $criteria['courseid']])) {
                    $ids = progress_service::overdue_learner_ids($criteria['courseid']);
                }
                break;
        }
        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * Active-enrolled user ids for a course.
     *
     * @param int $courseid
     * @return int[]
     */
    private static function enrolled_learner_ids(int $courseid): array {
        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$context) {
            return [];
        }
        // Onlyactive = true so suspended enrolments are excluded.
        $users = get_enrolled_users($context, '', 0, 'u.id', null, 0, 0, true);
        return array_keys($users);
    }

    /**
     * Resolve the unique adult recipients for an audience.
     *
     * @param array $criteria Normalised criteria.
     * @return array Recipient records keyed by adult user id (id, firstname, lastname, email, learnercount).
     */
    public static function resolve_recipients(array $criteria): array {
        global $DB;
        $learnerids = self::get_learner_ids_for_audience($criteria);
        if (empty($learnerids)) {
            return [];
        }
        $now = time();
        [$insql, $params] = $DB->get_in_or_equal($learnerids, SQL_PARAMS_NAMED, 'lid');
        // Shared scope eligibility (active scope time window + course/learner/site/category coverage),
        // identical to proxy messaging. For broad audiences (category/cohort/site) there is no single
        // course, so only the scope time window applies.
        $coursetarget = (in_array($criteria['audiencetype'], ['course', 'overdue'], true) && $criteria['courseid'] > 0)
            ? (int)$criteria['courseid']
            : 0;
        [$coursejoin, $scopeparams] = relationship_service::messaging_scope_sql($coursetarget, 'bmsc');
        $params += $scopeparams;
        $where = '';
        if ($criteria['legalonly']) {
            $where .= ' AND r.legal = 1';
        }
        // Recipient eligibility always requires a VERIFIED relationship — never message an adult whose
        // authority is revoked, restricted, disputed or unverified. This mirrors the access invariant
        // and is independent of the optional confidentiality-tier (excluderestricted) filter below.
        $where .= " AND r.authoritystatus = :verified";
        $params['verified'] = relationship_service::AUTHORITY_VERIFIED;
        if ($criteria['excluderestricted']) {
            [$cinsql, $cparams] = $DB->get_in_or_equal(self::RESTRICTED_TIERS, SQL_PARAMS_NAMED, 'ctier', false);
            $where .= " AND r.confidentiality $cinsql";
            $params += $cparams;
        }
        $params += [
            'active1' => relationship_service::STATUS_ACTIVE,
            'active2' => relationship_service::STATUS_ACTIVE,
            'now1' => $now, 'now2' => $now,
        ];
        $sql = "SELECT u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                       u.middlename, u.alternatename, u.email,
                       COUNT(DISTINCT r.childid) AS learnercount,
                       MIN(r.id) AS relationshipid
                  FROM {tool_guardianlink_rel} r
                  JOIN {tool_guardianlink_scope} s ON s.relationshipid = r.id
                  JOIN {user} u ON u.id = r.guardianid
                 WHERE r.childid $insql
                   AND r.status = :active1
                   AND s.status = :active2
                   AND s.allowteachercontact = 1
                   AND s.allowmessaging = 1
                   AND (r.starttime = 0 OR r.starttime <= :now1)
                   AND (r.endtime = 0 OR r.endtime >= :now2)
                   AND u.deleted = 0
                   AND u.suspended = 0
                   $coursejoin
                   $where
              GROUP BY u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                       u.middlename, u.alternatename, u.email
              ORDER BY u.lastname, u.firstname";
        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Count unique adult recipients for an audience (for preview).
     *
     * @param array $criteria Normalised criteria.
     * @return int
     */
    public static function count_recipients(array $criteria): int {
        return count(self::resolve_recipients($criteria));
    }

    /**
     * Send a bulk message to a resolved audience through Moodle messaging.
     *
     * @param int $senderid
     * @param array $criteria Normalised criteria.
     * @param string $subject
     * @param string $body
     * @return array ['sent' => int, 'failed' => int, 'recipients' => int]
     */
    public static function send_bulk_message(int $senderid, array $criteria, string $subject, string $body): array {
        $recipients = self::resolve_recipients($criteria);
        $sender = \core_user::get_user($senderid, '*', MUST_EXIST);
        $subject = clean_param($subject, PARAM_TEXT);
        // Privacy-preserving addressing: send from the no-reply user with the sender's NAME (never
        // email) in the body, and Reply-To forced to no-reply, so no real email can reach the headers.
        $noreply = \core_user::get_noreply_user();
        $full = get_string('messagefrom', 'tool_guardianlink', fullname($sender)) . "\n\n" . $body;
        $sent = 0;
        $failed = 0;
        foreach ($recipients as $recipient) {
            try {
                $message = new \core\message\message();
                $message->component = 'tool_guardianlink';
                $message->name = 'bulk_message';
                $message->userfrom = $noreply;
                $message->replyto = $noreply->email;
                $message->replytoname = get_string('noreplyname');
                $message->userto = \core_user::get_user((int)$recipient->id, '*', MUST_EXIST);
                $message->subject = $subject;
                $message->fullmessage = $full;
                $message->fullmessageformat = FORMAT_PLAIN;
                $message->fullmessagehtml = format_text($full, FORMAT_PLAIN);
                $message->smallmessage = $subject;
                $message->notification = 1;
                if (message_send($message)) {
                    $sent++;
                } else {
                    $failed++;
                }
            } catch (\Throwable $e) {
                $failed++;
            }
        }
        relationship_service::trigger_event('bulk_message_sent', \context_system::instance(), 0, 0, [
            'audiencetype' => $criteria['audiencetype'],
            'recipients' => count($recipients),
            'sent' => $sent,
        ]);
        relationship_service::log_access($senderid, 0, 'bulk_message_sent', $criteria['courseid'] ?? 0, 'bulkmail', 0, [
            'audiencetype' => $criteria['audiencetype'],
            'recipients' => count($recipients),
            'sent' => $sent,
            'failed' => $failed,
        ]);
        return ['sent' => $sent, 'failed' => $failed, 'recipients' => count($recipients)];
    }
}

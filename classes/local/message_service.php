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
 * Proxy messaging and digest helpers for GuardianLink.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guardianlink\local;

/**
 * Messaging service for privacy-preserving adult/teacher communication.
 */
class message_service {
    /**
     * Send a proxy message from teacher/staff to authorised adults for a learner/course.
     *
     * @param int $senderid
     * @param int $learnerid
     * @param int $courseid
     * @param string $subject
     * @param string $body
     * @return array
     */
    /**
     * Build a privacy-preserving message. It is sent FROM the no-reply user with the human sender's
     * NAME (never their email) carried in the body, and Reply-To forced to no-reply. This guarantees
     * that no real participant's email address can ever reach the email From/Reply-To headers,
     * regardless of $CFG->allowedemaildomains or maildisplay. Replies happen in-app via the thread link.
     *
     * @param string $name Message provider name.
     * @param \stdClass $sender The human sender (used for display name only).
     * @param string $subject
     * @param string $body
     * @return \core\message\message
     */
    private static function privacy_message(string $name, \stdClass $sender, string $subject, string $body): \core\message\message {
        $noreply = \core_user::get_noreply_user();
        $label = get_string('messagefrom', 'tool_guardianlink', fullname($sender));
        $full = $label . "\n\n" . $body;
        $message = new \core\message\message();
        $message->component = 'tool_guardianlink';
        $message->name = $name;
        $message->userfrom = $noreply;
        $message->replyto = $noreply->email;
        $message->replytoname = get_string('noreplyname');
        $message->subject = clean_param($subject, PARAM_TEXT);
        $message->fullmessage = $full;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = format_text($full, FORMAT_PLAIN);
        $message->smallmessage = clean_param($subject, PARAM_TEXT);
        $message->notification = 1;
        return $message;
    }

    /**
     * Send a message from an authorised adult to the learner's proxy recipients.
     *
     * @param int $senderid The user id of the sending adult.
     * @param int $learnerid The learner whose proxy recipients receive the message.
     * @param int $courseid The course context for the message.
     * @param string $subject The message subject.
     * @param string $body The message body.
     * @return array Result details about the dispatched messages.
     */
    public static function send_proxy_message(int $senderid, int $learnerid, int $courseid, string $subject, string $body): array {
        global $DB;
        $recipients = relationship_service::get_proxy_recipients($learnerid, $courseid);
        $sent = 0;
        $threads = [];
        $sender = \core_user::get_user($senderid, '*', MUST_EXIST);
        $course = get_course($courseid);
        foreach ($recipients as $recipient) {
            $thread = (object)[
                'childid' => $learnerid,
                'courseid' => $courseid,
                'teacherid' => $senderid,
                'guardianid' => (int)$recipient->id,
                'relationshipid' => (int)$recipient->relationshipid,
                'subject' => clean_param($subject, PARAM_TEXT),
                'lastmessage' => clean_param($body, PARAM_TEXT),
                'status' => 'open',
                'timecreated' => time(),
                'timemodified' => time(),
            ];
            $threadid = (int)$DB->insert_record('tool_guardianlink_msgthread', $thread);
            $message = self::privacy_message('proxy_message', $sender, $subject, $body);
            $message->userto = \core_user::get_user((int)$recipient->id, '*', MUST_EXIST);
            $message->contexturl = (new \moodle_url(
                '/admin/tool/guardianlink/thread.php',
                ['id' => $threadid]
            ))->out(false);
            $message->contexturlname = format_string($course->fullname);
            message_send($message);
            relationship_service::trigger_event(
                'proxy_message_sent',
                \context_course::instance($courseid),
                $learnerid,
                $threadid,
                ['courseid' => $courseid]
            );
            relationship_service::log_access(
                $senderid,
                $learnerid,
                'proxy_message_sent',
                $courseid,
                'msgthread',
                $threadid,
                ['guardianid' => (int)$recipient->id]
            );
            $sent++;
            $threads[] = $threadid;
        }
        return ['sent' => $sent, 'threads' => $threads];
    }

    /**
     * Teacher -> authorised-adults message built from an (ad-hoc or stored) template: HTML body with
     * {placeholders} rendered per recipient, honouring each recipient's grades scope, recorded as
     * threads so adults can reply in-app. Optionally targets a specific test/activity via
     * $extra['gradeitemid'] to fill {testname}/{testresult}.
     *
     * @param int $senderid Teacher/staff user id.
     * @param int $learnerid
     * @param int $courseid
     * @param \stdClass $template Object with ->subject, ->body, ->bodyformat.
     * @param array $extra Extra context (e.g. ['gradeitemid' => 42]).
     * @return array ['sent' => int, 'recipients' => int]
     */
    public static function send_proxy_template(
        int $senderid,
        int $learnerid,
        int $courseid,
        \stdClass $template,
        array $extra = []
    ): array {
        $recipients = relationship_service::get_proxy_recipients($learnerid, $courseid);
        $sent = 0;
        foreach ($recipients as $recipient) {
            $adultid = (int)$recipient->id;
            $grades = relationship_service::can_access_child($adultid, $learnerid, $courseid, 'grades');
            $ctx = template_service::context($adultid, $learnerid, $courseid, $grades, $extra);
            $rendered = template_service::render($template, $ctx);
            if (
                self::send_one_off(
                    $adultid,
                    $learnerid,
                    $courseid,
                    $rendered['subject'],
                    $rendered['body'],
                    $senderid,
                    'proxy_message_sent'
                )
            ) {
                $sent++;
            }
        }
        return ['sent' => $sent, 'recipients' => count($recipients)];
    }

    /**
     * Reply within an existing proxy thread (teacher <-> authorised adult), keeping the conversation
     * inside GuardianLink and preserving the no-contact-detail-exposure guarantee both ways.
     *
     * @param int $threadid
     * @param int $senderid
     * @param string $body
     * @return bool
     */
    public static function reply_to_thread(int $threadid, int $senderid, string $body): bool {
        global $DB;
        $thread = $DB->get_record('tool_guardianlink_msgthread', ['id' => $threadid], '*', MUST_EXIST);
        // Only the two participants of the thread may reply.
        if (!in_array($senderid, [(int)$thread->teacherid, (int)$thread->guardianid], true)) {
            throw new \moodle_exception('accessdenied', 'tool_guardianlink');
        }
        $toid = ($senderid === (int)$thread->teacherid) ? (int)$thread->guardianid : (int)$thread->teacherid;
        $sender = \core_user::get_user($senderid, '*', MUST_EXIST);
        $message = self::privacy_message(
            'proxy_message',
            $sender,
            get_string('replysubject', 'tool_guardianlink', $thread->subject),
            $body
        );
        $message->userto = \core_user::get_user($toid, '*', MUST_EXIST);
        $message->contexturl = (new \moodle_url('/admin/tool/guardianlink/thread.php', ['id' => $threadid]))->out(false);
        $message->contexturlname = format_string((string)$thread->subject);
        message_send($message);
        $thread->lastmessage = clean_param($body, PARAM_TEXT);
        $thread->timemodified = time();
        $DB->update_record('tool_guardianlink_msgthread', $thread);
        relationship_service::log_access(
            $senderid,
            (int)$thread->childid,
            'proxy_reply_sent',
            (int)$thread->courseid,
            'msgthread',
            $threadid
        );
        return true;
    }

    /**
     * Send a rendered template (e.g. results/performance) to a learner's authorised adults. Used by
     * teachers to email parents results. Keeps the no-leak guarantee: sent from the no-reply user
     * with the teacher's NAME (not email) in the body; the template's HTML body is preserved.
     *
     * @param int $senderid Teacher/staff user id.
     * @param int $learnerid
     * @param int $courseid
     * @param \stdClass $template
     * @return array ['sent' => int, 'recipients' => int]
     */
    public static function send_template_to_adults(int $senderid, int $learnerid, int $courseid, \stdClass $template): array {
        $recipients = relationship_service::get_proxy_recipients($learnerid, $courseid);
        $sender = \core_user::get_user($senderid, '*', MUST_EXIST);
        $noreply = \core_user::get_noreply_user();
        $label = get_string('messagefrom', 'tool_guardianlink', fullname($sender));
        $sent = 0;
        foreach ($recipients as $recipient) {
            $ctx = \tool_guardianlink\local\template_service::context((int)$recipient->id, $learnerid, $courseid, true);
            $rendered = \tool_guardianlink\local\template_service::render($template, $ctx);
            $message = new \core\message\message();
            $message->component = 'tool_guardianlink';
            $message->name = 'proxy_message';
            $message->userfrom = $noreply;
            $message->replyto = $noreply->email;
            $message->replytoname = get_string('noreplyname');
            $message->userto = \core_user::get_user((int)$recipient->id, '*', MUST_EXIST);
            $message->subject = $rendered['subject'];
            $message->fullmessageformat = FORMAT_HTML;
            $message->fullmessage = $label . "\n\n" . html_to_text($rendered['body']);
            $message->fullmessagehtml = $label . '<br><br>' . $rendered['body'];
            $message->smallmessage = $rendered['subject'];
            $message->notification = 1;
            $message->contexturl = (new \moodle_url(
                '/admin/tool/guardianlink/child.php',
                ['id' => $learnerid, 'courseid' => $courseid]
            ))->out(false);
            $message->contexturlname = get_string('pluginname', 'tool_guardianlink');
            message_send($message);
            relationship_service::log_access(
                $senderid,
                $learnerid,
                'results_email_sent',
                $courseid,
                'template',
                (int)$template->id,
                ['guardianid' => (int)$recipient->id]
            );
            $sent++;
        }
        return ['sent' => $sent, 'recipients' => count($recipients)];
    }

    /**
     * Send a single already-rendered HTML notice to one authorised adult, recorded as a thread so
     * it appears in the messaging register and the adult can reply in-app. Used by the public API
     * ({@see \tool_guardianlink\api::notify_guardians()}) so integrating plugins can notify
     * guardians without ever seeing their address. Preserves the no-leak guarantee.
     *
     * @param int $adultid Recipient authorised adult.
     * @param int $learnerid
     * @param int $courseid
     * @param string $subject Already-rendered subject.
     * @param string $body Already-rendered HTML body.
     * @param int $senderid Acting user (audit + display name only).
     * @param string $action Audit action key recorded for this send.
     * @return bool
     */
    public static function send_one_off(
        int $adultid,
        int $learnerid,
        int $courseid,
        string $subject,
        string $body,
        int $senderid,
        string $action = 'api_notice_sent'
    ): bool {
        global $DB;
        $sender = \core_user::get_user($senderid, '*', MUST_EXIST);
        $recipient = \core_user::get_user($adultid, '*', IGNORE_MISSING);
        if (!$recipient) {
            return false;
        }
        $rel = relationship_service::get_active_relationship($adultid, $learnerid);
        $now = time();
        $thread = (object)[
            'childid' => $learnerid,
            'courseid' => $courseid,
            'teacherid' => $senderid,
            'guardianid' => $adultid,
            'relationshipid' => $rel ? (int)$rel->id : 0,
            'subject' => clean_param($subject, PARAM_TEXT),
            'lastmessage' => clean_param(html_to_text($body), PARAM_TEXT),
            'status' => 'open',
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $threadid = (int)$DB->insert_record('tool_guardianlink_msgthread', $thread);

        $noreply = \core_user::get_noreply_user();
        $label = get_string('messagefrom', 'tool_guardianlink', fullname($sender));
        $message = new \core\message\message();
        $message->component = 'tool_guardianlink';
        $message->name = 'proxy_message';
        $message->userfrom = $noreply;
        $message->replyto = $noreply->email;
        $message->replytoname = get_string('noreplyname');
        $message->userto = $recipient;
        $message->subject = clean_param($subject, PARAM_TEXT);
        $message->fullmessageformat = FORMAT_HTML;
        $message->fullmessage = $label . "\n\n" . html_to_text($body);
        $message->fullmessagehtml = $label . '<br><br>' . $body;
        $message->smallmessage = clean_param($subject, PARAM_TEXT);
        $message->notification = 1;
        $message->contexturl = (new \moodle_url('/admin/tool/guardianlink/thread.php', ['id' => $threadid]))->out(false);
        $message->contexturlname = clean_param($subject, PARAM_TEXT);
        message_send($message);
        relationship_service::log_access(
            $senderid,
            $learnerid,
            $action,
            $courseid,
            'msgthread',
            $threadid,
            ['guardianid' => $adultid]
        );
        return true;
    }

    /**
     * Create a minimal digest payload for an authorised adult/learner pair.
     *
     * @param int $adultid
     * @param int $learnerid
     * @param object|null $preference Digest preference controlling included sections.
     * @return string
     */
    public static function build_digest_text(int $adultid, int $learnerid, ?object $preference = null): string {
        global $CFG;
        require_once($CFG->libdir . '/enrollib.php');

        $learner = \core_user::get_user($learnerid, '*', MUST_EXIST);
        $lines = [];
        $lines[] = get_string('digestintro', 'tool_guardianlink', fullname($learner));
        $lines[] = '';

        $courses = enrol_get_users_courses($learnerid, true, 'id, shortname, fullname, visible, startdate, enddate');
        if ($courses) {
            $lines[] = get_string('digestcourses', 'tool_guardianlink');
            foreach ($courses as $course) {
                if (relationship_service::can_access_child($adultid, $learnerid, (int)$course->id, 'overview')) {
                    $cangrades = !empty($preference->includegrades)
                        && relationship_service::can_access_child($adultid, $learnerid, (int)$course->id, 'grades');
                    // Real progress data (read-only), honouring the grades scope.
                    $p = progress_service::course_progress((int)$course->id, $learnerid, $cangrades);
                    $summary = '- ' . format_string($course->fullname);
                    if ($p->completionpercent !== null) {
                        $summary .= ' — ' . get_string(
                            'digestprogress',
                            'tool_guardianlink',
                            (object)['percent' => $p->completionpercent, 'completed' => $p->completed, 'total' => $p->total]
                        );
                    }
                    if (!empty($preference->includeoverdue) && $p->overdue > 0) {
                        $summary .= ' — ' . get_string('digestoverdue', 'tool_guardianlink', $p->overdue);
                    }
                    if ($cangrades && $p->coursegrade !== null) {
                        $summary .= ' — ' . get_string('allowgrades', 'tool_guardianlink') . ': ' . $p->coursegrade;
                    }
                    $lines[] = $summary;
                }
            }
        } else {
            $lines[] = get_string('digestnocourses', 'tool_guardianlink');
        }

        if (
            !empty($preference->includehealth)
                && relationship_service::can_access_child($adultid, $learnerid, 0, 'healthsummary')
        ) {
            $health = relationship_service::get_health_records_for_adult($adultid, $learnerid);
            if ($health) {
                $lines[] = '';
                $lines[] = get_string('healthrecords', 'tool_guardianlink') . ':';
                foreach ($health as $record) {
                    $lines[] = '- ' . $record->title . ' (' . $record->severity . ')';
                }
            }
        }

        $lines[] = '';
        $lines[] = get_string('digestfooter', 'tool_guardianlink');
        relationship_service::log_access($adultid, $learnerid, 'digest_built');
        return implode("\n", $lines);
    }

    /**
     * Send a scheduled guardian digest.
     *
     * @param object $preference
     * @return bool
     */
    public static function send_guardian_digest(object $preference): bool {
        $adult = \core_user::get_user((int)$preference->guardianid, '*', MUST_EXIST);
        $learner = \core_user::get_user((int)$preference->childid, '*', MUST_EXIST);
        $body = self::build_digest_text((int)$preference->guardianid, (int)$preference->childid, $preference);
        $message = new \core\message\message();
        $message->component = 'tool_guardianlink';
        $message->name = 'guardian_digest';
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $adult;
        $message->subject = get_string('digestsubject', 'tool_guardianlink', fullname($learner));
        $message->fullmessage = $body;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = format_text($body, FORMAT_PLAIN);
        $message->smallmessage = get_string('digestsms', 'tool_guardianlink', fullname($learner));
        $message->notification = 1;
        $message->contexturl = (new \moodle_url(
            '/admin/tool/guardianlink/child.php',
            ['id' => (int)$preference->childid]
        ))->out(false);
        $message->contexturlname = fullname($learner);
        message_send($message);
        relationship_service::mark_digest_sent($preference);
        return true;
    }
}

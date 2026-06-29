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
 * Email/message templates with placeholders for GuardianLink.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guardianlink\local;

/**
 * Reusable templates with {placeholders}, rendered with a learner/adult/course context and sent at
 * appropriate times (manually by a teacher, or on a trigger such as a results notice or review-due).
 */
class template_service {
    /**
     * Supported placeholders => human description (for the editor help).
     *
     * @return array
     */
    public static function placeholders(): array {
        return [
            'sitename' => get_string('ph_sitename', 'tool_guardianlink'),
            'adultname' => get_string('ph_adultname', 'tool_guardianlink'),
            'learnerfirstname' => get_string('ph_learnerfirstname', 'tool_guardianlink'),
            'learnerfullname' => get_string('ph_learnerfullname', 'tool_guardianlink'),
            'coursename' => get_string('ph_coursename', 'tool_guardianlink'),
            'grade' => get_string('ph_grade', 'tool_guardianlink'),
            'classaverage' => get_string('ph_classaverage', 'tool_guardianlink'),
            'relativeperformance' => get_string('ph_relativeperformance', 'tool_guardianlink'),
            'activitygrades' => get_string('ph_activitygrades', 'tool_guardianlink'),
            'testname' => get_string('ph_testname', 'tool_guardianlink'),
            'testresult' => get_string('ph_testresult', 'tool_guardianlink'),
            'completion' => get_string('ph_completion', 'tool_guardianlink'),
            'overdue' => get_string('ph_overdue', 'tool_guardianlink'),
            'date' => get_string('ph_date', 'tool_guardianlink'),
        ];
    }

    /**
     * Per-course activity placeholder catalogue (token => description), one pair per gradable
     * activity in the course. Shown to teachers so they can insert {grade_<id>} for any activity.
     *
     * @param int $courseid
     * @return array
     */
    public static function course_activity_placeholders(int $courseid): array {
        $out = [];
        foreach (progress_service::gradeitem_options($courseid) as $iid => $name) {
            $out['grade_' . $iid] = get_string('ph_activitygrade', 'tool_guardianlink', $name);
            $out['activity_' . $iid] = get_string('ph_activityname', 'tool_guardianlink', $name);
        }
        return $out;
    }

    /**
     * Trigger keys a template can be bound to.
     *
     * @return array
     */
    public static function triggers(): array {
        return [
            'manual' => get_string('trigger_manual', 'tool_guardianlink'),
            'results' => get_string('trigger_results', 'tool_guardianlink'),
            'relationship_created' => get_string('trigger_relationship_created', 'tool_guardianlink'),
            'review_due' => get_string('trigger_review_due', 'tool_guardianlink'),
        ];
    }

    /**
     * Fetch templates matching the given trigger and scope filters.
     *
     * @param string $triggerkey Optional filter.
     * @param bool $enabledonly
     * @param int|null $courseid null = any scope; 0 = global/admin only; >0 = that course only.
     * @return array
     */
    public static function get_templates(string $triggerkey = '', bool $enabledonly = false, ?int $courseid = null): array {
        global $DB;
        $conditions = [];
        if ($triggerkey !== '') {
            $conditions['triggerkey'] = $triggerkey;
        }
        if ($enabledonly) {
            $conditions['enabled'] = 1;
        }
        if ($courseid !== null) {
            $conditions['courseid'] = $courseid;
        }
        return $DB->get_records('tool_guardianlink_template', $conditions ?: null, 'name ASC');
    }

    /**
     * Templates available when acting inside a course: the course's own templates, optionally plus
     * the global/admin ones. Course templates take precedence on a shortname clash.
     *
     * @param int $courseid
     * @param bool $includeglobal Include global (courseid = 0) templates too.
     * @param string $triggerkey Optional trigger filter.
     * @param bool $enabledonly
     * @return array keyed by id
     */
    public static function get_course_templates(
        int $courseid,
        bool $includeglobal = true,
        string $triggerkey = '',
        bool $enabledonly = false
    ): array {
        $own = self::get_templates($triggerkey, $enabledonly, $courseid);
        if (!$includeglobal) {
            return $own;
        }
        $shortnames = [];
        foreach ($own as $t) {
            $shortnames[$t->shortname] = true;
        }
        $out = $own;
        foreach (self::get_templates($triggerkey, $enabledonly, 0) as $id => $t) {
            if (!isset($shortnames[$t->shortname])) {
                $out[$id] = $t;
            }
        }
        return $out;
    }

    /**
     * Fetch a single template by shortname, with optional course-scoped fallback.
     *
     * @param string $shortname
     * @param int $courseid Course-scoped lookup; falls back to the global template if none in-course.
     * @return object|null
     */
    public static function get_template(string $shortname, int $courseid = 0): ?object {
        global $DB;
        if ($courseid > 0) {
            $rec = $DB->get_record('tool_guardianlink_template', ['shortname' => $shortname, 'courseid' => $courseid]);
            if ($rec) {
                return $rec;
            }
        }
        $rec = $DB->get_record('tool_guardianlink_template', ['shortname' => $shortname, 'courseid' => 0]);
        return $rec ?: null;
    }

    /**
     * Copy templates into one or more target courses (the templates, NOT recipients). A target
     * course that already has a template with the same shortname is overwritten, so this doubles as
     * "push my course's templates to other courses".
     *
     * @param int[] $templateids Template ids to copy.
     * @param int[] $tocourseids Destination course ids.
     * @param int $userid Acting teacher (becomes owner of new copies).
     * @return int Number of templates written (created or overwritten).
     */
    public static function copy_templates(array $templateids, array $tocourseids, int $userid): int {
        global $DB;
        $written = 0;
        $now = time();
        foreach ($templateids as $tid) {
            $src = $DB->get_record('tool_guardianlink_template', ['id' => (int)$tid]);
            if (!$src) {
                continue;
            }
            foreach ($tocourseids as $cid) {
                $cid = (int)$cid;
                if ($cid <= 0 || $cid === (int)$src->courseid) {
                    continue;
                }
                $existing = $DB->get_record('tool_guardianlink_template', ['shortname' => $src->shortname, 'courseid' => $cid]);
                $copy = (object)[
                    'shortname' => $src->shortname,
                    'name' => $src->name,
                    'triggerkey' => $src->triggerkey,
                    'subject' => $src->subject,
                    'body' => $src->body,
                    'bodyformat' => $src->bodyformat,
                    'enabled' => $src->enabled,
                    'courseid' => $cid,
                    'ownerid' => $userid,
                    'timemodified' => $now,
                ];
                if ($existing) {
                    $copy->id = $existing->id;
                    $DB->update_record('tool_guardianlink_template', $copy);
                } else {
                    $copy->createdby = $userid;
                    $copy->timecreated = $now;
                    $DB->insert_record('tool_guardianlink_template', $copy);
                }
                $written++;
            }
        }
        return $written;
    }

    /**
     * Create or update a template record and return its id.
     *
     * @param object|array $data
     * @param int $userid
     * @return int
     */
    public static function save_template(object|array $data, int $userid): int {
        global $DB;
        $get = fn($k, $d = '') => is_array($data) ? ($data[$k] ?? $d) : ($data->$k ?? $d);
        $now = time();
        $body = $get('body');
        if (is_array($body)) {
            $bodytext = $body['text'] ?? '';
            $bodyformat = (int)($body['format'] ?? FORMAT_HTML);
        } else {
            $bodytext = (string)$body;
            $bodyformat = (int)$get('bodyformat', FORMAT_HTML);
        }
        $courseid = (int)$get('courseid', 0);
        $record = (object)[
            'shortname' => clean_param((string)$get('shortname'), PARAM_ALPHANUMEXT),
            'name' => clean_param((string)$get('name'), PARAM_TEXT),
            'triggerkey' => clean_param((string)$get('triggerkey', 'manual'), PARAM_ALPHANUMEXT),
            'subject' => clean_param((string)$get('subject'), PARAM_TEXT),
            'body' => clean_param($bodytext, PARAM_CLEANHTML),
            'bodyformat' => $bodyformat,
            'enabled' => empty($get('enabled', 1)) ? 0 : 1,
            'courseid' => $courseid,
            'timemodified' => $now,
        ];
        $id = (int)$get('id', 0);
        if ($id > 0 && $DB->record_exists('tool_guardianlink_template', ['id' => $id])) {
            $record->id = $id;
            $DB->update_record('tool_guardianlink_template', $record);
        } else {
            $record->ownerid = $courseid > 0 ? $userid : 0;
            $record->createdby = $userid;
            $record->timecreated = $now;
            $id = (int)$DB->insert_record('tool_guardianlink_template', $record);
        }
        return $id;
    }

    /**
     * Delete a template by its id.
     *
     * @param int $id
     */
    public static function delete_template(int $id): void {
        global $DB;
        $DB->delete_records('tool_guardianlink_template', ['id' => $id]);
    }

    /**
     * Build the placeholder context for an adult/learner/course.
     *
     * @param int $adultid
     * @param int $learnerid
     * @param int $courseid
     * @param bool $includegrade
     * @param array $extra Extra/override substitutions (e.g. ['gradeitemid' => 42]).
     * @return array key => value
     */
    public static function context(
        int $adultid,
        int $learnerid,
        int $courseid = 0,
        bool $includegrade = false,
        array $extra = []
    ): array {
        global $SITE;
        $adult = \core_user::get_user($adultid, '*', IGNORE_MISSING);
        $learner = \core_user::get_user($learnerid, '*', IGNORE_MISSING);
        $ctx = [
            'sitename' => format_string($SITE->fullname),
            'adultname' => $adult ? fullname($adult) : '',
            'learnerfirstname' => $learner ? $learner->firstname : '',
            'learnerfullname' => $learner ? fullname($learner) : '',
            'coursename' => '',
            'grade' => '',
            'classaverage' => '',
            'relativeperformance' => '',
            'activitygrades' => '',
            'testname' => '',
            'testresult' => '',
            'completion' => '',
            'overdue' => '',
            'date' => userdate(time(), get_string('strftimedate', 'langconfig')),
        ];
        if ($courseid > 0) {
            $course = get_course($courseid);
            $ctx['coursename'] = format_string($course->fullname);
            $p = progress_service::course_progress($courseid, $learnerid, $includegrade);
            $ctx['grade'] = $p->coursegrade !== null ? $p->coursegrade : '-';
            $ctx['completion'] = $p->completionpercent !== null ? ($p->completionpercent . '%') : '-';
            $ctx['overdue'] = (string)$p->overdue;
            if ($includegrade) {
                $learnerraw = progress_service::course_grade_raw($courseid, $learnerid);
                $classavg = progress_service::class_average($courseid);
                $ctx['classaverage'] = progress_service::format_course_grade($courseid, $classavg);
                $ctx['relativeperformance'] = self::relative_performance_label($learnerraw, $classavg);
                $lines = [];
                foreach (progress_service::activity_grades($courseid, $learnerid) as $name => $g) {
                    $lines[] = s($name) . ': ' . $g;
                }
                $ctx['activitygrades'] = $lines ? implode('<br>', $lines) : '-';
                // Unique per-activity tokens: {grade_<itemid>} and {activity_<itemid>}.
                foreach (progress_service::activity_grade_tokens($courseid, $learnerid) as $k => $v) {
                    $ctx[$k] = $v;
                }
                // A specific test/activity, when the caller nominated one (gradeitemid in $extra).
                if (!empty($extra['gradeitemid'])) {
                    $one = progress_service::activity_grade_by_item((int)$extra['gradeitemid'], $learnerid, $courseid);
                    if ($one !== null) {
                        $ctx['testname'] = $one['name'];
                        $ctx['testresult'] = $one['grade'];
                    } else {
                        $ctx['testresult'] = '-';
                    }
                }
            } else {
                $ctx['classaverage'] = '-';
                $ctx['relativeperformance'] = '-';
                $ctx['activitygrades'] = '-';
            }
        }
        // Literal overrides (already-resolved values) win over anything computed above.
        foreach ($extra as $k => $v) {
            if ($k !== 'gradeitemid' && array_key_exists($k, $ctx)) {
                $ctx[$k] = (string)$v;
            }
        }
        return $ctx;
    }

    /**
     * Human label comparing a learner's raw grade to the class average.
     *
     * @param float|null $learnerraw
     * @param float|null $classavg
     * @return string
     */
    protected static function relative_performance_label(?float $learnerraw, ?float $classavg): string {
        if ($learnerraw === null || $classavg === null || $classavg == 0.0) {
            return '-';
        }
        $ratio = $learnerraw / $classavg;
        if ($ratio >= 1.05) {
            return get_string('perf_above', 'tool_guardianlink');
        }
        if ($ratio <= 0.95) {
            return get_string('perf_below', 'tool_guardianlink');
        }
        return get_string('perf_at', 'tool_guardianlink');
    }

    /**
     * Render a template's subject + body with a context, substituting {placeholders}.
     *
     * @param object $template
     * @param array $ctx
     * @return array ['subject' => string, 'body' => string, 'format' => int]
     */
    public static function render(object $template, array $ctx): array {
        $search = [];
        $replace = [];
        foreach ($ctx as $k => $v) {
            $search[] = '{' . $k . '}';
            $replace[] = (string)$v;
        }
        return [
            'subject' => str_replace($search, $replace, (string)$template->subject),
            'body' => str_replace($search, $replace, (string)$template->body),
            'format' => (int)$template->bodyformat,
        ];
    }
}

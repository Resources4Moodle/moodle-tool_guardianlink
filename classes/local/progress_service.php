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
 * Real learner progress (completion, grade, overdue) for GuardianLink dashboards, digests and audiences.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guardianlink\local;

/**
 * Pulls real progress data from core completion and gradebook APIs.
 *
 * Read-only: it never alters learner data. Callers are responsible for confirming the adult's
 * scope (e.g. grades only when the relationship's course scope allows grades).
 */
class progress_service {
    /**
     * Progress summary for one learner in one course.
     *
     * @param int $courseid
     * @param int $learnerid
     * @param bool $includegrade Include the course grade (only when the caller's scope permits grades).
     * @return object {completionpercent:?int, completed:int, total:int, overdue:int, coursegrade:?string, lastaccess:int}
     */
    public static function course_progress(int $courseid, int $learnerid, bool $includegrade = false): \stdClass {
        global $CFG, $DB;
        require_once($CFG->libdir . '/completionlib.php');
        require_once($CFG->libdir . '/gradelib.php');

        $result = (object)[
            'completionpercent' => null,
            'completed' => 0,
            'total' => 0,
            'overdue' => 0,
            'coursegrade' => null,
            'lastaccess' => 0,
        ];

        $course = get_course($courseid);
        $completion = new \completion_info($course);
        if ($completion->is_enabled()) {
            $now = time();
            $activities = $completion->get_activities();
            foreach ($activities as $cm) {
                $result->total++;
                $data = $completion->get_data($cm, false, $learnerid);
                if (in_array((int)$data->completionstate, [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS], true)) {
                    $result->completed++;
                } else if (!empty($cm->completionexpected) && $cm->completionexpected < $now) {
                    $result->overdue++;
                }
            }
            if ($result->total > 0) {
                $result->completionpercent = (int)round(100 * $result->completed / $result->total);
            }
        }

        if ($includegrade) {
            $courseitem = \grade_item::fetch_course_item($courseid);
            if ($courseitem) {
                $gradegrade = new \grade_grade(['itemid' => $courseitem->id, 'userid' => $learnerid], true);
                if ($gradegrade && $gradegrade->finalgrade !== null) {
                    $result->coursegrade = grade_format_gradevalue($gradegrade->finalgrade, $courseitem);
                }
            }
        }

        $result->lastaccess = (int)$DB->get_field(
            'user_lastaccess',
            'timeaccess',
            ['userid' => $learnerid, 'courseid' => $courseid]
        );

        return $result;
    }

    /**
     * Overdue activity count for a learner in a course (completion expected, in the past, not complete).
     *
     * @param int $courseid
     * @param int $learnerid
     * @return int
     */
    public static function overdue_count(int $courseid, int $learnerid): int {
        return (int)self::course_progress($courseid, $learnerid)->overdue;
    }

    /**
     * Whether the learner has any overdue work in a course.
     *
     * @param int $courseid
     * @param int $learnerid
     * @return bool
     */
    public static function is_overdue(int $courseid, int $learnerid): bool {
        return self::overdue_count($courseid, $learnerid) > 0;
    }

    /**
     * Raw final course grade for a learner (null if none).
     *
     * @param int $courseid
     * @param int $learnerid
     * @return float|null
     */
    public static function course_grade_raw(int $courseid, int $learnerid): ?float {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        $item = \grade_item::fetch_course_item($courseid);
        if (!$item) {
            return null;
        }
        $grade = new \grade_grade(['itemid' => $item->id, 'userid' => $learnerid], true);
        return ($grade && $grade->finalgrade !== null) ? (float)$grade->finalgrade : null;
    }

    /**
     * Class average final course grade (null if no grades).
     *
     * @param int $courseid
     * @return float|null
     */
    public static function class_average(int $courseid): ?float {
        global $CFG, $DB;
        require_once($CFG->libdir . '/gradelib.php');
        $item = \grade_item::fetch_course_item($courseid);
        if (!$item) {
            return null;
        }
        $avg = $DB->get_field_sql(
            "SELECT AVG(finalgrade) FROM {grade_grades} WHERE itemid = :iid AND finalgrade IS NOT NULL",
            ['iid' => $item->id]
        );
        return ($avg !== null && $avg !== false) ? (float)$avg : null;
    }

    /**
     * Format a raw grade value for a course's grade scale.
     *
     * @param int $courseid
     * @param float|null $value
     * @return string
     */
    public static function format_course_grade(int $courseid, ?float $value): string {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        if ($value === null) {
            return '-';
        }
        $item = \grade_item::fetch_course_item($courseid);
        return $item ? grade_format_gradevalue($value, $item) : (string)round($value, 2);
    }

    /**
     * Per-activity grades for a learner in a course (only items the learner has a grade for).
     *
     * @param int $courseid
     * @param int $learnerid
     * @return array [itemname => formatted grade]
     */
    public static function activity_grades(int $courseid, int $learnerid): array {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        $out = [];
        $items = \grade_item::fetch_all(['courseid' => $courseid, 'itemtype' => 'mod']) ?: [];
        foreach ($items as $item) {
            $grade = new \grade_grade(['itemid' => $item->id, 'userid' => $learnerid], true);
            if ($grade && $grade->finalgrade !== null) {
                $out[$item->get_name()] = grade_format_gradevalue($grade->finalgrade, $item);
            }
        }
        return $out;
    }

    /**
     * One specific activity/test grade for a learner, by grade item id.
     *
     * @param int $itemid grade_items.id
     * @param int $learnerid
     * @return array|null ['name' => string, 'grade' => string] or null if no grade
     */
    public static function activity_grade_by_item(int $itemid, int $learnerid): ?array {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        $item = \grade_item::fetch(['id' => $itemid]);
        if (!$item) {
            return null;
        }
        $grade = new \grade_grade(['itemid' => $item->id, 'userid' => $learnerid], true);
        if (!$grade || $grade->finalgrade === null) {
            return ['name' => $item->get_name(), 'grade' => '-'];
        }
        return ['name' => $item->get_name(), 'grade' => grade_format_gradevalue($grade->finalgrade, $item)];
    }

    /**
     * Per-activity grade substitution tokens for a learner: for every gradable activity in the
     * course this returns both 'grade_<itemid>' (the learner's formatted grade, or '-' if none) and
     * 'activity_<itemid>' (the activity name). These back the unique per-activity placeholders a
     * teacher can drop into a message (e.g. {grade_42}).
     *
     * @param int $courseid
     * @param int $learnerid
     * @return array [token => value]
     */
    public static function activity_grade_tokens(int $courseid, int $learnerid): array {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        $out = [];
        $items = \grade_item::fetch_all(['courseid' => $courseid, 'itemtype' => 'mod']) ?: [];
        foreach ($items as $item) {
            $grade = new \grade_grade(['itemid' => $item->id, 'userid' => $learnerid], true);
            $out['grade_' . $item->id] = ($grade && $grade->finalgrade !== null)
                ? grade_format_gradevalue($grade->finalgrade, $item) : '-';
            $out['activity_' . $item->id] = $item->get_name();
        }
        return $out;
    }

    /**
     * Gradable activity/test items in a course, for a "send a specific test result" picker.
     *
     * @param int $courseid
     * @return array [itemid => itemname]
     */
    public static function gradeitem_options(int $courseid): array {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        $out = [];
        $items = \grade_item::fetch_all(['courseid' => $courseid, 'itemtype' => 'mod']) ?: [];
        foreach ($items as $item) {
            $out[(int)$item->id] = $item->get_name();
        }
        return $out;
    }

    /**
     * Enrolled learner ids in a course who currently have overdue work (for at-risk audiences).
     *
     * @param int $courseid
     * @return int[]
     */
    public static function overdue_learner_ids(int $courseid): array {
        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$context) {
            return [];
        }
        $ids = [];
        foreach (array_keys(get_enrolled_users($context, '', 0, 'u.id', null, 0, 0, true)) as $uid) {
            if (self::is_overdue($courseid, (int)$uid)) {
                $ids[] = (int)$uid;
            }
        }
        return $ids;
    }
}

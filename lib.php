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
 * Library callbacks for GuardianLink.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add GuardianLink links to the global navigation.
 *
 * @param global_navigation $navigation
 */
function tool_guardianlink_extend_navigation(global_navigation $navigation): void {
    if (!isloggedin() || isguestuser()) {
        return;
    }
    $node = $navigation->add(
        get_string('pluginname', 'tool_guardianlink'),
        new moodle_url('/admin/tool/guardianlink/index.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'tool_guardianlink'
    );
    $node->showinflatnavigation = true;
}

/**
 * Add GuardianLink links to user navigation.
 *
 * @param navigation_node $navigation
 * @param stdClass $user
 * @param context_user $usercontext
 * @param stdClass $course
 * @param context_course $coursecontext
 */
function tool_guardianlink_extend_navigation_user(
    navigation_node $navigation,
    stdClass $user,
    context_user $usercontext,
    stdClass $course,
    context_course $coursecontext
): void {
    global $USER;
    if ((int)$USER->id === (int)$user->id && isloggedin() && !isguestuser()) {
        $navigation->add(get_string('familydashboard', 'tool_guardianlink'), new moodle_url('/admin/tool/guardianlink/index.php'));
    }
}

/**
 * Enforce the integrity rules of a governed assisted (logged-in-as) session.
 *
 * Runs inside core require_login(). For a GuardianLink assisted session it:
 *  - blocks entry to ANY assessed activity (no external influence during assessments), and
 *  - ends the session once it exceeds the configured maximum duration.
 * It deliberately does nothing for ordinary sessions or a manager's normal "log in as".
 *
 * @param mixed $courseorid
 * @param mixed $autologinguest
 * @param mixed $cm
 * @param mixed $setwantsurltome
 * @param mixed $preventredirect
 */
function tool_guardianlink_after_require_login(
    $courseorid = null,
    $autologinguest = null,
    $cm = null,
    $setwantsurltome = null,
    $preventredirect = null
) {
    global $USER, $SESSION;

    // Soft supervision reminder (school opt-in). When the school requires parental supervision by
    // default and this learner has not been granted independent access to the course, show a
    // non-blocking notice. This never blocks the learner; hard enforcement is left to the institution.
    if (
        \tool_guardianlink\local\supervision_service::supervision_required()
            && isloggedin() && !isguestuser() && !\core\session\manager::is_loggedinas()
    ) {
        $cid = is_object($courseorid) ? (int)($courseorid->id ?? 0) : (int)$courseorid;
        if (
            $cid > SITEID
                && \tool_guardianlink\local\supervision_service::child_has_guardian((int)$USER->id)
                && !\tool_guardianlink\local\supervision_service::is_independent_allowed((int)$USER->id, $cid)
        ) {
            \core\notification::add(
                get_string('supervisionnotice', 'tool_guardianlink'),
                \core\output\notification::NOTIFY_WARNING
            );
        }
    }

    if (!\core\session\manager::is_loggedinas() || empty($SESSION->tool_guardianlink_assisted)) {
        return;
    }
    $assisted = $SESSION->tool_guardianlink_assisted;

    // Heartbeat: keep this live session's course grant fresh so the cleanup sweep won't revoke it.
    \tool_guardianlink\local\setup::touch_course_view((int)$assisted->realuserid, (int)$assisted->courseid);

    // Time cap: end the assisted session (return to the real adult) once it overruns.
    $max = \tool_guardianlink\local\relationship_service::assisted_max_seconds();
    if ($max > 0 && (time() - (int)$assisted->started) > $max) {
        // Revoke the course-context grant before ending the session so no standing access remains.
        \tool_guardianlink\local\setup::set_course_view((int)$assisted->realuserid, (int)$assisted->courseid, false);
        unset($SESSION->tool_guardianlink_assisted);
        redirect(
            new moodle_url('/login/logout.php', ['sesskey' => sesskey()]),
            get_string('assistedexpired', 'tool_guardianlink'),
            null,
            \core\output\notification::NOTIFY_INFO
        );
    }

    // Block all assessed activities — the anti-cheating / no-external-influence rule.
    if ($cm && !empty($cm->modname) && plugin_supports('mod', $cm->modname, FEATURE_GRADE_HAS_GRADE)) {
        redirect(
            new moodle_url('/course/view.php', ['id' => (int)$assisted->courseid]),
            get_string('assistedassessmentblocked', 'tool_guardianlink'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
}

/**
 * Add the teacher GuardianLink area to a course's secondary navigation.
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context_course $context
 */
function tool_guardianlink_extend_navigation_course(navigation_node $navigation, stdClass $course, context_course $context): void {
    if (
        has_capability('tool/guardianlink:sendproxymessages', $context)
            || has_capability('moodle/course:update', $context)
    ) {
        $navigation->add(
            get_string('teachercoursearea', 'tool_guardianlink'),
            new moodle_url('/admin/tool/guardianlink/course.php', ['courseid' => $course->id]),
            navigation_node::TYPE_SETTING,
            null,
            'tool_guardianlink_course'
        );
    }
}

/**
 * Serve GuardianLink custody-chain files (proof attachments and embedded note images).
 *
 * Sensitive material: only admin-level managers may retrieve these files.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool false on failure
 */
function tool_guardianlink_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_SYSTEM) {
        return false;
    }
    if (!in_array($filearea, ['proof', 'proofnote'], true)) {
        return false;
    }
    require_login();
    if (!has_capability('tool/guardianlink:manage', \context_system::instance())) {
        return false;
    }
    $itemid = (int)array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'tool_guardianlink', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, null, 0, $forcedownload, $options);
}

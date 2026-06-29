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
 * Teacher-of-course GuardianLink area: a faculty dashboard for parent/guardian interaction.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/admin/tool/guardianlink/classes/form/course_policy_form.php');

use tool_guardianlink\local\relationship_service;
use tool_guardianlink\local\progress_service;

$courseid = required_param('courseid', PARAM_INT);
$filter = optional_param('filter', 'all', PARAM_ALPHA);     // All | atrisk | withadults.
$q = optional_param('q', '', PARAM_TEXT);                   // Name search.
$course = get_course($courseid);
require_login($course);
$context = context_course::instance($courseid);

// Teacher capability: send mediated messages to authorised adults in this course.
$canproxy = has_capability('tool/guardianlink:sendproxymessages', $context);
// Editing teachers may set the per-course policy (duration cap, delegation toggles).
$canmanage = has_capability('moodle/course:update', $context);
// Family metadata (names of a learner's authorised adults) is teacher-prevented by default.
$canseefamily = has_capability('tool/guardianlink:viewfamilymetadata', $context);
// Gradebook visibility is a SEPARATE permission: a user able to message adults is not necessarily
// allowed to see learners' grades, so gate grade columns on Moodle's own grade capability.
$canviewgrades = has_capability('moodle/grade:viewall', $context);
// Audit-level visibility: who may see ALL course threads and adult/relationship identities, beyond
// the minimum a teacher needs to do their own job.
$canviewaudit = has_capability('tool/guardianlink:viewaudit', $context);
$canseeadultnames = $canseefamily || $canviewaudit || $canmanage;

if (!$canproxy && !$canmanage) {
    throw new moodle_exception('accessdenied', 'tool_guardianlink');
}

$baseurl = new moodle_url('/admin/tool/guardianlink/course.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_url($baseurl);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('teachercoursearea', 'tool_guardianlink'));
$PAGE->set_heading(format_string($course->fullname));

// Handle the per-course policy form (editing teachers only).
$policyform = null;
if ($canmanage) {
    $policyform = new \tool_guardianlink\form\course_policy_form(
        null,
        null,
        'post',
        '',
        null,
        true,
        ['courseid' => $courseid]
    );
    $existing = relationship_service::get_course_config($courseid);
    $policyform->set_data([
        'courseid' => $courseid,
        'maxgrantdays' => $existing ? (int)$existing->maxgrantdays : 0,
        'defaultgrantdays' => $existing ? (int)$existing->defaultgrantdays : 0,
        'allowparentpropose' => $existing ? (int)$existing->allowparentpropose : 1,
        'allowteacherproxy' => $existing ? (int)$existing->allowteacherproxy : 1,
        'allowassistedaccess' => $existing ? (int)$existing->allowassistedaccess : 0,
        'allowindependentaccess' => $existing ? (int)($existing->allowindependentaccess ?? 0) : 0,
    ]);
    if ($data = $policyform->get_data()) {
        require_sesskey();
        relationship_service::save_course_config($courseid, $data, (int)$USER->id);
        redirect(
            $baseurl,
            get_string('coursepolicysaved', 'tool_guardianlink'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

echo $OUTPUT->header();
echo \tool_guardianlink\local\ui::help_link('coursedash');
echo $OUTPUT->render_from_template('tool_guardianlink/dashboard', [
    'heading' => get_string('teachercoursearea', 'tool_guardianlink'),
    'description' => get_string('teachercourseintro', 'tool_guardianlink'),
]);

// Effective duration cap for this course (cascade result), shown for transparency.
$effmax = relationship_service::effective_max_grant_seconds($courseid);
echo html_writer::tag('p', get_string(
    'effectivemaxnote',
    'tool_guardianlink',
    $effmax > 0 ? (int)round($effmax / DAYSECS) : get_string('nolimit', 'tool_guardianlink')
));

// Build the per-learner picture once (progress + reachable adults), then filter/search/sort in PHP.
$enrolled = get_enrolled_users(
    $context,
    '',
    0,
    'u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename',
    null,
    0,
    0,
    true
);
$rows = [];
$totaladults = 0;
$atriskcount = 0;
$withadultscount = 0;
foreach ($enrolled as $learner) {
    $recipients = $canproxy ? relationship_service::get_proxy_recipients((int)$learner->id, $courseid) : [];
    $nadults = count($recipients);
    $p = progress_service::course_progress($courseid, (int)$learner->id, $canviewgrades);
    $isatrisk = $p->overdue > 0;
    if ($nadults > 0) {
        $withadultscount++;
        $totaladults += $nadults;
    }
    if ($isatrisk) {
        $atriskcount++;
    }
    $rows[] = (object)[
        'learner' => $learner,
        'nadults' => $nadults,
        'progress' => $p,
        'atrisk' => $isatrisk,
    ];
}

// Summary stat cards.
$stats = [
    get_string('stat_learners', 'tool_guardianlink') => count($rows),
    get_string('stat_withadults', 'tool_guardianlink') => $withadultscount,
    get_string('stat_atrisk', 'tool_guardianlink') => $atriskcount,
];
if ($canproxy) {
    $openthreads = $DB->count_records('tool_guardianlink_msgthread', ['courseid' => $courseid, 'status' => 'open']);
    $stats[get_string('stat_openthreads', 'tool_guardianlink')] = $openthreads;
}
echo html_writer::start_div('d-flex flex-wrap mb-3');
foreach ($stats as $label => $value) {
    echo html_writer::div(
        html_writer::tag('div', $value, ['class' => 'h3 mb-0']) . html_writer::tag('div', $label, ['class' => 'small text-muted']),
        'card card-body mr-2 mb-2 text-center',
        ['style' => 'min-width:8rem;']
    );
}
echo html_writer::end_div();

// Quick actions for teachers.
$canregistry = has_capability('tool/guardianlink:managecourseregistry', $context);
if ($canproxy || $canregistry) {
    $actions = [];
    if ($canproxy && $withadultscount > 0 && relationship_service::course_allows_teacher_proxy($courseid)) {
        $actions[] = html_writer::link(
            new moodle_url('/admin/tool/guardianlink/coursebulk.php', ['courseid' => $courseid]),
            get_string('bulkmessageguardians', 'tool_guardianlink'),
            ['class' => 'btn btn-primary mr-2']
        );
    }
    if ($canproxy) {
        $actions[] = html_writer::link(
            new moodle_url('/admin/tool/guardianlink/teachertemplates.php', ['courseid' => $courseid]),
            get_string('managecoursetemplates', 'tool_guardianlink'),
            ['class' => 'btn btn-secondary mr-2']
        );
    }
    if ($canregistry) {
        $actions[] = html_writer::link(
            new moodle_url('/admin/tool/guardianlink/registry.php', ['courseid' => $courseid]),
            get_string('courseregistry', 'tool_guardianlink'),
            ['class' => 'btn btn-secondary']
        );
    }
    if ($actions) {
        echo html_writer::div(implode('', $actions), 'mb-3');
    }
}

// Filter/search controls.
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $baseurl->out(false), 'class' => 'form-inline mb-3']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'courseid', 'value' => $courseid]);
$filteropts = [
    'all' => get_string('filter_all', 'tool_guardianlink'),
    'atrisk' => get_string('filter_atrisk', 'tool_guardianlink'),
    'withadults' => get_string('filter_withadults', 'tool_guardianlink'),
];
echo html_writer::label(get_string('show', 'tool_guardianlink'), 'filter', false, ['class' => 'mr-1']);
echo html_writer::select($filteropts, 'filter', $filter, false, ['class' => 'mr-2']);
echo html_writer::label(get_string('searchname', 'tool_guardianlink'), 'q', false, ['class' => 'mr-1']);
echo html_writer::empty_tag('input', ['type' => 'text', 'name' => 'q', 'value' => $q, 'class' => 'form-control mr-2']);
echo html_writer::empty_tag('input', ['type' => 'submit', 'class' => 'btn btn-secondary',
    'value' => get_string('applyfilters', 'tool_guardianlink')]);
echo html_writer::end_tag('form');

// Apply filter + search.
$needle = trim(core_text::strtolower($q));
$visible = array_filter($rows, function ($r) use ($filter, $needle, $canseefamily) {
    if ($filter === 'atrisk' && !$r->atrisk) {
        return false;
    }
    if ($filter === 'withadults' && $r->nadults === 0) {
        return false;
    }
    // Hide learners with no reachable adults unless the teacher may see family metadata.
    if ($r->nadults === 0 && !$canseefamily && $filter !== 'all') {
        return false;
    }
    if ($needle !== '' && strpos(core_text::strtolower(fullname($r->learner)), $needle) === false) {
        return false;
    }
    return true;
});

$table = new html_table();
$head = [get_string('selectlearner', 'tool_guardianlink'), get_string('completion', 'completion'),
    get_string('coursegrade', 'tool_guardianlink'), get_string('stat_atrisk', 'tool_guardianlink'),
    get_string('authorisedadults', 'tool_guardianlink')];
if ($canproxy) {
    $head[] = get_string('actions');
}
$table->head = $head;
$table->attributes['class'] = 'generaltable';
foreach ($visible as $r) {
    $p = $r->progress;
    $row = [
        fullname($r->learner),
        $p->completionpercent !== null ? ($p->completionpercent . '%') : '-',
        $p->coursegrade !== null ? $p->coursegrade : '-',
        $r->atrisk ? html_writer::tag('span', $p->overdue, ['class' => 'badge badge-warning']) : '0',
        $r->nadults . ' ' . get_string('adultsreachable', 'tool_guardianlink'),
    ];
    if ($canproxy) {
        if ($r->nadults > 0) {
            $row[] = html_writer::link(new moodle_url(
                '/admin/tool/guardianlink/teachers.php',
                ['childid' => $r->learner->id, 'courseid' => $courseid]
            ), get_string('sendproxymessage', 'tool_guardianlink'))
                . ' | ' . html_writer::link(new moodle_url(
                    '/admin/tool/guardianlink/sendresults.php',
                    ['childid' => $r->learner->id, 'courseid' => $courseid]
                ), get_string('sendresults', 'tool_guardianlink'));
        } else {
            $row[] = '-';
        }
    }
    $table->data[] = $row;
}
echo $OUTPUT->heading(get_string('learnerswithadults', 'tool_guardianlink') . ' (' . count($visible) . ')', 3);
if (!empty($table->data)) {
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(get_string('noadultsincourse', 'tool_guardianlink'), 'info');
}

// Open teacher<->adult message threads for this course (metadata only) with reply links.
if ($canproxy) {
    // Ordinary teachers see only their OWN threads; thread subjects/status created by other teachers
    // require the audit capability (avoids leaking that a relationship/conversation exists).
    $threadconds = ['courseid' => $courseid];
    if (!$canviewaudit) {
        $threadconds['teacherid'] = (int)$USER->id;
    }
    $threads = $DB->get_records('tool_guardianlink_msgthread', $threadconds, 'timemodified DESC', '*', 0, 50);
    if ($threads) {
        echo $OUTPUT->heading(get_string('coursemessagethreads', 'tool_guardianlink'), 3);
        $tt = new html_table();
        $tt->attributes['class'] = 'generaltable';
        $tt->head = [get_string('subject', 'tool_guardianlink'), get_string('status', 'tool_guardianlink'),
            get_string('lastupdated', 'tool_guardianlink'), ''];
        foreach ($threads as $thread) {
            $threadurl = new moodle_url('/admin/tool/guardianlink/thread.php', ['id' => $thread->id]);
            $tt->data[] = [
                html_writer::link($threadurl, s($thread->subject)),
                $thread->status === 'open'
                    ? html_writer::tag('span', s($thread->status), ['class' => 'badge badge-success'])
                    : s($thread->status),
                userdate($thread->timemodified),
                html_writer::link($threadurl, get_string('viewthread', 'tool_guardianlink')),
            ];
        }
        echo html_writer::table($tt);
    }
}

// Independent-access acknowledgements recorded by parents for this course (oversight, names only).
if ($canproxy && \tool_guardianlink\local\supervision_service::course_allows_independent($courseid)) {
    $acks = \tool_guardianlink\local\supervision_service::get_acknowledgements($courseid);
    echo $OUTPUT->heading(get_string('independentacks', 'tool_guardianlink'), 3);
    if ($acks) {
        $at = new html_table();
        $at->attributes['class'] = 'generaltable';
        $at->head = [get_string('selectlearner', 'tool_guardianlink'), get_string('authorisedadult', 'tool_guardianlink'),
            get_string('status', 'tool_guardianlink'), get_string('lastupdated', 'tool_guardianlink')];
        foreach ($acks as $ack) {
            $learner = core_user::get_user((int)$ack->childid, '*', IGNORE_MISSING);
            // The authorised adult's identity is relationship metadata: redact it for ordinary teachers
            // (who only need the operational status), reveal it only with family-metadata/audit/manage.
            if ($canseeadultnames) {
                $adult = core_user::get_user((int)$ack->guardianid, '*', IGNORE_MISSING);
                $adultcell = $adult ? fullname($adult) : (int)$ack->guardianid;
            } else {
                $adultcell = get_string('metadatahidden', 'tool_guardianlink');
            }
            $at->data[] = [
                $learner ? fullname($learner) : (int)$ack->childid,
                $adultcell,
                $ack->status === 'allowed'
                    ? html_writer::tag('span', s($ack->status), ['class' => 'badge badge-success'])
                    : s($ack->status),
                userdate($ack->timemodified),
            ];
        }
        echo html_writer::table($at);
    } else {
        echo $OUTPUT->notification(get_string('independentacksnone', 'tool_guardianlink'), 'info');
    }
}

// Per-course policy (editing teachers).
if ($policyform) {
    echo $OUTPUT->heading(get_string('coursepolicy', 'tool_guardianlink'), 3);
    $policyform->display();
}

echo $OUTPUT->footer();

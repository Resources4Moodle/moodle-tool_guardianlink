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
 * Messaging and digest administration — filterable tables.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('tool_guardianlink_messaging');
$context = context_system::instance();
require_capability('tool/guardianlink:managedigests', $context);

$fcourse = optional_param('fcourse', 0, PARAM_INT);
$fstatus = optional_param('fstatus', '', PARAM_ALPHA);
$fsince = optional_param('fsince', 0, PARAM_INT); // Days.
$baseurl = new moodle_url('/admin/tool/guardianlink/admin/messaging.php');
$PAGE->set_url($baseurl);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('admin_messaging', 'tool_guardianlink'));
echo \tool_guardianlink\local\ui::help_link('messaging');
echo $OUTPUT->notification(get_string('messagingintro', 'tool_guardianlink'), 'info');

// Course options derived from existing threads.
$courseids = $DB->get_fieldset_sql("SELECT DISTINCT courseid FROM {tool_guardianlink_msgthread} WHERE courseid > 0");
$courseopts = [0 => get_string('allcourses', 'tool_guardianlink')];
foreach ($courseids as $cid) {
    $courseopts[$cid] = format_string($DB->get_field('course', 'fullname', ['id' => $cid]) ?: ('#' . $cid));
}
$statusopts = ['' => get_string('allstatuses', 'tool_guardianlink'), 'open' => get_string('statusopen', 'tool_guardianlink'),
    'closed' => get_string('statusclosed', 'tool_guardianlink')];
$sinceopts = [0 => get_string('anytime', 'tool_guardianlink'), 7 => get_string('last7days', 'tool_guardianlink'),
    30 => get_string('last30days', 'tool_guardianlink'), 90 => get_string('last90days', 'tool_guardianlink')];

// Filter form (native).
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $baseurl->out(false), 'class' => 'form-inline mb-3']);
echo html_writer::label(get_string('course'), 'fcourse', false, ['class' => 'mr-1']);
echo html_writer::select($courseopts, 'fcourse', $fcourse, false, ['class' => 'mr-2']);
echo html_writer::label(get_string('status', 'tool_guardianlink'), 'fstatus', false, ['class' => 'mr-1']);
echo html_writer::select($statusopts, 'fstatus', $fstatus, false, ['class' => 'mr-2']);
echo html_writer::label(get_string('lastupdated', 'tool_guardianlink'), 'fsince', false, ['class' => 'mr-1']);
echo html_writer::select($sinceopts, 'fsince', $fsince, false, ['class' => 'mr-2']);
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'btn btn-secondary',
    'value' => get_string('applyfilters', 'tool_guardianlink'),
]);
echo html_writer::end_tag('form');

// Build filtered thread query.
$where = '1=1';
$params = [];
if ($fcourse) {
    $where .= ' AND courseid = :cid';
    $params['cid'] = $fcourse;
}
if ($fstatus !== '') {
    $where .= ' AND status = :st';
    $params['st'] = $fstatus;
}
if ($fsince > 0) {
    $where .= ' AND timemodified >= :since';
    $params['since'] = time() - ($fsince * DAYSECS);
}
$threads = $DB->get_records_select('tool_guardianlink_msgthread', $where, $params, 'timemodified DESC', '*', 0, 200);

echo $OUTPUT->heading(get_string('messagethreads', 'tool_guardianlink') . ' (' . count($threads) . ')', 3);
if ($threads) {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable';
    $table->head = ['ID', get_string('subject', 'tool_guardianlink'), get_string('selectlearner', 'tool_guardianlink'),
        get_string('course'), get_string('authorisedadult', 'tool_guardianlink'), get_string('status', 'tool_guardianlink'),
        get_string('lastupdated', 'tool_guardianlink')];
    foreach ($threads as $t) {
        $learner = core_user::get_user((int)$t->childid, '*', IGNORE_MISSING);
        $adult = core_user::get_user((int)$t->guardianid, '*', IGNORE_MISSING);
        $table->data[] = [
            (int)$t->id,
            html_writer::link(new moodle_url('/admin/tool/guardianlink/thread.php', ['id' => $t->id]), s($t->subject)),
            $learner ? fullname($learner) : (int)$t->childid,
            $t->courseid
                ? format_string($DB->get_field('course', 'fullname', ['id' => $t->courseid]) ?: ('#' . $t->courseid))
                : '-',
            $adult ? fullname($adult) : (int)$t->guardianid,
            s($t->status),
            userdate($t->timemodified),
        ];
    }
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(get_string('messagesnone', 'tool_guardianlink'), 'info');
}

// Digest preferences table (filterable by the same since window).
$digwhere = '1=1';
$digparams = [];
if ($fsince > 0) {
    $digwhere .= ' AND timemodified >= :since';
    $digparams['since'] = time() - ($fsince * DAYSECS);
}
$digests = $DB->get_records_select('tool_guardianlink_digestpref', $digwhere, $digparams, 'timemodified DESC', '*', 0, 200);
echo $OUTPUT->heading(get_string('digestpreferences', 'tool_guardianlink') . ' (' . count($digests) . ')', 3);
if ($digests) {
    $dt = new html_table();
    $dt->attributes['class'] = 'generaltable';
    $dt->head = [get_string('authorisedadult', 'tool_guardianlink'), get_string('selectlearner', 'tool_guardianlink'),
        get_string('digestfrequency', 'tool_guardianlink'), get_string('status', 'tool_guardianlink'),
        get_string('digestlastsent', 'tool_guardianlink'), get_string('digestnextsend', 'tool_guardianlink')];
    foreach ($digests as $d) {
        $adult = core_user::get_user((int)$d->guardianid, '*', IGNORE_MISSING);
        $learner = core_user::get_user((int)$d->childid, '*', IGNORE_MISSING);
        $dt->data[] = [
            $adult ? fullname($adult) : (int)$d->guardianid,
            $learner ? fullname($learner) : (int)$d->childid,
            s($d->frequency), s($d->status),
            $d->lastsent ? userdate($d->lastsent) : '-',
            $d->nextsend ? userdate($d->nextsend) : '-',
        ];
    }
    echo html_writer::table($dt);
} else {
    echo $OUTPUT->notification(get_string('digestsnone', 'tool_guardianlink'), 'info');
}

echo $OUTPUT->footer();

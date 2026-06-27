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
 * GuardianLink help manual — one page documenting every GuardianLink page, for admins and faculty.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

require_login();
$context = context_system::instance();

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/admin/tool/guardianlink/manual.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('manualtitle', 'tool_guardianlink'));
$PAGE->set_heading(get_string('manualtitle', 'tool_guardianlink'));

// The manual content. Each entry: id => [audience, title, html-body].
// Kept inline (English) as page documentation; section ids match tool_guardianlink_help_link() anchors.
$sections = [
    'overview' => ['everyone', 'What GuardianLink does',
        '<p>GuardianLink gives parents, guardians, carers, hostel wardens and tutors <strong>delegated, scoped, audited</strong> '
        . 'visibility of a learner\'s progress — without impersonating the learner and without exposing private contact details. '
        . 'Every authorised adult only ever sees what their relationship\'s scope permits, and all access is logged.</p>'
        . '<p>The plugin lives under <em>Site administration &rarr; Plugins &rarr; Admin tools &rarr; GuardianLink</em> for '
        . 'admins, '
        . 'and inside each course (secondary navigation) for teachers.</p>'],

    'relationships' => ['admin', 'Relationship registry (admin)',
        '<p><em>Admin tools &rarr; GuardianLink &rarr; Relationships.</em> The master registry of who may access whom. '
        . 'Add an authorised adult and a learner (both must be existing Moodle users — use the type-ahead pickers), choose the '
        . 'relationship type, access profile, legal/authority status, course/category scope and validity dates.</p>'
        . '<ul><li><strong>Edit</strong> an existing entry with the <em>Edit</em> link — it loads the record and updates it in '
        . 'place.</li>'
        . '<li><strong>Restrict</strong> flags a safeguarding/no-contact case (with a logged reason); restricted adults are '
        . 'excluded from all messaging.</li>'
        . '<li><strong>Proof</strong> attaches custody/validation evidence (admin-view only).</li></ul>'],

    'registry' => ['teacher', 'Course relationship registry (teacher)',
        '<p><em>Course &rarr; GuardianLink &rarr; Relationship registry (this course).</em> Visible only when an admin has granted '
        . 'you the '
        . '<code>tool/guardianlink:managecourseregistry</code> capability. It lists and lets you manage relationships for the '
        . 'learners '
        . '<strong>enrolled in this course</strong> only; new or edited entries are automatically scoped to this course. Contact '
        . 'details are never shown.</p>'],

    'uploadparents' => ['admin', 'Bulk upload parents/guardians (CSV)',
        '<p><em>Admin tools &rarr; GuardianLink &rarr; Upload parents/guardians.</em> Creates parent/guardian Moodle accounts and '
        . 'binds their '
        . 'relationships in one pass. Click <strong>Download sample CSV</strong> to get a correctly-headed example, edit it in a '
        . 'spreadsheet, keep the '
        . 'header row, then upload. Use <em>Preview only</em> first to validate before committing. The learner named in each row '
        . 'must already be a Moodle member.</p>'
        . '<p>Columns: adultusername, adultfirstname, adultlastname, adultemail, adultphone1, adultphone2, password, '
        . 'learneridnumber (or learnerusername), '
        . 'reltype, legal, accessprofile, courseids (comma-separated course IDs), status, authoritystatus.</p>'],

    'roletypes' => ['admin', 'Relationship role types',
        '<p><em>Admin tools &rarr; GuardianLink &rarr; Role types.</em> Define and enable the relationship vocabulary (legal '
        . 'parent, guardian, tutor, '
        . 'hostel warden, …). Only enabled types can be selected in the registry or accepted by the CSV importer and web '
        . 'services.</p>'],

    'profiles' => ['admin', 'Access profiles',
        '<p><em>Admin tools &rarr; GuardianLink &rarr; Access profiles.</em> Reusable bundles of scope permissions (overview, '
        . 'grades, completion, '
        . 'health summary, teacher contact, assisted access, …). Assign a profile to a relationship so permissions are applied '
        . 'consistently.</p>'],

    'organisations' => ['admin', 'Organisations',
        '<p><em>Admin tools &rarr; GuardianLink &rarr; Organisations.</em> Register hostels, residential care and similar bodies '
        . 'and their members, '
        . 'so an organisation can hold an oversight relationship to a cohort of learners.</p>'],

    'health' => ['admin', 'Health / care summaries',
        '<p><em>Admin tools &rarr; GuardianLink &rarr; Health.</em> Restricted learner health/care summaries, released only to '
        . 'adults whose scope '
        . 'includes the health-summary permission. Disabled by default; enable in Settings.</p>'],

    'tutor_requests' => ['admin', 'Tutor access requests',
        '<p><em>Admin tools &rarr; GuardianLink &rarr; Tutor requests.</em> Approve, narrow or reject requests for tutor access '
        . 'raised by guardians '
        . '(when permitted). Approval creates a scoped, time-limited tutor relationship.</p>'],

    'messaging' => ['admin', 'Messaging &amp; digests',
        '<p><em>Admin tools &rarr; GuardianLink &rarr; Messaging.</em> Filterable tables of message threads (by course, status, '
        . 'recency) and of digest '
        . 'preferences. Open any thread to read the in-app conversation. No email addresses are ever shown.</p>'],

    'templates' => ['admin', 'Email/message templates (admin, site-wide)',
        '<p><em>Admin tools &rarr; GuardianLink &rarr; Email templates.</em> Create site-wide templates with {placeholders}. These '
        . 'are available to every '
        . 'course. Teachers can also create their own course-scoped templates (see below).</p>'],

    'coursetemplates' => ['teacher', 'Course templates (teacher)',
        '<p><em>Course &rarr; GuardianLink &rarr; Manage this course\'s message templates.</em> Create templates that belong to '
        . 'your course, edit and '
        . 'delete them, and <strong>copy them into other courses you teach</strong> (the templates only — never recipients). A '
        . 'target course that already '
        . 'has a template with the same short name is overwritten.</p>'],

    'placeholders' => ['everyone', 'Placeholders (incl. per-activity results)',
        '<p>Templates and messages substitute {placeholders} per recipient. General ones include {learnerfullname}, {coursename}, '
        . '{grade}, {classaverage}, '
        . '{relativeperformance}, {activitygrades}, {completion}, {overdue}, {date}.</p>'
        . '<p><strong>Per-activity results:</strong> every gradable activity in the course exposes a unique token '
        . '<code>{grade_&lt;id&gt;}</code> (the learner\'s '
        . 'grade in that activity) and <code>{activity_&lt;id&gt;}</code> (its name). The message/template editors list the exact '
        . 'tokens for the current course. '
        . 'You can also pick one activity in the <em>Specific test/activity result</em> selector to fill {testname} and '
        . '{testresult}.</p>'
        . '<p>Grade placeholders are only filled for adults whose scope permits grades.</p>'],

    'bulkmail' => ['admin', 'Bulk mail (admin)',
        '<p><em>Admin tools &rarr; GuardianLink &rarr; Bulk mail.</em> Message the authorised adults of a whole course, category, '
        . 'cohort, or the at-risk '
        . '(overdue) audience, with filters (legal-only, verified-only, exclude-restricted). Preview the audience before '
        . 'sending.</p>'],

    'coursebulk' => ['teacher', 'Course bulk message (teacher)',
        '<p><em>Course &rarr; GuardianLink &rarr; Message guardians (bulk).</em> The course-scoped equivalent of admin bulk mail: '
        . 'pick the audience (all '
        . 'learners with reachable adults, or only at-risk/overdue learners), a template, optionally a specific test, preview, and '
        . 'send.</p>'],

    'teachers' => ['teacher', 'Message a learner\'s authorised adults',
        '<p><em>Course dashboard &rarr; Send message.</em> Compose an HTML message to one learner\'s authorised adults. Start from '
        . 'a template, insert any '
        . 'placeholder (including per-activity result tokens), optionally attach a specific test result, and send. Replies happen '
        . 'in-app via the thread link; '
        . 'adults never see your address and you never see theirs.</p>'],

    'sendresults' => ['teacher', 'Email results to parents',
        '<p><em>Course dashboard &rarr; Send results.</em> Choose a results/manual template (course or site-wide), optionally a '
        . 'specific test, preview the '
        . 'rendered message, and send it to the learner\'s authorised adults.</p>'],

    'coursedash' => ['teacher', 'In-course faculty dashboard',
        '<p><em>Course &rarr; GuardianLink.</em> Summary cards (learners, with-adults, at-risk, open threads), a filter/search '
        . 'bar, and a per-learner table '
        . 'showing completion, course grade, overdue count and reachable adults with quick Message/Results actions. Open message '
        . 'threads are listed with reply '
        . 'links, and editing teachers can set the per-course policy.</p>'],

    'report' => ['admin', 'Consolidated report',
        '<p><em>Admin tools &rarr; GuardianLink &rarr; Oversight reports.</em> Headline summary cards, a status pie chart '
        . 'and a role-type '
        . 'bar chart, plus a single '
        . 'consolidated figures table. Use <strong>Export consolidated report (CSV)</strong> to download all figures. Aggregates '
        . 'only — no personal details.</p>'],

    'audit' => ['admin', 'Audit log',
        '<p><em>Admin tools &rarr; GuardianLink &rarr; Audit.</em> The access and action log: who did what, when, in which course. '
        . 'Retention is configurable in Settings.</p>'],

    'revalidation' => ['admin', 'Re-validation',
        '<p><em>Admin tools &rarr; GuardianLink &rarr; Re-validation.</em> Relationships due for periodic re-validation. '
        . 'Re-validating advances the review date '
        . 'and records a custody note. The cycle length is set in Settings.</p>'],

    'integrations' => ['admin', 'ERP/SIS integration &amp; web services',
        '<p><em>Admin tools &rarr; GuardianLink &rarr; Integrations.</em> Enable the <code>guardianlink_erp</code> web service to '
        . 'let a SIS/ERP create and '
        . 'reconcile relationships, and to send bulk messages server-side. Recipient lookups never return email addresses.</p>'],

    'api' => ['developer', 'Developer API for other plugins',
        '<p>Other plugins integrate via <code>\\tool_guardianlink\\api</code>: <code>get_guardians_for_learner()</code>, '
        . '<code>get_learners_for_guardian()</code>, <code>can_access()</code>, <code>has_relationship()</code>, '
        . '<code>notify_guardians()</code>, '
        . '<code>render_template()</code>, <code>get_progress()</code>, <code>placeholders()</code>. The API never returns contact '
        . 'details and always sends '
        . 'through the audited no-reply channel.</p>'],

    'assisted' => ['admin', 'Governed assisted access',
        '<p>When enabled by the school (Settings + per-course policy + relationship scope), an adult may co-login alongside a '
        . 'young learner. This is a governed, '
        . 'banner-flagged, time-capped, audited wrapper — graded activities are blocked and the authorised-adult role keeps '
        . 'Moodle\'s login-as prohibited.</p>'],

    'independent' => ['everyone', 'Independent (unsupervised) access',
        '<p>By default a school can ask that a parent/guardian is present when a learner uses a course. Independent access lifts '
        . 'that for a specific course through a deliberate three-key workflow: (1) an administrator enables the capability in '
        . '<em>Settings</em>; (2) a teacher ticks <em>Allow independent access</em> in the course\'s GuardianLink policy; and '
        . '(3) the parent opens <em>Allow independent (unsupervised) access</em> from their learner\'s GuardianLink page and '
        . 'acknowledges it per course. The parent can revoke at any time. When the school sets the supervision-required posture, '
        . 'learners see a non-blocking reminder on courses they have not been granted independent access to — '
        . 'it never locks them out.</p>'
        . '<p>Higher-education note: a parent can also be added purely as a grades observer (the "Observer parent" relationship '
        . 'type / "Grades and teacher contact only" access profile), seeing grades and contacting the teacher for the courses they '
        . 'are scoped to, and nothing else.</p>'],

    'settings' => ['admin', 'Settings',
        '<p><em>Admin tools &rarr; GuardianLink &rarr; Settings.</em> Global toggles and limits: assisted mode, tutor '
        . 'proposal/approval, grant durations and '
        . 'review lead time, re-validation cycle, consent policies, health records, trusted sources and audit retention.</p>'],

    'privacy' => ['everyone', 'Privacy guarantees',
        '<p>GuardianLink never shows an adult\'s email or phone to teachers or other parents. All messages are sent from the '
        . 'no-reply user with the sender\'s '
        . '<em>name</em> (not address) and replies forced in-app. Grades are only ever released to adults whose relationship scope '
        . 'permits grades.</p>'],
];

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manualtitle', 'tool_guardianlink'));
echo $OUTPUT->notification(get_string('manualintro', 'tool_guardianlink'), 'info');

// Table of contents.
echo $OUTPUT->heading(get_string('manualcontents', 'tool_guardianlink'), 3);
echo html_writer::start_tag('ul');
foreach ($sections as $id => $sec) {
    echo html_writer::tag(
        'li',
        html_writer::link(new moodle_url($PAGE->url, [], $id), $sec[1])
        . ' ' . html_writer::tag('span', $sec[0], ['class' => 'badge badge-light'])
    );
}
echo html_writer::end_tag('ul');

// Sections.
foreach ($sections as $id => $sec) {
    echo html_writer::start_div('card mb-3');
    echo html_writer::start_div('card-body');
    echo html_writer::tag(
        'h3',
        s($sec[1]) . ' ' . html_writer::tag('span', $sec[0], ['class' => 'badge badge-secondary']),
        ['id' => $id]
    );
    echo $sec[2];
    echo html_writer::link(
        new moodle_url($PAGE->url, [], 'top'),
        get_string('backtotop', 'tool_guardianlink'),
        ['class' => 'small']
    );
    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo $OUTPUT->footer();

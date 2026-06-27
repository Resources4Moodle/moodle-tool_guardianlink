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
 * GuardianLink consolidated oversight report (aggregates for safeguarding/DPO staff).
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('tool_guardianlink_report');
$context = context_system::instance();
require_capability('tool/guardianlink:viewreports', $context);

$export = optional_param('export', '', PARAM_ALPHA);

// Gather all figures once (used by both the screen view and the CSV export).
$bystatus = $DB->get_records_sql("SELECT status, COUNT(*) AS n FROM {tool_guardianlink_rel} GROUP BY status ORDER BY status");
$bytype = $DB->get_records_sql(
    "SELECT reltype, COUNT(*) AS n FROM {tool_guardianlink_rel} WHERE status = 'active' GROUP BY reltype ORDER BY n DESC"
);
$counters = [
    'reportrestricted' => $DB->count_records('tool_guardianlink_rel', ['authoritystatus' => 'restricted']),
    'reportpendingtutor' => $DB->count_records('tool_guardianlink_tutorreq', ['status' => 'pending']),
    'reportpendingrel' => $DB->count_records('tool_guardianlink_rel', ['status' => 'pending']),
    'reportconsents' => $DB->count_records_select('tool_guardianlink_policy', "status = 'accepted'"),
    'reporthealth' => $DB->count_records('tool_guardianlink_health', ['status' => 'active']),
];
$totalrel = $DB->count_records('tool_guardianlink_rel');
$totalactive = $DB->count_records('tool_guardianlink_rel', ['status' => 'active']);

// CSV export of the consolidated figures.
if ($export === 'csv') {
    require_once($CFG->libdir . '/csvlib.class.php');
    $csv = new csv_export_writer();
    $csv->set_filename('guardianlink_report_' . userdate(time(), '%Y%m%d'));
    $csv->add_data([get_string('reportsection', 'tool_guardianlink'),
        get_string('reportmetric', 'tool_guardianlink'), get_string('count', 'tool_guardianlink')]);
    foreach ($bystatus as $r) {
        $csv->add_data([get_string('reportbystatus', 'tool_guardianlink'), $r->status, (int)$r->n]);
    }
    foreach ($bytype as $r) {
        $csv->add_data([get_string('reportbytype', 'tool_guardianlink'), $r->reltype, (int)$r->n]);
    }
    foreach ($counters as $key => $val) {
        $csv->add_data([get_string('reportgovernance', 'tool_guardianlink'),
            get_string($key, 'tool_guardianlink'), (int)$val]);
    }
    $csv->download_file();
    exit;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('admin_report', 'tool_guardianlink'));
echo \tool_guardianlink\local\ui::help_link('report');
echo $OUTPUT->notification(get_string('reportintro', 'tool_guardianlink'), 'info');

// Export button.
echo html_writer::div($OUTPUT->single_button(
    new moodle_url('/admin/tool/guardianlink/admin/report.php', ['export' => 'csv']),
    get_string('reportexportcsv', 'tool_guardianlink'),
    'get'
), 'mb-3');

// Headline summary cards.
$cards = [
    get_string('reporttotalrel', 'tool_guardianlink') => $totalrel,
    get_string('reporttotalactive', 'tool_guardianlink') => $totalactive,
    get_string('reportrestricted', 'tool_guardianlink') => $counters['reportrestricted'],
    get_string('reportpendingrel', 'tool_guardianlink') => $counters['reportpendingrel'],
    get_string('reporthealth', 'tool_guardianlink') => $counters['reporthealth'],
];
echo html_writer::start_div('d-flex flex-wrap mb-4');
foreach ($cards as $label => $value) {
    echo html_writer::div(
        html_writer::tag('div', $value, ['class' => 'h2 mb-0']) . html_writer::tag('div', $label, ['class' => 'small text-muted']),
        'card card-body mr-2 mb-2 text-center',
        ['style' => 'min-width:9rem;']
    );
}
echo html_writer::end_div();

// Charts (the "report drawing" capability).
echo html_writer::start_div('row');

// Pie: relationships by status.
if ($bystatus) {
    $labels = [];
    $values = [];
    foreach ($bystatus as $r) {
        $labels[] = $r->status;
        $values[] = (int)$r->n;
    }
    $pie = new \core\chart_pie();
    $pie->set_title(get_string('reportbystatus', 'tool_guardianlink'));
    $pie->add_series(new \core\chart_series(get_string('count', 'tool_guardianlink'), $values));
    $pie->set_labels($labels);
    echo html_writer::div($OUTPUT->render($pie), 'col-md-6 mb-3');
}

// Bar: active relationships by role type.
if ($bytype) {
    $labels = [];
    $values = [];
    foreach ($bytype as $r) {
        $labels[] = $r->reltype;
        $values[] = (int)$r->n;
    }
    $bar = new \core\chart_bar();
    $bar->set_title(get_string('reportbytype', 'tool_guardianlink'));
    $bar->add_series(new \core\chart_series(get_string('count', 'tool_guardianlink'), $values));
    $bar->set_labels($labels);
    echo html_writer::div($OUTPUT->render($bar), 'col-md-6 mb-3');
}
echo html_writer::end_div();

// Consolidated detail table.
$consol = new html_table();
$consol->attributes['class'] = 'generaltable';
$consol->head = [get_string('reportsection', 'tool_guardianlink'), get_string('reportmetric', 'tool_guardianlink'),
    get_string('count', 'tool_guardianlink')];
foreach ($bystatus as $r) {
    $consol->data[] = [get_string('reportbystatus', 'tool_guardianlink'), s($r->status), (int)$r->n];
}
foreach ($bytype as $r) {
    $consol->data[] = [get_string('reportbytype', 'tool_guardianlink'), s($r->reltype), (int)$r->n];
}
foreach ($counters as $key => $val) {
    $consol->data[] = [get_string('reportgovernance', 'tool_guardianlink'),
        get_string($key, 'tool_guardianlink'), (int)$val];
}
echo $OUTPUT->heading(get_string('reportconsolidated', 'tool_guardianlink'), 3);
echo html_writer::table($consol);

echo $OUTPUT->footer();

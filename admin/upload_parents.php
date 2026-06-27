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
 * Admin web UI: upload a CSV of parents/guardians, create their Moodle accounts, and pre-bind.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/csvlib.class.php');

use tool_guardianlink\local\relationship_service as RS;

admin_externalpage_setup('tool_guardianlink_uploadparents');
$context = context_system::instance();
require_capability('tool/guardianlink:maprelationships', $context);

// Serve a ready-to-edit sample CSV so an admin can see exactly what the importer expects.
if (optional_param('samplecsv', 0, PARAM_BOOL)) {
    $cols = ['adultusername', 'adultfirstname', 'adultlastname', 'adultemail', 'adultphone1', 'adultphone2',
        'password', 'learneridnumber', 'reltype', 'legal', 'accessprofile', 'courseids', 'status', 'authoritystatus'];
    $rows = [
        ['jdoe.parent', 'John', 'Doe', 'john.doe@example.com', '+44 7700 900001', '', '', 'S12345',
            'legal_parent', '1', 'family_full', '3', 'active', 'verified'],
        ['msmith.guardian', 'Mary', 'Smith', 'mary.smith@example.com', '', '', '', 'S12346',
            'guardian', '0', 'family_basic', '3,4', 'active', 'verified'],
    ];
    $quote = fn($v) => (preg_match('/[",\s]/', (string)$v)) ? '"' . str_replace('"', '""', (string)$v) . '"' : (string)$v;
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="guardianlink_parents_sample.csv"');
    echo implode(',', $cols) . "\r\n";
    foreach ($rows as $r) {
        echo implode(',', array_map($quote, $r)) . "\r\n";
    }
    exit;
}

$PAGE->set_url(new moodle_url('/admin/tool/guardianlink/admin/upload_parents.php'));
$form = new \tool_guardianlink\form\upload_parents_form();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('uploadparents', 'tool_guardianlink'));
echo \tool_guardianlink\local\ui::help_link('uploadparents');
echo $OUTPUT->notification(get_string('uploadintro', 'tool_guardianlink'), 'info');

// Sample CSV download so an admin can understand the exact format expected.
echo html_writer::div(
    $OUTPUT->single_button(
        new moodle_url('/admin/tool/guardianlink/admin/upload_parents.php', ['samplecsv' => 1]),
        get_string('uploadsamplecsv', 'tool_guardianlink'),
        'get'
    )
    . html_writer::tag('p', get_string('uploadsamplecsv_help', 'tool_guardianlink'), ['class' => 'small text-muted mt-1']),
    'mb-3'
);

if ($data = $form->get_data()) {
    $content = $form->get_file_content('csvfile');
    $preview = !empty($data->previewonly);
    $defaultpw = trim((string)$data->defaultpassword);
    $forcechange = !empty($data->forcepasswordchange) ? 1 : 0;
    $admin = get_admin();

    // Valid, enabled relationship types in THIS Moodle instance (the requested validation).
    $validtypes = RS::get_relationship_type_options(true);

    $iid = csv_import_reader::get_new_iid('guardianlink_parents');
    $cir = new csv_import_reader($iid, 'guardianlink_parents');
    $readcount = $cir->load_csv_content($content, 'utf-8', 'comma');
    if ($readcount === false || $readcount === null) {
        echo $OUTPUT->notification($cir->get_error(), 'error');
        $cir->cleanup(true);
        $form->display();
        echo $OUTPUT->footer();
        exit;
    }
    $columns = array_map('trim', $cir->get_columns());

    $table = new html_table();
    $table->head = ['#', get_string('authorisedadult', 'tool_guardianlink'), get_string('selectlearner', 'tool_guardianlink'),
        get_string('reltype', 'tool_guardianlink'), get_string('status', 'tool_guardianlink')];
    $rownum = 0;
    $ok = 0;
    $errors = 0;
    $cir->init();
    while ($line = $cir->next()) {
        $rownum++;
        $row = [];
        foreach ($columns as $i => $col) {
            $row[$col] = $line[$i] ?? '';
        }
        $row['sourcecode'] = 'WEBCSV';
        $adultlabel = $row['adultemail'] ?? ($row['adultusername'] ?? '?');
        $reltype = trim((string)($row['reltype'] ?? 'legal_parent'));
        try {
            // Validation: the relationship type must exist and be enabled here.
            if (!array_key_exists($reltype, $validtypes)) {
                throw new \moodle_exception('uploadbadreltype', 'tool_guardianlink', '', $reltype);
            }
            // Resolve the learner (must already be a full Moodle member, and is found by id/username/idnumber/email).
            if (!empty($row['learnerusername']) && empty($row['learneridnumber'])) {
                $row['learneridnumber'] = $row['learnerusername'];
            }
            $learnerid = RS::resolve_user_id($row, 'learnerid', 'learneridnumber');
            if (!$learnerid) {
                throw new \moodle_exception('uploadnolearner', 'tool_guardianlink');
            }
            $learner = core_user::get_user(
                $learnerid,
                'id,firstname,lastname,firstnamephonetic,lastnamephonetic,middlename,alternatename',
                MUST_EXIST
            );

            if ($preview) {
                $table->data[] = [$rownum, s($adultlabel), fullname($learner), s($reltype),
                    html_writer::tag('span', get_string('uploadwillcreate', 'tool_guardianlink'), ['class' => 'text-info'])];
                $ok++;
                continue;
            }

            // Phase 1: create the Moodle account (inbuilt) with credentials + password.
            if (empty($row['password']) && $defaultpw !== '') {
                $row['password'] = $defaultpw;
            }
            $row['forcepasswordchange'] = $forcechange;
            $adultid = RS::provision_adult($row, $admin->id);

            // Phase 2: the plugin populates its tables — bind the guardian relationship + course scopes.
            $payload = $row;
            $payload['adultid'] = $adultid;
            $payload['status'] = $row['status'] ?? RS::STATUS_ACTIVE;
            $payload['authoritystatus'] = $row['authoritystatus'] ?? 'verified';
            $courseids = array_filter(array_map('intval', explode(',', (string)($row['courseids'] ?? ''))));
            if ($courseids) {
                $payload['scopes'] = array_map(fn($cid) => ['scopekind' => 'course', 'courseid' => $cid], $courseids);
            }
            RS::add_or_update_relationship($payload, $admin->id, true);

            $table->data[] = [$rownum, s($adultlabel), fullname($learner), s($reltype),
                html_writer::tag('strong', get_string('uploadlinked', 'tool_guardianlink'), ['class' => 'text-success'])];
            $ok++;
        } catch (\Throwable $e) {
            $errors++;
            $table->data[] = [$rownum, s($adultlabel), s($row['learneridnumber'] ?? ($row['learnerusername'] ?? '?')),
                s($reltype), html_writer::tag('span', s($e->getMessage()), ['class' => 'text-danger'])];
        }
    }
    $cir->cleanup(true);
    $cir->close();

    $summary = $preview
        ? get_string('uploadpreviewsummary', 'tool_guardianlink', (object)['ok' => $ok, 'errors' => $errors])
        : get_string('uploadcommitsummary', 'tool_guardianlink', (object)['ok' => $ok, 'errors' => $errors]);
    echo $OUTPUT->notification($summary, $errors ? 'warning' : 'success');
    echo html_writer::table($table);
    if ($preview && $ok > 0) {
        echo $OUTPUT->notification(get_string('uploadpreviewnext', 'tool_guardianlink'), 'info');
    }
}

$form->display();
echo $OUTPUT->footer();

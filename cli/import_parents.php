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
 * CLI bulk import: create parent/guardian accounts (with phone) AND associate them with children.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use tool_guardianlink\local\relationship_service as RS;

[$options, $unrecognized] = cli_get_params([
    'help' => false,
    'file' => null,
    'sourcecode' => 'CSV',
    'dryrun' => false,
], ['h' => 'help', 'f' => 'file', 's' => 'sourcecode']);

if ($options['help'] || empty($options['file'])) {
    cli_writeln("GuardianLink bulk PARENT importer — creates adult accounts (with phone) and links them to learners.\n\n"
        . "Required per row to CREATE an adult: adultemail, adultfirstname, adultlastname.\n"
        . "Learner is resolved by learneridnumber / learnerusername / learnerid (must already exist).\n"
        . "Optional: adultusername, adultphone1, adultphone2, password, reltype, legal, accessprofile, "
        . "courseids, status, authoritystatus.\n\n"
        . "Example:\n  php admin/tool/guardianlink/cli/import_parents.php --file=/tmp/parents.csv\n");
    exit(0);
}
if (!is_readable($options['file'])) {
    cli_error('CSV file is not readable: ' . $options['file']);
}

$admin = get_admin();
$handle = fopen($options['file'], 'r');
$header = fgetcsv($handle);
if (!$header) {
    cli_error('CSV header row missing.');
}
$header = array_map('trim', $header);

$count = 0;
$created = 0;
$linked = 0;
$failed = 0;
while (($row = fgetcsv($handle)) !== false) {
    if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) {
        continue; // Skip blank lines.
    }
    $count++;
    $data = @array_combine($header, $row);
    if ($data === false) {
        $failed++;
        cli_writeln("Row {$count}: column count does not match header");
        continue;
    }
    $data['sourcecode'] = $options['sourcecode'];
    try {
        if ($options['dryrun']) {
            $learnerid = RS::resolve_user_id($data, 'learnerid', 'learneridnumber');
            if (!$learnerid && !empty($data['learnerusername'])) {
                $data['learneridnumber'] = $data['learnerusername'];
                $learnerid = RS::resolve_user_id($data, 'learnerid', 'learneridnumber');
            }
            cli_writeln("Row {$count}: DRYRUN adult='" . ($data['adultemail'] ?? $data['adultusername'] ?? '?')
                . "' learner=" . ($learnerid ?: 'NOT FOUND'));
            continue;
        }
        // 1. Create or find the adult, capturing phone.
        $adultid = RS::provision_adult($data, $admin->id);
        $created += ($adultid > 0 ? 1 : 0);
        // 2. Resolve learner (must exist; allow username via learnerusername).
        if (!empty($data['learnerusername']) && empty($data['learneridnumber'])) {
            $data['learneridnumber'] = $data['learnerusername'];
        }
        // 3. Associate.
        $payload = $data;
        $payload['adultid'] = $adultid;
        $payload['status'] = $data['status'] ?? RS::STATUS_ACTIVE;
        $payload['authoritystatus'] = $data['authoritystatus'] ?? 'verified';
        $courseids = array_filter(array_map('intval', explode(',', (string)($data['courseids'] ?? ''))));
        if ($courseids) {
            $payload['scopes'] = array_map(fn($cid) => ['scopekind' => 'course', 'courseid' => $cid], $courseids);
        }
        RS::add_or_update_relationship($payload, $admin->id, true);
        $linked++;
    } catch (Throwable $e) {
        $failed++;
        cli_writeln("Row {$count}: " . $e->getMessage());
    }
}
fclose($handle);

RS::log_sync_event(
    $options['sourcecode'],
    'relationship',
    $options['dryrun'] ? 'parent_dryrun' : 'parent_import',
    $failed ? 'partial' : 'success',
    $count,
    $linked,
    $failed,
    $admin->id
);
cli_writeln("Rows {$count}; adults provisioned {$created}; links {$linked}; failed {$failed}.");

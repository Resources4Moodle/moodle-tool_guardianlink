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
 * CLI CSV importer for GuardianLink relationships.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params([
    'help' => false,
    'file' => null,
    'sourcecode' => 'CSV',
    'dryrun' => false,
], [
    'h' => 'help',
    'f' => 'file',
    's' => 'sourcecode',
]);

if ($options['help'] || empty($options['file'])) {
    cli_writeln(
        "GuardianLink relationship CSV importer\n\n"
        . "Required columns: adultid or adultidnumber, learnerid or learneridnumber.\n"
        . "Optional: externalid, reltype, legal, authoritybasis, authoritystatus, confidentiality, status, "
        . "accessprofile, courseids, starttime, endtime, reviewtime, notes.\n\n"
        . "Example:\n"
        . "php admin/tool/guardianlink/cli/import_relationships.php --file=/path/relationships.csv --sourcecode=ERP"
    );
    exit(0);
}

$path = $options['file'];
if (!is_readable($path)) {
    cli_error('CSV file is not readable: ' . $path);
}

$handle = fopen($path, 'r');
$header = fgetcsv($handle);
if (!$header) {
    cli_error('CSV header row missing.');
}
$header = array_map('trim', $header);
$count = 0;
$success = 0;
$failed = 0;
while (($row = fgetcsv($handle)) !== false) {
    $count++;
    $payload = array_combine($header, $row);
    if ($payload === false) {
        $failed++;
        cli_writeln("Row {$count}: invalid column count");
        continue;
    }
    $payload['sourcecode'] = $options['sourcecode'];
    try {
        if (!$options['dryrun']) {
            \tool_guardianlink\local\relationship_service::add_or_update_relationship($payload, 0, true);
        } else {
            \tool_guardianlink\local\relationship_service::resolve_user_id($payload, 'adultid', 'adultidnumber');
            \tool_guardianlink\local\relationship_service::resolve_user_id($payload, 'learnerid', 'learneridnumber');
        }
        $success++;
    } catch (Throwable $e) {
        $failed++;
        cli_writeln("Row {$count}: " . $e->getMessage());
    }
}
fclose($handle);
\tool_guardianlink\local\relationship_service::log_sync_event(
    $options['sourcecode'],
    'relationship',
    $options['dryrun'] ? 'csv_dryrun' : 'csv_import',
    $failed ? 'partial' : 'success',
    $count,
    $success,
    $failed,
    0
);
cli_writeln("Processed {$count}; succeeded {$success}; failed {$failed}.");

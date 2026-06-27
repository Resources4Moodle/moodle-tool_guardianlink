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
 * GuardianLink privacy / no-email-leak self-check. Run in CI or by hand:.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../../config.php');

$root = realpath(__DIR__ . '/..');
$fail = 0;
$say = function (bool $ok, string $label) use (&$fail) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . "\n";
    if (!$ok) {
        $fail++;
    }
};
// Contact/PII keys that must never appear in a web-service return structure.
$contactre = '/(email|e_mail|phone|address|username|mobile|maildisplay)/i';

echo "GuardianLink privacy / no-email-leak check\n";
echo "==========================================\n";

// 1. No external function return structure exposes a contact field.
echo "[1] Web-service return structures expose no contact fields\n";
$extdir = $root . '/classes/external';
foreach (glob($extdir . '/*.php') as $file) {
    $short = basename($file, '.php');
    if ($short === 'base') {
        continue;
    }
    $class = '\\tool_guardianlink\\external\\' . $short;
    if (!class_exists($class) || !method_exists($class, 'execute_returns')) {
        continue;
    }
    $structure = $class::execute_returns();
    $keys = [];
    $walk = function ($node, $prefix = '') use (&$walk, &$keys) {
        if ($node instanceof \core_external\external_single_structure) {
            foreach ($node->keys as $k => $child) {
                $keys[] = $k;
                $walk($child, $k);
            }
        } else if ($node instanceof \core_external\external_multiple_structure) {
            $walk($node->content, $prefix);
        }
    };
    $walk($structure);
    $bad = array_filter($keys, fn($k) => preg_match($GLOBALS['contactre'], $k));
    $say(empty($bad), "$short returns no contact field" . ($bad ? ' (FOUND: ' . implode(',', $bad) . ')' : ''));
}

// 2. All outbound messages use no-reply addressing (no real sender email can reach From/Reply-To).
echo "[2] Outbound messages use no-reply addressing\n";
$msgsrc = file_get_contents($root . '/classes/local/message_service.php');
$bulksrc = file_get_contents($root . '/classes/local/bulk_message_service.php');
$say(strpos($msgsrc, 'userfrom = $sender') === false, 'message_service: no message sent directly from a real sender');
$say(substr_count($bulksrc, 'userfrom = $sender') === 0, 'bulk_message_service: no message sent directly from a real sender');
$say(
    strpos($msgsrc, 'get_noreply_user()') !== false && strpos($msgsrc, '$message->replyto') !== false,
    'message_service: forces no-reply user + Reply-To'
);
$say(
    strpos($bulksrc, 'get_noreply_user()') !== false && strpos($bulksrc, '$message->replyto') !== false,
    'bulk_message_service: forces no-reply user + Reply-To'
);

// 3. No page (entry-point) outputs a user email or phone number.
echo "[3] No plugin page outputs an email or phone number\n";
$pageglob = array_merge(glob($root . '/*.php'), glob($root . '/admin/*.php'), glob($root . '/my/*.php'));
$leaky = [];
foreach ($pageglob as $file) {
    $src = file_get_contents($file);
    // Any reference to ->email / ->phone in a page is suspicious (pages must not touch contact PII).
    if (preg_match('/->(email|phone1|phone2|phone)\b/', $src)) {
        $leaky[] = basename($file);
    }
}
$say(empty($leaky), 'no page references ->email or ->phone' . ($leaky ? ' (CHECK: ' . implode(',', $leaky) . ')' : ''));

// 4. Privacy metadata declares every tool_guardianlink_* table that stores a user id (personal data).
echo "[4] Privacy provider declares every personal-data table\n";
$collection = new \core_privacy\local\metadata\collection('tool_guardianlink');
\tool_guardianlink\privacy\provider::get_metadata($collection);
$declared = [];
foreach ($collection->get_collection() as $item) {
    if (method_exists($item, 'get_name')) {
        $declared[$item->get_name()] = true;
    }
}
$userlike = '/(userid|guardianid|childid|actorid|adultid|tutorid|requesterid|teacherid|approvedby|createdby|moodleuserid)/i';
$xml = file_get_contents($root . '/db/install.xml');
preg_match_all('/<TABLE NAME="(tool_guardianlink_[a-z_]+)"(.*?)<\/TABLE>/s', $xml, $m, PREG_SET_ORDER);
foreach ($m as $tbl) {
    [$full, $name, $body] = $tbl;
    if (preg_match($userlike, $body)) {
        $say(isset($declared[$name]), "personal-data table $name is declared in privacy metadata");
    }
}

echo "==========================================\n";
echo $fail === 0 ? "RESULT: ALL PRIVACY CHECKS PASSED\n" : "RESULT: $fail CHECK(S) FAILED\n";
exit($fail === 0 ? 0 : 1);

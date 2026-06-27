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
 * Policy/consent acknowledgement on behalf of a minor (GDPR Art. 8 style).
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guardianlink\local;

/**
 * Lets a legal-responsibility holder record consent to defined policies on behalf of a learner.
 * Consents are stored in tool_guardianlink_policy, audited, and (optionally) required before sensitive
 * actions such as starting an assisted session.
 */
class consent_service {
    /**
     * Configured policies requiring consent, as [key => label].
     * Admin setting "consentpolicies" holds lines of "key|Label".
     *
     * @return array
     */
    public static function required_policies(): array {
        $raw = (string)get_config('tool_guardianlink', 'consentpolicies');
        $policies = [];
        foreach (preg_split('/\r?\n/', $raw) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = explode('|', $line, 2);
            $key = clean_param(trim($parts[0]), PARAM_ALPHANUMEXT);
            if ($key === '') {
                continue;
            }
            $policies[$key] = isset($parts[1]) && trim($parts[1]) !== '' ? trim($parts[1]) : $key;
        }
        return $policies;
    }

    /**
     * Whether a specific consent (by the adult, for the learner) is currently on record.
     *
     * @param int $adultid
     * @param int $childid
     * @param string $policykey
     * @return bool
     */
    public static function has_consent(int $adultid, int $childid, string $policykey): bool {
        global $DB;
        $now = time();
        return $DB->record_exists_select(
            'tool_guardianlink_policy',
            "userid = :uid AND childid = :cid AND policykey = :pk AND status = 'accepted' AND (expires = 0 OR expires > :now)",
            ['uid' => $adultid, 'cid' => $childid, 'pk' => $policykey, 'now' => $now]
        );
    }

    /**
     * Policy keys still outstanding (not yet consented) for this adult/learner pair.
     *
     * @param int $adultid
     * @param int $childid
     * @return array [key => label]
     */
    public static function outstanding(int $adultid, int $childid): array {
        $out = [];
        foreach (self::required_policies() as $key => $label) {
            if (!self::has_consent($adultid, $childid, $key)) {
                $out[$key] = $label;
            }
        }
        return $out;
    }

    /**
     * Whether all configured policies are consented for this pair (true also when none are configured).
     *
     * @param int $adultid
     * @param int $childid
     * @return bool
     */
    public static function all_consented(int $adultid, int $childid): bool {
        return count(self::outstanding($adultid, $childid)) === 0;
    }

    /**
     * Record a consent on behalf of a learner. Only a legal-responsibility holder may consent.
     *
     * @param int $adultid
     * @param int $childid
     * @param string $policykey
     * @return int Record id.
     */
    public static function record(int $adultid, int $childid, string $policykey): int {
        global $DB;
        $relationship = relationship_service::get_active_relationship($adultid, $childid);
        if (!$relationship || empty($relationship->legal)) {
            throw new \required_capability_exception(
                \context_system::instance(),
                'tool/guardianlink:maprelationships',
                'nopermissions',
                ''
            );
        }
        $policykey = clean_param($policykey, PARAM_ALPHANUMEXT);
        if (!array_key_exists($policykey, self::required_policies())) {
            throw new \invalid_parameter_exception('Unknown policy key.');
        }
        $now = time();
        $existing = $DB->get_record(
            'tool_guardianlink_policy',
            ['userid' => $adultid, 'childid' => $childid, 'policykey' => $policykey]
        );
        $record = (object)[
            'userid' => $adultid,
            'childid' => $childid,
            'policykey' => $policykey,
            'status' => 'accepted',
            'sourcecode' => '',
            'timeaccepted' => $now,
            'expires' => 0,
        ];
        if ($existing) {
            $record->id = (int)$existing->id;
            $DB->update_record('tool_guardianlink_policy', $record);
            $id = (int)$existing->id;
        } else {
            $id = (int)$DB->insert_record('tool_guardianlink_policy', $record);
        }
        relationship_service::trigger_event(
            'consent_recorded',
            \context_user::instance($childid),
            $childid,
            0,
            ['policykey' => $policykey]
        );
        relationship_service::log_access($adultid, $childid, 'consent_recorded', 0, 'policy', $id, ['policykey' => $policykey]);
        return $id;
    }
}

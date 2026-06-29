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
 * Relationship, scoping, audit, ERP and health services for GuardianLink.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guardianlink\local;

/**
 * Central relationship, scoping, tutor, health, organisation and audit service.
 *
 * The database keeps the historical field names guardianid/childid for upgrade
 * continuity, but all UI and service methods should treat guardianid as
 * "authorised adult" and childid as "learner".
 */
class relationship_service {
    /** @var string Relationship is active and access is granted. */
    public const STATUS_ACTIVE = 'active';
    /** @var string Relationship is awaiting approval. */
    public const STATUS_PENDING = 'pending';
    /** @var string Relationship is temporarily suspended. */
    public const STATUS_SUSPENDED = 'suspended';
    /** @var string Relationship has passed its expiry date. */
    public const STATUS_EXPIRED = 'expired';
    /** @var string Relationship has been revoked. */
    public const STATUS_REVOKED = 'revoked';
    /** @var string Relationship request was rejected. */
    public const STATUS_REJECTED = 'rejected';
    /** @var string The only authority status that may expose learner data (adult-facing access invariant). */
    public const AUTHORITY_VERIFIED = 'verified';

    /**
     * Default relationship role taxonomy. Schools can add their own records.
     *
     * @return array[]
     */
    public static function default_relationship_types(): array {
        return [
            [
                'shortname' => 'legal_parent', 'name' => get_string('reltype_legal_parent', 'tool_guardianlink'),
                'category' => 'family', 'defaultprofile' => 'family_full',
                'maydelegate' => 1, 'mayholdlegal' => 1, 'sortorder' => 10,
            ],
            [
                'shortname' => 'parent_no_contact', 'name' => get_string('reltype_parent_no_contact', 'tool_guardianlink'),
                'category' => 'family', 'defaultprofile' => 'health_sensitive',
                'maydelegate' => 0, 'mayholdlegal' => 0, 'sortorder' => 20,
            ],
            [
                'shortname' => 'observer_parent', 'name' => get_string('reltype_observer_parent', 'tool_guardianlink'),
                'category' => 'family', 'defaultprofile' => 'highered_observer',
                'maydelegate' => 0, 'mayholdlegal' => 0, 'sortorder' => 25,
            ],
            [
                'shortname' => 'guardian', 'name' => get_string('reltype_guardian', 'tool_guardianlink'),
                'category' => 'family', 'defaultprofile' => 'family_full',
                'maydelegate' => 1, 'mayholdlegal' => 1, 'sortorder' => 30,
            ],
            [
                'shortname' => 'carer', 'name' => get_string('reltype_carer', 'tool_guardianlink'),
                'category' => 'care', 'defaultprofile' => 'family_basic',
                'maydelegate' => 0, 'mayholdlegal' => 0, 'sortorder' => 40,
            ],
            [
                'shortname' => 'foster_carer', 'name' => get_string('reltype_foster_carer', 'tool_guardianlink'),
                'category' => 'care', 'defaultprofile' => 'residential_care',
                'maydelegate' => 0, 'mayholdlegal' => 0, 'sortorder' => 50,
            ],
            [
                'shortname' => 'adoptive_parent', 'name' => get_string('reltype_adoptive_parent', 'tool_guardianlink'),
                'category' => 'family', 'defaultprofile' => 'family_full',
                'maydelegate' => 1, 'mayholdlegal' => 1, 'sortorder' => 60,
            ],
            [
                'shortname' => 'step_parent', 'name' => get_string('reltype_step_parent', 'tool_guardianlink'),
                'category' => 'family', 'defaultprofile' => 'family_basic',
                'maydelegate' => 0, 'mayholdlegal' => 0, 'sortorder' => 70,
            ],
            [
                'shortname' => 'hostel_warden', 'name' => get_string('reltype_hostel_warden', 'tool_guardianlink'),
                'category' => 'residential', 'defaultprofile' => 'hostel_guardian',
                'maydelegate' => 0, 'mayholdlegal' => 0, 'sortorder' => 80,
            ],
            [
                'shortname' => 'residential_keyworker',
                'name' => get_string('reltype_residential_keyworker', 'tool_guardianlink'),
                'category' => 'residential', 'defaultprofile' => 'residential_care',
                'maydelegate' => 0, 'mayholdlegal' => 0, 'sortorder' => 90,
            ],
            [
                'shortname' => 'orphanage_staff', 'name' => get_string('reltype_orphanage_staff', 'tool_guardianlink'),
                'category' => 'residential', 'defaultprofile' => 'residential_care',
                'maydelegate' => 0, 'mayholdlegal' => 0, 'sortorder' => 100,
            ],
            [
                'shortname' => 'case_worker', 'name' => get_string('reltype_case_worker', 'tool_guardianlink'),
                'category' => 'welfare', 'defaultprofile' => 'health_sensitive',
                'maydelegate' => 0, 'mayholdlegal' => 0, 'sortorder' => 110,
            ],
            [
                'shortname' => 'tutor', 'name' => get_string('reltype_tutor', 'tool_guardianlink'),
                'category' => 'education', 'defaultprofile' => 'tutor_limited',
                'maydelegate' => 0, 'mayholdlegal' => 0, 'sortorder' => 120,
            ],
            [
                'shortname' => 'mentor', 'name' => get_string('reltype_mentor', 'tool_guardianlink'),
                'category' => 'education', 'defaultprofile' => 'tutor_limited',
                'maydelegate' => 0, 'mayholdlegal' => 0, 'sortorder' => 130,
            ],
            [
                'shortname' => 'sponsor', 'name' => get_string('reltype_sponsor', 'tool_guardianlink'),
                'category' => 'education', 'defaultprofile' => 'family_basic',
                'maydelegate' => 0, 'mayholdlegal' => 0, 'sortorder' => 140,
            ],
            [
                'shortname' => 'other', 'name' => get_string('reltype_other', 'tool_guardianlink'),
                'category' => 'other', 'defaultprofile' => 'family_basic',
                'maydelegate' => 0, 'mayholdlegal' => 0, 'sortorder' => 999,
            ],
        ];
    }

    /**
     * Built-in default access profiles (seeded into tool_guardianlink_profile; used as fallback).
     *
     * @return array
     */
    private static function default_profiles(): array {
        return [
            'family_basic' => [
                'allowoverview' => 1, 'allowgrades' => 0, 'allowcompletion' => 1, 'allowactivities' => 1,
                'allowattendance' => 0, 'allowcalendar' => 1, 'allowteachercontact' => 1, 'allowmessaging' => 1,
                'allowassisted' => 0, 'allowhealthsummary' => 0, 'allowtutormanagement' => 0, 'allowpolicyconsent' => 0,
            ],
            'family_full' => [
                'allowoverview' => 1, 'allowgrades' => 1, 'allowcompletion' => 1, 'allowactivities' => 1,
                'allowattendance' => 1, 'allowcalendar' => 1, 'allowteachercontact' => 1, 'allowmessaging' => 1,
                'allowassisted' => 0, 'allowhealthsummary' => 0, 'allowtutormanagement' => 1, 'allowpolicyconsent' => 1,
            ],
            'tutor_limited' => [
                'allowoverview' => 1, 'allowgrades' => 0, 'allowcompletion' => 1, 'allowactivities' => 1,
                'allowattendance' => 0, 'allowcalendar' => 1, 'allowteachercontact' => 0, 'allowmessaging' => 0,
                'allowassisted' => 0, 'allowhealthsummary' => 0, 'allowtutormanagement' => 0, 'allowpolicyconsent' => 0,
            ],
            'hostel_guardian' => [
                'allowoverview' => 1, 'allowgrades' => 0, 'allowcompletion' => 1, 'allowactivities' => 1,
                'allowattendance' => 1, 'allowcalendar' => 1, 'allowteachercontact' => 1, 'allowmessaging' => 1,
                'allowassisted' => 0, 'allowhealthsummary' => 0, 'allowtutormanagement' => 0, 'allowpolicyconsent' => 0,
            ],
            'residential_care' => [
                'allowoverview' => 1, 'allowgrades' => 0, 'allowcompletion' => 1, 'allowactivities' => 1,
                'allowattendance' => 1, 'allowcalendar' => 1, 'allowteachercontact' => 1, 'allowmessaging' => 1,
                'allowassisted' => 0, 'allowhealthsummary' => 1, 'allowtutormanagement' => 0, 'allowpolicyconsent' => 0,
            ],
            'health_sensitive' => [
                'allowoverview' => 1, 'allowgrades' => 0, 'allowcompletion' => 0, 'allowactivities' => 0,
                'allowattendance' => 0, 'allowcalendar' => 0, 'allowteachercontact' => 0, 'allowmessaging' => 0,
                'allowassisted' => 0, 'allowhealthsummary' => 0, 'allowtutormanagement' => 0, 'allowpolicyconsent' => 0,
            ],
            // Higher-education "observer" parent: only grades and teacher communication, nothing else.
            'highered_observer' => [
                'allowoverview' => 1, 'allowgrades' => 1, 'allowcompletion' => 1, 'allowactivities' => 0,
                'allowattendance' => 0, 'allowcalendar' => 0, 'allowteachercontact' => 1, 'allowmessaging' => 1,
                'allowassisted' => 0, 'allowhealthsummary' => 0, 'allowtutormanagement' => 0, 'allowpolicyconsent' => 0,
            ],
        ];
    }

    /**
     * The permission flag fields that make up a profile.
     *
     * @return string[]
     */
    public static function profile_fields(): array {
        return array_keys(self::default_profiles()['family_basic']);
    }

    /**
     * Access profiles for UI/API/scopes — admin-editable, read from tool_guardianlink_profile when present,
     * falling back to the built-in defaults.
     *
     * @return array shortname => [permission => 0/1]
     */
    public static function access_profiles(): array {
        global $DB;
        if (!$DB->get_manager()->table_exists('tool_guardianlink_profile')) {
            return self::default_profiles();
        }
        $records = $DB->get_records('tool_guardianlink_profile', ['enabled' => 1], 'sortorder ASC, name ASC');
        if (!$records) {
            return self::default_profiles();
        }
        $out = [];
        foreach ($records as $rec) {
            $perms = [];
            foreach (self::profile_fields() as $f) {
                $perms[$f] = (int)$rec->{$f};
            }
            $out[$rec->shortname] = $perms;
        }
        return $out;
    }

    /**
     * Seed the built-in profiles into tool_guardianlink_profile if it is empty.
     */
    public static function ensure_default_profiles(): void {
        global $DB;
        if (!$DB->get_manager()->table_exists('tool_guardianlink_profile')) {
            return;
        }
        $names = [
            'family_basic' => get_string('profile_family_basic', 'tool_guardianlink'),
            'family_full' => get_string('profile_family_full', 'tool_guardianlink'),
            'tutor_limited' => get_string('profile_tutor_limited', 'tool_guardianlink'),
            'hostel_guardian' => get_string('profile_hostel_guardian', 'tool_guardianlink'),
            'residential_care' => get_string('profile_residential_care', 'tool_guardianlink'),
            'health_sensitive' => get_string('profile_health_sensitive', 'tool_guardianlink'),
            'highered_observer' => get_string('profile_highered_observer', 'tool_guardianlink'),
        ];
        $now = time();
        $sort = 0;
        foreach (self::default_profiles() as $shortname => $perms) {
            if ($DB->record_exists('tool_guardianlink_profile', ['shortname' => $shortname])) {
                continue;
            }
            $record = (object)array_merge($perms, [
                'shortname' => $shortname,
                'name' => $names[$shortname] ?? $shortname,
                'enabled' => 1,
                'systemmanaged' => 1,
                'sortorder' => ($sort += 10),
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
            $DB->insert_record('tool_guardianlink_profile', $record);
        }
    }

    /**
     * All profile records for admin management.
     *
     * @return array
     */
    public static function get_profiles(): array {
        global $DB;
        return $DB->get_records('tool_guardianlink_profile', null, 'sortorder ASC, name ASC');
    }

    /**
     * Profile options [shortname => name] for selectors.
     *
     * @return array
     */
    public static function get_profile_options(): array {
        $options = [];
        foreach (self::get_profiles() as $p) {
            if ($p->enabled) {
                $options[$p->shortname] = $p->name;
            }
        }
        if (!$options) {
            foreach (self::default_profiles() as $shortname => $perms) {
                $options[$shortname] = $shortname;
            }
        }
        return $options;
    }

    /**
     * Create/update an access profile.
     *
     * @param object|array $data
     * @param int $userid Acting user id (for audit), 0 if not applicable.
     * @return int
     */
    public static function save_profile(object|array $data, int $userid = 0): int {
        global $DB;
        $now = time();
        $record = (object)[
            'shortname' => clean_param((string)self::value($data, 'shortname', ''), PARAM_ALPHANUMEXT),
            'name' => clean_param((string)self::value($data, 'name', ''), PARAM_TEXT),
            'enabled' => empty(self::value($data, 'enabled', 1)) ? 0 : 1,
            'sortorder' => (int)self::value($data, 'sortorder', 0),
            'systemmanaged' => 0,
            'timemodified' => $now,
        ];
        foreach (self::profile_fields() as $f) {
            $record->{$f} = empty(self::value($data, $f, 0)) ? 0 : 1;
        }
        $id = (int)self::value($data, 'id', 0);
        if ($id > 0 && $DB->record_exists('tool_guardianlink_profile', ['id' => $id])) {
            $record->id = $id;
            $record->systemmanaged = (int)$DB->get_field('tool_guardianlink_profile', 'systemmanaged', ['id' => $id]);
            $DB->update_record('tool_guardianlink_profile', $record);
            return $id;
        }
        $record->timecreated = $now;
        return (int)$DB->insert_record('tool_guardianlink_profile', $record);
    }

    /**
     * Delete a (non system-managed) profile.
     *
     * @param int $id
     */
    public static function delete_profile(int $id): void {
        global $DB;
        $DB->delete_records_select('tool_guardianlink_profile', 'id = :id AND systemmanaged = 0', ['id' => $id]);
    }

    /**
     * Check and seed default relationship types.
     */
    public static function ensure_default_relationship_types(): void {
        global $DB;
        if (!$DB->get_manager()->table_exists('tool_guardianlink_reltype')) {
            return;
        }
        $now = time();
        foreach (self::default_relationship_types() as $type) {
            $existing = $DB->get_record('tool_guardianlink_reltype', ['shortname' => $type['shortname']]);
            $record = (object)[
                'shortname' => $type['shortname'],
                'name' => $type['name'],
                'description' => '',
                'category' => $type['category'],
                'defaultprofile' => $type['defaultprofile'],
                'maydelegate' => $type['maydelegate'],
                'mayholdlegal' => $type['mayholdlegal'],
                'systemmanaged' => 1,
                'active' => 1,
                'sortorder' => $type['sortorder'],
                'configjson' => json_encode(['seeded' => true]),
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            if ($existing) {
                $record->id = $existing->id;
                $record->timecreated = $existing->timecreated;
                $DB->update_record('tool_guardianlink_reltype', $record);
            } else {
                $DB->insert_record('tool_guardianlink_reltype', $record);
            }
        }
    }

    /**
     * Relationship type options for forms.
     *
     * @param bool $activeonly
     * @return array
     */
    public static function get_relationship_type_options(bool $activeonly = true): array {
        global $DB;
        $fallback = [];
        foreach (self::default_relationship_types() as $type) {
            $fallback[$type['shortname']] = $type['name'];
        }
        if (!$DB->get_manager()->table_exists('tool_guardianlink_reltype')) {
            return $fallback;
        }
        $conditions = $activeonly ? ['active' => 1] : null;
        $records = $DB->get_records('tool_guardianlink_reltype', $conditions, 'sortorder ASC, name ASC');
        if (!$records) {
            return $fallback;
        }
        $options = [];
        foreach ($records as $record) {
            $options[$record->shortname] = $record->name;
        }
        return $options;
    }

    /**
     * Resolve a Moodle user id from explicit user id, username, idnumber, or email.
     *
     * @param mixed $data
     * @param string $idfield
     * @param string $lookupfield
     * @return int
     */
    public static function resolve_user_id($data, string $idfield, string $lookupfield = ''): int {
        global $DB;
        $id = (int)self::value($data, $idfield, 0);
        if ($id > 0 && $DB->record_exists('user', ['id' => $id, 'deleted' => 0])) {
            return $id;
        }
        if ($lookupfield === '') {
            return 0;
        }
        $raw = trim((string)self::value($data, $lookupfield, ''));
        if ($raw === '') {
            return 0;
        }
        foreach (['idnumber', 'username', 'email'] as $field) {
            $record = $DB->get_record('user', [$field => $raw, 'deleted' => 0], 'id', IGNORE_MULTIPLE);
            if ($record) {
                return (int)$record->id;
            }
        }
        return 0;
    }

    /**
     * Update an adult's phone numbers (core user fields) if provided. Facilitates parent phone
     * collection during bulk import / ERP sync. Phone is PII held on the core user record and is
     * never exposed by the plugin to teachers or other parents.
     *
     * @param int $adultid
     * @param string $phone1
     * @param string $phone2
     */
    /**
     * The authorised adult's stored phone numbers, for pre-filling the admin registry edit form
     * only. Lives in the service (never a page) so the no-contact-on-pages guarantee holds: pages
     * call this getter rather than touching ->phone fields themselves.
     *
     * @param int $adultid
     * @return array ['phone1' => string, 'phone2' => string]
     */
    public static function get_adult_phones(int $adultid): array {
        global $DB;
        $rec = $adultid > 0
            ? $DB->get_record('user', ['id' => $adultid, 'deleted' => 0], 'id,phone1,phone2', IGNORE_MISSING)
            : null;
        return [
            'phone1' => $rec ? (string)$rec->phone1 : '',
            'phone2' => $rec ? (string)$rec->phone2 : '',
        ];
    }

    /**
     * Store or update the contact phone numbers for an authorised adult.
     *
     * @param int $adultid The user id of the authorised adult.
     * @param string $phone1 Primary phone number.
     * @param string $phone2 Secondary phone number.
     */
    public static function update_adult_phone(int $adultid, string $phone1 = '', string $phone2 = ''): void {
        global $DB;
        if ($adultid <= 0) {
            return;
        }
        $update = ['id' => $adultid];
        if (trim($phone1) !== '') {
            $update['phone1'] = clean_param($phone1, PARAM_NOTAGS);
        }
        if (trim($phone2) !== '') {
            $update['phone2'] = clean_param($phone2, PARAM_NOTAGS);
        }
        if (count($update) > 1 && $DB->record_exists('user', ['id' => $adultid, 'deleted' => 0])) {
            $update['timemodified'] = time();
            $DB->update_record('user', (object)$update);
        }
    }

    /**
     * Resolve an authorised adult, creating the Moodle account if it does not exist and enough
     * detail is supplied (email + first + last name). Captures phone numbers either way. This is
     * the building block for bulk "add parents and associate them with their children".
     *
     * @param object|array $data Accepts adult* / guardian* lookup keys plus adultfirstname,
     *                           adultlastname, adultemail, adultusername, adultphone1, adultphone2, password.
     * @param int $createdby
     * @return int Adult user id.
     */
    public static function provision_adult(object|array $data, int $createdby): int {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/user/lib.php');

        $phone1 = (string)self::value($data, 'adultphone1', self::value($data, 'phone1', ''));
        $phone2 = (string)self::value($data, 'adultphone2', self::value($data, 'phone2', ''));

        // Resolve an existing adult first (by id / username / idnumber / email).
        $id = self::resolve_user_id($data, 'adultid', 'adultidnumber');
        if (!$id) {
            $id = self::resolve_user_id($data, 'guardianid', 'guardianidnumber');
        }
        $email = clean_param((string)self::value($data, 'adultemail', self::value($data, 'email', '')), PARAM_EMAIL);
        if (!$id && $email !== '') {
            $existing = $DB->get_record('user', ['email' => $email, 'deleted' => 0], 'id', IGNORE_MULTIPLE);
            $id = $existing ? (int)$existing->id : 0;
        }
        if ($id > 0) {
            self::update_adult_phone($id, $phone1, $phone2);
            return $id;
        }

        // Create the adult account.
        $first = clean_param((string)self::value($data, 'adultfirstname', self::value($data, 'firstname', '')), PARAM_TEXT);
        $last = clean_param((string)self::value($data, 'adultlastname', self::value($data, 'lastname', '')), PARAM_TEXT);
        if ($email === '' || $first === '' || $last === '') {
            throw new \invalid_parameter_exception(
                'Cannot create adult: adultemail, adultfirstname and adultlastname are required.'
            );
        }
        $user = new \stdClass();
        $user->auth = 'manual';
        $user->confirmed = 1;
        $user->mnethostid = $CFG->mnet_localhost_id;
        $user->username = clean_param((string)self::value($data, 'adultusername', $email), PARAM_USERNAME);
        $user->firstname = $first;
        $user->lastname = $last;
        $user->email = $email;
        $user->phone1 = clean_param($phone1, PARAM_NOTAGS);
        $user->phone2 = clean_param($phone2, PARAM_NOTAGS);
        $password = (string)self::value($data, 'password', '');
        if ($password !== '') {
            $user->password = $password;
        }
        $newid = user_create_user($user, $password !== '', false);
        // Force a password change on first login when requested, or whenever no password was supplied.
        $forcechange = (int)self::value($data, 'forcepasswordchange', $password === '' ? 1 : 0);
        if ($forcechange) {
            set_user_preference('auth_forcepasswordchange', 1, $newid);
        }
        self::log_access($createdby, 0, 'adult_provisioned', 0, 'user', (int)$newid, ['email' => $email]);
        return (int)$newid;
    }

    /**
     * Add or update a relationship from form/API payload.
     *
     * @param object|array $data
     * @param int $createdby
     * @param bool $fromapi
     * @return int
     */
    public static function add_or_update_relationship(object|array $data, int $createdby, bool $fromapi = false): int {
        global $DB;
        $adultid = self::resolve_user_id($data, 'adultid', 'adultidnumber');
        if (!$adultid) {
            $adultid = self::resolve_user_id($data, 'guardianid', 'guardianidnumber');
        }
        $learnerid = self::resolve_user_id($data, 'learnerid', 'learneridnumber');
        if (!$learnerid) {
            $learnerid = self::resolve_user_id($data, 'childid', 'childidnumber');
        }
        if ($adultid <= 0 || $learnerid <= 0 || $adultid === $learnerid) {
            throw new \invalid_parameter_exception('Invalid adult/learner user mapping.');
        }

        // Facilitate parent phone collection: update the adult's phone if supplied with the mapping.
        self::update_adult_phone(
            $adultid,
            (string)self::value($data, 'adultphone1', self::value($data, 'phone1', '')),
            (string)self::value($data, 'adultphone2', self::value($data, 'phone2', ''))
        );

        $now = time();
        $reltype = clean_param((string)self::value(
            $data,
            'reltype',
            self::value($data, 'reltypecode', 'legal_parent')
        ), PARAM_ALPHANUMEXT);
        $typedata = self::get_relationship_type_record($reltype);
        $relcategory = clean_param((string)self::value(
            $data,
            'relcategory',
            $typedata ? $typedata->category : 'family'
        ), PARAM_ALPHANUMEXT);
        $status = clean_param((string)self::value($data, 'status', self::STATUS_PENDING), PARAM_ALPHANUMEXT);
        $externalid = clean_param((string)self::value($data, 'externalid', ''), PARAM_TEXT);
        $sourcecode = clean_param((string)self::value($data, 'sourcecode', ''), PARAM_ALPHANUMEXT);
        $existing = false;

        // Editing a specific relationship by id takes precedence (the admin "Edit" action).
        $relationshipidparam = (int)self::value($data, 'relationshipid', 0);
        if ($relationshipidparam > 0) {
            $existing = $DB->get_record('tool_guardianlink_rel', ['id' => $relationshipidparam]);
        }
        if (!$existing && $sourcecode !== '' && $externalid !== '') {
            $existing = $DB->get_record('tool_guardianlink_rel', ['sourcecode' => $sourcecode, 'externalid' => $externalid]);
        }
        if (!$existing) {
            $existing = $DB->get_record(
                'tool_guardianlink_rel',
                ['guardianid' => $adultid, 'childid' => $learnerid, 'reltype' => $reltype, 'status' => self::STATUS_ACTIVE]
            );
        }

        $record = (object)[
            'guardianid' => $adultid,
            'childid' => $learnerid,
            'reltypeid' => $typedata ? (int)$typedata->id : 0,
            'reltype' => $reltype,
            'relcategory' => $relcategory,
            'legal' => empty(self::value($data, 'legal', 0)) ? 0 : 1,
            'authoritybasis' => clean_param((string)self::value($data, 'authoritybasis', 'school_record'), PARAM_ALPHANUMEXT),
            'authoritystatus' => clean_param((string)self::value($data, 'authoritystatus', 'verified'), PARAM_ALPHANUMEXT),
            'confidentiality' => clean_param((string)self::value($data, 'confidentiality', 'standard'), PARAM_ALPHANUMEXT),
            'householdkey' => clean_param((string)self::value($data, 'householdkey', ''), PARAM_TEXT),
            'contactgroupkey' => clean_param((string)self::value($data, 'contactgroupkey', ''), PARAM_TEXT),
            'status' => $status,
            'consentstatus' => clean_param((string)self::value($data, 'consentstatus', 'not_required'), PARAM_ALPHANUMEXT),
            'createdby' => $existing ? (int)$existing->createdby : $createdby,
            'approvedby' => $status === self::STATUS_ACTIVE ? $createdby : (int)($existing->approvedby ?? 0),
            'starttime' => (int)self::value($data, 'starttime', 0),
            'endtime' => (int)self::value($data, 'endtime', 0),
            'reviewtime' => (int)self::value($data, 'reviewtime', 0),
            'lastsynced' => $fromapi ? $now : (int)($existing->lastsynced ?? 0),
            // Empty external refs are stored NULL so the unique (sourcecode, externalid) index does not
            // collide across the many manual relationships that have no external source identity.
            'sourcecode' => $sourcecode !== '' ? $sourcecode : null,
            'externalid' => $externalid !== '' ? $externalid : null,
            'sourcerevision' => clean_param((string)self::value($data, 'sourcerevision', ''), PARAM_TEXT),
            'tenantkey' => clean_param((string)self::value($data, 'tenantkey', ''), PARAM_ALPHANUMEXT),
            'restrictionsjson' => self::normalise_json(
                self::value($data, 'restrictions', self::value($data, 'restrictionsjson', ''))
            ),
            'rightsjson' => self::normalise_json(self::value($data, 'rights', self::value($data, 'rightsjson', ''))),
            'notes' => clean_param((string)self::value($data, 'notes', ''), PARAM_TEXT),
            'timecreated' => $existing ? (int)$existing->timecreated : $now,
            'timemodified' => $now,
        ];

        $isnew = empty($existing);
        $profile = clean_param((string)self::value(
            $data,
            'accessprofile',
            $typedata ? $typedata->defaultprofile : 'family_basic'
        ), PARAM_ALPHANUMEXT);
        // Replace mode (the incoming scope set replaces previous active scopes) defaults ON for
        // authoritative syncs (ERP/SIS/web services pass $fromapi), and OFF for manual edits unless
        // the caller explicitly opts in with 'replacescopes'. This keeps additive behaviour for the
        // course-scoped registry, which must not touch other courses' scopes.
        $replacescopes = (bool)self::value($data, 'replacescopes', $fromapi);

        // Atomic write: the relationship row, its scopes, the external-identity map, the review-time
        // schedule and the audit entry commit together or not at all. An invalid scope payload
        // (rejected by set_scopes' validation) therefore rolls the whole upsert back, never leaving a
        // relationship persisted with partial or unvalidated scope state.
        $transaction = $DB->start_delegated_transaction();
        try {
            if ($existing) {
                $record->id = (int)$existing->id;
                $DB->update_record('tool_guardianlink_rel', $record);
                $relationshipid = (int)$record->id;
            } else {
                $relationshipid = (int)$DB->insert_record('tool_guardianlink_rel', $record);
            }

            if (self::value($data, 'scopes', null) !== null) {
                self::set_scopes(
                    $relationshipid,
                    (array)self::value($data, 'scopes', []),
                    $createdby,
                    $profile,
                    $sourcecode,
                    $replacescopes
                );
            } else {
                self::set_scopes_from_csv($relationshipid, $data, $createdby, $profile, $sourcecode, $replacescopes);
            }

            if ($sourcecode !== '' && $externalid !== '') {
                self::upsert_external_map('relationship', $sourcecode, $externalid, ['relationshipid' => $relationshipid]);
            }

            // Schedule the first re-validation review if none was supplied and the cycle is enabled.
            if ($isnew && (int)$record->reviewtime === 0 && $status === self::STATUS_ACTIVE) {
                $next = self::next_review_time();
                if ($next > 0) {
                    $DB->set_field('tool_guardianlink_rel', 'reviewtime', $next, ['id' => $relationshipid]);
                }
            }

            self::log_access(
                $createdby,
                $learnerid,
                $isnew ? 'relationship_created' : 'relationship_updated',
                0,
                'relationship',
                $relationshipid,
                ['sourcecode' => $sourcecode],
                'success',
                $sourcecode
            );

            $transaction->allow_commit();
        } catch (\Exception $e) {
            // The rollback() call re-throws $e after discarding the partial writes.
            $transaction->rollback($e);
        }

        // Post-commit side effects (Moodle events and role assignments must not run on a rolled-back
        // write, so they live outside the transaction).
        if ($isnew) {
            self::trigger_event(
                'relationship_created',
                \context_user::instance($learnerid),
                $learnerid,
                $relationshipid,
                ['reltype' => $reltype]
            );
        }
        // Role provisioning follows the grant's LIVE state. A grant only conveys access when it is
        // active AND verified; for any other state (revoked, restricted, disputed, expired, pending,
        // unverified) strip all standing role grants immediately rather than waiting for cleanup.
        if ($status === self::STATUS_ACTIVE && $record->authoritystatus === self::AUTHORITY_VERIFIED) {
            setup::maybe_sync_role($adultid, $learnerid, true);
        } else {
            setup::strip_all_grants($adultid, $learnerid);
        }
        return $relationshipid;
    }

    /**
     * Original method kept for compatibility with v0.1 admin page.
     *
     * @param object $data
     * @param int $createdby
     * @return int
     */
    public static function add_relationship(object $data, int $createdby): int {
        return self::add_or_update_relationship($data, $createdby, false);
    }

    /**
     * Resolve a single course reference (from CSV or a form field) to a Moodle course id.
     *
     * Accepts, in order of preference: a numeric course id, a course shortname, or a course
     * idnumber. This lets integrators and bulk uploaders use stable human-readable keys instead
     * of internal database ids, which are easy to mistype and differ between sites.
     *
     * @param string $ref Raw reference: course id, shortname, or idnumber.
     * @return int Course id, or 0 if it does not resolve to an existing course.
     */
    public static function resolve_course_ref(string $ref): int {
        global $DB;
        $ref = trim($ref);
        if ($ref === '') {
            return 0;
        }
        if (ctype_digit($ref) && $DB->record_exists('course', ['id' => (int)$ref])) {
            return (int)$ref;
        }
        if ($id = $DB->get_field('course', 'id', ['shortname' => $ref], IGNORE_MULTIPLE)) {
            return (int)$id;
        }
        if ($id = $DB->get_field('course', 'id', ['idnumber' => $ref], IGNORE_MULTIPLE)) {
            return (int)$id;
        }
        return 0;
    }

    /**
     * Build course scopes from form CSV fields.
     *
     * @param int $relationshipid
     * @param object|array $data
     * @param int $createdby
     * @param string $profile
     * @param string $sourcecode
     * @param bool $replace Replace mode: revoke previously-active scopes missing from this set.
     */
    public static function set_scopes_from_csv(
        int $relationshipid,
        object|array $data,
        int $createdby = 0,
        string $profile = 'family_basic',
        string $sourcecode = '',
        bool $replace = false
    ): void {
        $scopes = [];
        foreach (explode(',', (string)self::value($data, 'courseids', '')) as $rawid) {
            $id = self::resolve_course_ref((string)$rawid);
            if ($id > 0) {
                $scopes[] = ['scopekind' => 'course', 'courseid' => $id];
            }
        }
        foreach (explode(',', (string)self::value($data, 'categoryids', '')) as $rawid) {
            $id = (int)trim($rawid);
            if ($id > 0) {
                $scopes[] = ['scopekind' => 'category', 'categoryid' => $id];
            }
        }
        if (empty($scopes) && (int)self::value($data, 'allowsitelearneronly', 0) === 1) {
            $scopes[] = ['scopekind' => 'learner', 'courseid' => 0, 'categoryid' => 0];
        }
        foreach ($scopes as $key => $scope) {
            foreach (array_keys(self::access_profiles()['family_basic']) as $permission) {
                if (self::value($data, $permission, null) !== null) {
                    $scope[$permission] = empty(self::value($data, $permission, 0)) ? 0 : 1;
                }
            }
            $scopes[$key] = $scope;
        }
        self::set_scopes($relationshipid, $scopes, $createdby, $profile, $sourcecode, $replace);
    }

    /**
     * Upsert granular permission scopes.
     *
     * @param int $relationshipid
     * @param array $scopes
     * @param int $createdby
     * @param string $profile
     * @param string $sourcecode
     * @param bool $replace Replace mode: revoke previously-active scopes missing from this set.
     */
    public static function set_scopes(
        int $relationshipid,
        array $scopes,
        int $createdby = 0,
        string $profile = 'family_basic',
        string $sourcecode = '',
        bool $replace = false
    ): void {
        global $DB;
        $profiledefaults = self::access_profiles()[$profile] ?? self::access_profiles()['family_basic'];
        $now = time();
        // Validate the ENTIRE incoming set before writing anything: scopekind must be one of the known
        // kinds. Combined with the delegated transaction in add_or_update_relationship(), an invalid
        // scope payload aborts the whole upsert rather than leaving partially-written scope state.
        $validkinds = ['course', 'category', 'learner', 'site'];
        foreach ($scopes as $scope) {
            $s = is_array($scope) ? $scope : (array)$scope;
            $kind = clean_param(
                (string)($s['scopekind'] ?? ((int)($s['categoryid'] ?? 0) > 0 ? 'category' : 'course')),
                PARAM_ALPHANUMEXT
            );
            if (!in_array($kind, $validkinds, true)) {
                throw new \invalid_parameter_exception('Invalid GuardianLink scope kind: ' . $kind);
            }
        }
        // In replace mode we record which scope identities the incoming set covers, then revoke any
        // previously-active scope that is missing — so narrowing a parent from five courses to one
        // (e.g. from an ERP/SIS sync) actually removes the other four.
        $seenkeys = [];
        foreach ($scopes as $scope) {
            if (!is_array($scope)) {
                $scope = (array)$scope;
            }
            $courseid = (int)($scope['courseid'] ?? 0);
            $categoryid = (int)($scope['categoryid'] ?? 0);
            $scopekind = clean_param((string)($scope['scopekind'] ?? ($categoryid > 0 ? 'category' : 'course')), PARAM_ALPHANUMEXT);
            $seenkeys[$scopekind . ':' . $courseid . ':' . $categoryid] = true;
            if ($scopekind === 'course' && $courseid > 0 && !$DB->record_exists('course', ['id' => $courseid])) {
                continue;
            }
            if ($scopekind === 'category' && $categoryid > 0 && !$DB->record_exists('course_categories', ['id' => $categoryid])) {
                continue;
            }
            $existing = $DB->get_record(
                'tool_guardianlink_scope',
                ['relationshipid' => $relationshipid, 'scopekind' => $scopekind,
                'courseid' => $courseid,
                'categoryid' => $categoryid]
            );
            $record = (object)[
                'relationshipid' => $relationshipid,
                'scopekind' => $scopekind,
                'courseid' => $courseid,
                'categoryid' => $categoryid,
                'status' => clean_param((string)($scope['status'] ?? self::STATUS_ACTIVE), PARAM_ALPHANUMEXT),
                'starttime' => (int)($scope['starttime'] ?? 0),
                'endtime' => (int)($scope['endtime'] ?? 0),
                'createdby' => $existing ? (int)$existing->createdby : $createdby,
                'sourcecode' => clean_param((string)($scope['sourcecode'] ?? $sourcecode), PARAM_ALPHANUMEXT),
                'externalid' => clean_param((string)($scope['externalid'] ?? ''), PARAM_TEXT),
                'policyjson' => self::normalise_json($scope['policy'] ?? ($scope['policyjson'] ?? '')),
                'timecreated' => $existing ? (int)$existing->timecreated : $now,
                'timemodified' => $now,
            ];
            foreach ($profiledefaults as $permission => $default) {
                $record->{$permission} = empty($scope[$permission]) ? (int)$default : 1;
                if (array_key_exists($permission, $scope)) {
                    $record->{$permission} = empty($scope[$permission]) ? 0 : 1;
                }
            }
            if ($existing) {
                $record->id = (int)$existing->id;
                $DB->update_record('tool_guardianlink_scope', $record);
            } else {
                $DB->insert_record('tool_guardianlink_scope', $record);
            }
        }
        if ($replace) {
            // Revoke any still-active scope of this relationship that the incoming set did not include.
            $existing = $DB->get_records(
                'tool_guardianlink_scope',
                ['relationshipid' => $relationshipid, 'status' => self::STATUS_ACTIVE]
            );
            foreach ($existing as $scope) {
                $key = $scope->scopekind . ':' . (int)$scope->courseid . ':' . (int)$scope->categoryid;
                if (!isset($seenkeys[$key])) {
                    $scope->status = self::STATUS_REVOKED;
                    $scope->timemodified = $now;
                    $DB->update_record('tool_guardianlink_scope', $scope);
                }
            }
        }
    }

    /**
     * Return active learners for an authorised adult.
     *
     * @param int $adultid
     * @return array
     */
    public static function get_children_for_guardian(int $adultid): array {
        global $DB;
        $now = time();
        $sql = "SELECT r.id AS relationshipid, r.guardianid, r.childid, r.reltype, r.relcategory, r.status, r.legal,
                       r.authoritybasis, r.authoritystatus, r.confidentiality, r.endtime,
                       u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                       u.middlename, u.alternatename, u.email, u.idnumber, u.picture, u.imagealt
                  FROM {tool_guardianlink_rel} r
                  JOIN {user} u ON u.id = r.childid
                 WHERE r.guardianid = :adultid
                   AND r.status = :status
                   AND r.authoritystatus = :verified
                   AND (r.starttime = 0 OR r.starttime <= :now1)
                   AND (r.endtime = 0 OR r.endtime >= :now2)
                   AND u.deleted = 0
              ORDER BY u.lastname, u.firstname";
        return $DB->get_records_sql($sql, [
            'adultid' => $adultid,
            'status' => self::STATUS_ACTIVE,
            'verified' => self::AUTHORITY_VERIFIED,
            'now1' => $now,
            'now2' => $now,
        ]);
    }

    /**
     * Alias using neutral vocabulary.
     *
     * @param int $adultid
     * @return array
     */
    public static function get_learners_for_adult(int $adultid): array {
        return self::get_children_for_guardian($adultid);
    }

    /**
     * Fetch active relationship between adult and learner.
     *
     * @param int $adultid
     * @param int $learnerid
     * @return object|null
     */
    public static function get_active_relationship(int $adultid, int $learnerid): ?object {
        global $DB;
        $now = time();
        // Adult-facing access invariant: only a VERIFIED, active, in-date relationship may convey
        // access or even surface in adult-facing lookups. Requiring verified (rather than just
        // excluding revoked/restricted/disputed) also denies active-but-unverified relationships,
        // which must not expose any learner data until an administrator verifies authority.
        $sql = "SELECT *
                  FROM {tool_guardianlink_rel}
                 WHERE guardianid = :adultid
                   AND childid = :learnerid
                   AND status = :status
                   AND authoritystatus = :verified
                   AND (starttime = 0 OR starttime <= :now1)
                   AND (endtime = 0 OR endtime >= :now2)
              ORDER BY legal DESC, endtime ASC, id DESC";
        $records = $DB->get_records_sql($sql, [
            'adultid' => $adultid,
            'learnerid' => $learnerid,
            'status' => self::STATUS_ACTIVE,
            'verified' => self::AUTHORITY_VERIFIED,
            'now1' => $now,
            'now2' => $now,
        ], 0, 1);
        return $records ? reset($records) : null;
    }

    /**
     * Return scopes for a relationship.
     *
     * @param int $relationshipid
     * @return array
     */
    public static function get_scopes(int $relationshipid): array {
        global $DB;
        return $DB->get_records(
            'tool_guardianlink_scope',
            ['relationshipid' => $relationshipid],
            'scopekind ASC, courseid ASC, categoryid ASC'
        );
    }

    /**
     * Active, in-date scopes for a relationship — the only scopes that may expose data.
     *
     * Unlike get_scopes(), this filters to status = active within the start/end window. Callers that
     * render adult-facing dashboards must use this (and re-check each feature via can_access_child),
     * so an expired, future, or revoked scope row can never influence what is shown.
     *
     * @param int $relationshipid
     * @return array
     */
    public static function get_active_scopes(int $relationshipid): array {
        global $DB;
        $now = time();
        return $DB->get_records_select(
            'tool_guardianlink_scope',
            'relationshipid = :relationshipid AND status = :status'
                . ' AND (starttime = 0 OR starttime <= :now1) AND (endtime = 0 OR endtime >= :now2)',
            [
                'relationshipid' => $relationshipid,
                'status' => self::STATUS_ACTIVE,
                'now1' => $now,
                'now2' => $now,
            ],
            'scopekind ASC, courseid ASC, categoryid ASC'
        );
    }

    /**
     * The learner's enrolled courses that an authorised adult may actually see (the classroom view).
     *
     * Expands ALL scope kinds into concrete course cards: a direct course scope, a category scope
     * (every enrolled course in that category), and learner/site scopes (every enrolled course) all
     * resolve here, because eligibility is decided per course by can_access_child(...,'overview').
     * This is the single source of truth for adult-facing course lists (dashboard, digests, mobile).
     *
     * @param int $adultid
     * @param int $learnerid
     * @return array Course records keyed by course id (id, shortname, fullname, visible, dates, category).
     */
    public static function visible_courses_for_adult(int $adultid, int $learnerid): array {
        global $CFG;
        require_once($CFG->libdir . '/enrollib.php');
        $out = [];
        if (!self::get_active_relationship($adultid, $learnerid)) {
            return $out;
        }
        $fields = 'id, shortname, fullname, visible, startdate, enddate, category';
        foreach (enrol_get_users_courses($learnerid, true, $fields) as $course) {
            if (self::can_access_child($adultid, $learnerid, (int)$course->id, 'overview')) {
                $out[(int)$course->id] = $course;
            }
        }
        return $out;
    }

    /**
     * Why an adult can see a course — the scope kind that grants access — for a plain-language reason.
     *
     * @param int $adultid
     * @param int $learnerid
     * @param int $courseid
     * @return string One of 'course', 'category', 'learner', 'site', or '' if none currently grants it.
     */
    public static function access_reason_for_course(int $adultid, int $learnerid, int $courseid): string {
        $rel = self::get_active_relationship($adultid, $learnerid);
        if (!$rel) {
            return '';
        }
        $coursecat = 0;
        foreach (self::get_active_scopes((int)$rel->id) as $scope) {
            if ($scope->scopekind === 'course' && (int)$scope->courseid === $courseid) {
                return 'course';
            }
            if (in_array($scope->scopekind, ['learner', 'site'], true)) {
                return $scope->scopekind;
            }
        }
        // Category scope (only relevant once we know the course's category).
        global $DB;
        $coursecat = (int)$DB->get_field('course', 'category', ['id' => $courseid]);
        foreach (self::get_active_scopes((int)$rel->id) as $scope) {
            if ($scope->scopekind === 'category' && (int)$scope->categoryid === $coursecat) {
                return 'category';
            }
        }
        return '';
    }

    /**
     * Whether a learner is actively enrolled in an existing course.
     *
     * Used to reject stale or guessed childid/courseid pairings before any per-course action
     * (teacher contact, results email, tutor-request scope, course-specific health record).
     *
     * @param int $learnerid
     * @param int $courseid
     * @return bool
     */
    public static function learner_enrolled_in_course(int $learnerid, int $courseid): bool {
        global $CFG;
        require_once($CFG->libdir . '/enrollib.php');
        if ($learnerid <= 0 || $courseid <= 0) {
            return false;
        }
        $context = \context_course::instance($courseid, IGNORE_MISSING);
        return $context && is_enrolled($context, $learnerid, '', true);
    }

    /**
     * Whether governed assisted access (Moodle "log in as") is available on this site.
     *
     * Assisted access is an EXPERIMENTAL, high-risk capability and is out of scope for the
     * MVP. It stays completely inert unless BOTH the organisation master switch
     * (enableassistedmode) AND the separate experimental-risk acknowledgement
     * (assistedexperimentalack) are set, so it can never be activated by a single accidental
     * toggle. Until a full security/safeguarding review is completed it must remain off.
     *
     * @return bool
     */
    public static function assisted_feature_enabled(): bool {
        return (bool)get_config('tool_guardianlink', 'enableassistedmode')
            && (bool)get_config('tool_guardianlink', 'assistedexperimentalack');
    }

    /**
     * Check whether an authorised adult can access learner/course data.
     *
     * @param int $adultid
     * @param int $learnerid
     * @param int $courseid
     * @param string $permission overview|grades|activities|completion|attendance|calendar|teachercontact|messaging|assisted|healthsummary|tutormanagement|policyconsent
     * @return bool
     */
    public static function can_access_child(
        int $adultid,
        int $learnerid,
        int $courseid = 0,
        string $permission = 'overview'
    ): bool {
        global $DB;
        if ($adultid <= 0 || $learnerid <= 0 || $adultid === $learnerid) {
            return false;
        }
        $relationship = self::get_active_relationship($adultid, $learnerid);
        if (!$relationship) {
            return false;
        }
        // Adult-facing access invariant (defence in depth; get_active_relationship already enforces
        // it): only a verified relationship conveys access. This also denies active-but-unverified
        // relationships and any future non-verified authority status.
        if ($relationship->authoritystatus !== self::AUTHORITY_VERIFIED) {
            return false;
        }
        if ($permission === 'assisted' && !self::assisted_feature_enabled()) {
            return false;
        }
        if ($permission === 'healthsummary' && !get_config('tool_guardianlink', 'enablehealthrecords')) {
            return false;
        }
        $field = self::permission_field($permission);
        if ($courseid <= 0) {
            // Learner-level access is intentionally conservative. Basic overview and calendar can be
            // shown for an active relationship, but sensitive learner-level privileges such as grades,
            // health summaries, tutor management, and policy consent must be backed by an explicit scope.
            if (in_array($permission, ['overview', 'calendar'], true)) {
                return true;
            }
            // Learner-level health is special-category data: a single course-specific health scope
            // must NEVER satisfy a learner-wide health check, so require a learner or site scope here.
            $requirelearnerscope = ($permission === 'healthsummary');
            return self::relationship_has_permission_scope((int)$relationship->id, $field, $requirelearnerscope);
        }
        $now = time();
        $params = [
            'relationshipid' => $relationship->id,
            'status' => self::STATUS_ACTIVE,
            'now1' => $now,
            'now2' => $now,
            'courseid' => $courseid,
        ];
        $sql = "SELECT s.*
                  FROM {tool_guardianlink_scope} s
                 WHERE s.relationshipid = :relationshipid
                   AND s.status = :status
                   AND (s.starttime = 0 OR s.starttime <= :now1)
                   AND (s.endtime = 0 OR s.endtime >= :now2)
                   AND (s.courseid = :courseid OR s.scopekind IN ('learner', 'site'))";
        $scopes = $DB->get_records_sql($sql, $params);
        if (!$scopes) {
            $course = $DB->get_record('course', ['id' => $courseid], 'id,category');
            if ($course) {
                // The category fallback must honour the same active + time-window rules as a direct
                // scope, otherwise an expired category grant would keep conveying access.
                $scopes = $DB->get_records_select(
                    'tool_guardianlink_scope',
                    'relationshipid = :relationshipid AND scopekind = :scopekind AND categoryid = :categoryid '
                        . 'AND status = :status AND (starttime = 0 OR starttime <= :now1) '
                        . 'AND (endtime = 0 OR endtime >= :now2)',
                    [
                        'relationshipid' => $relationship->id,
                        'scopekind' => 'category',
                        'categoryid' => $course->category,
                        'status' => self::STATUS_ACTIVE,
                        'now1' => $now,
                        'now2' => $now,
                    ]
                );
            }
        }
        foreach ($scopes as $scope) {
            if (!empty($scope->{$field})) {
                return true;
            }
        }
        return false;
    }

    /**
     * Return recent relationships for admin pages.
     *
     * @param int $limit
     * @return array
     */
    public static function get_recent_relationships(int $limit = 100): array {
        global $DB;
        return $DB->get_records('tool_guardianlink_rel', null, 'timemodified DESC, id DESC', '*', 0, $limit);
    }

    /**
     * Return admin counts for overview page.
     *
     * @return array
     */
    public static function get_admin_counts(): array {
        global $DB;
        return [
            'activegrants' => (int)$DB->count_records('tool_guardianlink_rel', ['status' => self::STATUS_ACTIVE]),
            'pendingrequests' => (int)$DB->count_records('tool_guardianlink_tutorreq', ['status' => self::STATUS_PENDING]),
            'healthsummaries' => (int)$DB->count_records('tool_guardianlink_health', ['status' => self::STATUS_ACTIVE]),
            'organisationscount' => (int)$DB->count_records('tool_guardianlink_org', ['status' => self::STATUS_ACTIVE]),
        ];
    }

    /**
     * Create a tutor/helper request.
     *
     * @param object|array $data
     * @param int $requesterid
     * @return int
     */
    public static function create_tutor_request(object|array $data, int $requesterid): int {
        global $DB;
        $tutorid = self::resolve_user_id($data, 'tutorid', 'tutoridnumber');
        $learnerid = self::resolve_user_id($data, 'learnerid', 'learneridnumber');
        if (!$learnerid) {
            $learnerid = self::resolve_user_id($data, 'childid', 'childidnumber');
        }
        if ($tutorid <= 0 || $learnerid <= 0 || $tutorid === $learnerid) {
            throw new \invalid_parameter_exception('Invalid tutor or learner user.');
        }
        if (!self::can_access_child($requesterid, $learnerid, 0, 'tutormanagement')) {
            $relationship = self::get_active_relationship($requesterid, $learnerid);
            if (!$relationship || !$relationship->legal) {
                throw new \required_capability_exception(
                    \context_system::instance(),
                    'tool/guardianlink:approvetutors',
                    'nopermissions',
                    ''
                );
            }
        }
        $now = time();
        // Validate requested courses before storing: the learner must be actively enrolled, and an
        // adult requester may only propose tutoring for courses already within their own scope. Staff
        // requesters (approvetutors, no relationship) are constrained to enrolment only.
        $requesterrel = self::get_active_relationship($requesterid, $learnerid);
        $courseids = [];
        foreach (explode(',', (string)self::value($data, 'courseids', '')) as $rawcourse) {
            $cid = self::resolve_course_ref((string)$rawcourse);
            if ($cid <= 0 || !self::learner_enrolled_in_course($learnerid, $cid)) {
                continue;
            }
            if ($requesterrel) {
                // An adult requester may only propose for courses within their scope AND only where the
                // course policy still permits adult proposals. (Staff via approvetutors are exempt.)
                if (!self::can_access_child($requesterid, $learnerid, $cid, 'overview')) {
                    continue;
                }
                if (!self::course_allows_parent_propose($cid)) {
                    continue;
                }
            }
            $courseids[$cid] = $cid;
        }
        $courseids = array_values($courseids);
        $courseidcsv = implode(',', $courseids);
        // Trickle-down cap: a parent's proposed end-time cannot exceed the course/admin maximum.
        $endtime = self::cap_grant_endtime((int)self::value($data, 'endtime', 0), $courseids);
        $record = (object)[
            'requesterid' => $requesterid,
            'tutorid' => $tutorid,
            'childid' => $learnerid,
            'relationshipid' => 0,
            'courseids' => $courseidcsv,
            'purpose' => clean_param((string)self::value($data, 'purpose', ''), PARAM_TEXT),
            'status' => self::STATUS_PENDING,
            'decisionnote' => '',
            'approvedby' => 0,
            'starttime' => (int)self::value($data, 'starttime', 0),
            'endtime' => $endtime,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $id = (int)$DB->insert_record('tool_guardianlink_tutorreq', $record);
        self::trigger_event('tutor_request_created', \context_user::instance($learnerid), $learnerid, $id, ['tutorid' => $tutorid]);
        self::log_access($requesterid, $learnerid, 'tutor_request_created', 0, 'tutor_request', $id);
        return $id;
    }

    /**
     * Approve tutor request by creating a scoped tutor relationship.
     *
     * @param int $requestid
     * @param int $approverid
     * @param string $decisionnote
     * @return int Relationship id.
     */
    public static function approve_tutor_request(int $requestid, int $approverid, string $decisionnote = ''): int {
        global $DB;
        $request = $DB->get_record('tool_guardianlink_tutorreq', ['id' => $requestid], '*', MUST_EXIST);
        $payload = (object)[
            'adultid' => (int)$request->tutorid,
            'learnerid' => (int)$request->childid,
            'reltype' => 'tutor',
            'relcategory' => 'education',
            'legal' => 0,
            'authoritybasis' => 'tutoring_request',
            'authoritystatus' => 'verified',
            'confidentiality' => 'standard',
            'status' => self::STATUS_ACTIVE,
            'accessprofile' => 'tutor_limited',
            'courseids' => $request->courseids,
            'starttime' => (int)$request->starttime,
            'endtime' => (int)$request->endtime,
            'notes' => $request->purpose,
        ];
        $relationshipid = self::add_or_update_relationship($payload, $approverid, false);
        $request->relationshipid = $relationshipid;
        $request->status = self::STATUS_ACTIVE;
        $request->approvedby = $approverid;
        $request->decisionnote = clean_param($decisionnote, PARAM_TEXT);
        $request->timemodified = time();
        $DB->update_record('tool_guardianlink_tutorreq', $request);
        self::log_access($approverid, (int)$request->childid, 'tutor_request_approved', 0, 'tutor_request', $requestid);
        return $relationshipid;
    }

    /**
     * Create or update an organisation.
     *
     * @param object|array $data
     * @param int $userid
     * @return int
     */
    public static function upsert_organisation(object|array $data, int $userid): int {
        global $DB;
        $sourcecode = clean_param((string)self::value($data, 'sourcecode', ''), PARAM_ALPHANUMEXT);
        $externalid = clean_param((string)self::value($data, 'externalid', ''), PARAM_TEXT);
        $existing = false;
        if ($sourcecode !== '' && $externalid !== '') {
            $existing = $DB->get_record('tool_guardianlink_org', ['sourcecode' => $sourcecode, 'externalid' => $externalid]);
        }
        $now = time();
        $record = (object)[
            'orgtype' => clean_param((string)self::value($data, 'orgtype', 'other'), PARAM_ALPHANUMEXT),
            'name' => clean_param((string)self::value($data, 'name', self::value($data, 'orgname', '')), PARAM_TEXT),
            'externalid' => $externalid,
            'sourcecode' => $sourcecode,
            'status' => clean_param((string)self::value($data, 'status', self::STATUS_ACTIVE), PARAM_ALPHANUMEXT),
            'contactsummary' => clean_param((string)self::value($data, 'contactsummary', ''), PARAM_TEXT),
            'notes' => clean_param((string)self::value($data, 'notes', ''), PARAM_TEXT),
            'timecreated' => $existing ? (int)$existing->timecreated : $now,
            'timemodified' => $now,
        ];
        if ($record->name === '') {
            throw new \invalid_parameter_exception('Organisation name is required.');
        }
        if ($existing) {
            $record->id = (int)$existing->id;
            $DB->update_record('tool_guardianlink_org', $record);
            $id = (int)$record->id;
        } else {
            $id = (int)$DB->insert_record('tool_guardianlink_org', $record);
        }
        if ($sourcecode !== '' && $externalid !== '') {
            self::upsert_external_map('organisation', $sourcecode, $externalid, ['orgid' => $id]);
        }
        self::log_access(
            $userid,
            0,
            'organisation_upserted',
            0,
            'organisation',
            $id,
            ['sourcecode' => $sourcecode],
            'success',
            $sourcecode
        );
        return $id;
    }

    /**
     * Create or update a restricted health/care summary.
     *
     * @param object|array $data
     * @param int $userid
     * @return int
     */
    public static function upsert_health_record(object|array $data, int $userid): int {
        global $DB;
        if (!get_config('tool_guardianlink', 'enablehealthrecords')) {
            throw new \moodle_exception('healthrecordsdisabled', 'tool_guardianlink');
        }
        $learnerid = self::resolve_user_id($data, 'learnerid', 'learneridnumber');
        if (!$learnerid) {
            $learnerid = self::resolve_user_id($data, 'childid', 'childidnumber');
        }
        if ($learnerid <= 0) {
            throw new \invalid_parameter_exception('Invalid learner user.');
        }
        $sourcecode = clean_param((string)self::value($data, 'sourcecode', ''), PARAM_ALPHANUMEXT);
        $externalid = clean_param((string)self::value($data, 'externalid', ''), PARAM_TEXT);
        $existing = false;
        if ($sourcecode !== '' && $externalid !== '') {
            $existing = $DB->get_record('tool_guardianlink_health', ['sourcecode' => $sourcecode, 'externalid' => $externalid]);
        }
        $status = clean_param((string)self::value(
            $data,
            'status',
            get_config('tool_guardianlink', 'requirehealthapproval') ? self::STATUS_PENDING : self::STATUS_ACTIVE
        ), PARAM_ALPHANUMEXT);
        $now = time();
        // A course-specific health record must reference an existing course the learner is enrolled in,
        // otherwise it would attach to an invalid/ambiguous course context.
        $recordcourseid = (int)self::value($data, 'courseid', 0);
        if ($recordcourseid > 0 && !self::learner_enrolled_in_course($learnerid, $recordcourseid)) {
            throw new \invalid_parameter_exception(
                'A course-specific health record requires the learner to be enrolled in that course.'
            );
        }
        $record = (object)[
            'childid' => $learnerid,
            'courseid' => $recordcourseid,
            'healthtype' => clean_param((string)self::value($data, 'healthtype', 'care_note'), PARAM_ALPHANUMEXT),
            'title' => clean_param((string)self::value($data, 'title', self::value($data, 'healthtitle', '')), PARAM_TEXT),
            'summary' => clean_param((string)self::value($data, 'summary', self::value($data, 'healthsummary', '')), PARAM_TEXT),
            'severity' => clean_param((string)self::value($data, 'severity', 'routine'), PARAM_ALPHANUMEXT),
            'visibility' => clean_param((string)self::value($data, 'visibility', 'legal_guardian'), PARAM_ALPHANUMEXT),
            'status' => $status,
            'createdby' => $existing ? (int)$existing->createdby : $userid,
            'approvedby' => $status === self::STATUS_ACTIVE ? $userid : (int)($existing->approvedby ?? 0),
            'starttime' => (int)self::value($data, 'starttime', 0),
            'endtime' => (int)self::value($data, 'endtime', 0),
            'reviewtime' => (int)self::value($data, 'reviewtime', 0),
            'sourcecode' => $sourcecode,
            'externalid' => $externalid,
            'timecreated' => $existing ? (int)$existing->timecreated : $now,
            'timemodified' => $now,
        ];
        if ($record->title === '') {
            throw new \invalid_parameter_exception('Health/care title is required.');
        }
        if ($existing) {
            $record->id = (int)$existing->id;
            $DB->update_record('tool_guardianlink_health', $record);
            $id = (int)$record->id;
        } else {
            $id = (int)$DB->insert_record('tool_guardianlink_health', $record);
        }
        if ($sourcecode !== '' && $externalid !== '') {
            self::upsert_external_map('health', $sourcecode, $externalid, ['healthid' => $id]);
        }
        self::log_access(
            $userid,
            $learnerid,
            'health_record_upserted',
            (int)$record->courseid,
            'health',
            $id,
            ['visibility' => $record->visibility],
            'success',
            $sourcecode
        );
        return $id;
    }

    /**
     * Get health/care records visible to an authorised adult.
     *
     * @param int $adultid
     * @param int $learnerid
     * @return array
     */
    public static function get_health_records_for_adult(int $adultid, int $learnerid): array {
        global $DB;
        // The adult must hold an active, non-restricted relationship to the learner.
        $relationship = self::get_active_relationship($adultid, $learnerid);
        if (!$relationship) {
            return [];
        }
        $now = time();
        $candidates = $DB->get_records_select(
            'tool_guardianlink_health',
            'childid = :learnerid AND status = :status AND (starttime = 0 OR starttime <= :now1) '
                . 'AND (endtime = 0 OR endtime >= :now2)',
            ['learnerid' => $learnerid, 'status' => self::STATUS_ACTIVE, 'now1' => $now, 'now2' => $now],
            'severity DESC, title ASC'
        );
        // Visibility is an allowlist: only these levels are ever shown to an adult. Anything else
        // (restricted_staff / safeguarding / unknown) stays staff-only and is never released.
        $adultvisible = ['legal_guardian', 'authorised_care', 'emergency_only'];
        $records = [];
        foreach ($candidates as $record) {
            if (!in_array($record->visibility, $adultvisible, true)) {
                continue;
            }
            // Records visible only to legal guardians require the adult to hold legal responsibility.
            if ($record->visibility === 'legal_guardian' && empty($relationship->legal)) {
                continue;
            }
            // Re-check the health-summary scope per record: course-scoped records need the scope on
            // that course; learner-level (courseid = 0) records need the learner-level health scope.
            $scopecourse = (int)$record->courseid;
            if (!self::can_access_child($adultid, $learnerid, $scopecourse, 'healthsummary')) {
                continue;
            }
            $records[$record->id] = $record;
        }
        self::log_access($adultid, $learnerid, 'health_records_viewed', 0, 'health', 0, ['count' => count($records)]);
        return $records;
    }

    /**
     * Get active adults who can receive teacher proxy messages.
     *
     * @param int $learnerid
     * @param int $courseid
     * @return array
     */
    public static function get_proxy_recipients(int $learnerid, int $courseid): array {
        global $DB;
        $now = time();
        // Shared scope eligibility (active scope time window + course/learner/site/category coverage),
        // so proxy and bulk messaging apply identical rules that match can_access_child().
        [$scopesql, $scopeparams] = self::messaging_scope_sql($courseid, 'pmsc');
        $sql = "SELECT r.id AS relationshipid, r.guardianid, r.childid, r.reltype, r.confidentiality,
                       u.id, u.firstname, u.lastname, u.email, u.mailformat, u.deleted, u.suspended
                  FROM {tool_guardianlink_rel} r
                  JOIN {tool_guardianlink_scope} s ON s.relationshipid = r.id
                  JOIN {user} u ON u.id = r.guardianid
                 WHERE r.childid = :learnerid
                   AND r.status = :rstatus
                   AND r.authoritystatus = :verified
                   AND s.status = :sstatus
                   AND s.allowteachercontact = 1
                   AND s.allowmessaging = 1
                   AND (r.starttime = 0 OR r.starttime <= :now1)
                   AND (r.endtime = 0 OR r.endtime >= :now2)
                   AND u.deleted = 0
                   AND u.suspended = 0
                   {$scopesql}
              ORDER BY r.legal DESC, r.reltype ASC";
        return $DB->get_records_sql($sql, [
            'learnerid' => $learnerid,
            'rstatus' => self::STATUS_ACTIVE,
            'verified' => self::AUTHORITY_VERIFIED,
            'sstatus' => self::STATUS_ACTIVE,
            'now1' => $now,
            'now2' => $now,
        ] + $scopeparams);
    }

    /**
     * Build the scope-eligibility SQL fragment shared by proxy and bulk messaging recipient queries.
     *
     * Constrains a {tool_guardianlink_scope} alias `s` to an active, in-date scope that covers the
     * target course directly, is learner/site-wide, or covers the course's category — matching the
     * coverage and time-window rules enforced by can_access_child(). Centralising this guarantees the
     * two recipient resolvers cannot drift apart (e.g. one honouring scope expiry and the other not).
     *
     * @param int $courseid Target course id, or 0 for no course constraint (audience-wide scopes).
     * @param string $prefix Unique named-parameter prefix.
     * @param int $categoryid When > 0 (and $courseid is 0), require the scope to intersect this
     *                        category: a learner/site scope, a category scope for this category, or
     *                        a course scope whose course belongs to this category.
     * @param bool $broadscopeonly When true (and no course/category target), require a learner/site
     *                             scope — used for cohort audiences, which are not a teaching context,
     *                             so a single course-specific scope must not satisfy a cohort-wide send.
     * @return array [string $sqlfragment, array $params]
     */
    public static function messaging_scope_sql(
        int $courseid,
        string $prefix = 'msc',
        int $categoryid = 0,
        bool $broadscopeonly = false
    ): array {
        global $DB;
        $now = time();
        $sql = " AND (s.starttime = 0 OR s.starttime <= :{$prefix}snow1)"
             . " AND (s.endtime = 0 OR s.endtime >= :{$prefix}snow2)";
        $params = ["{$prefix}snow1" => $now, "{$prefix}snow2" => $now];
        if ($courseid > 0) {
            $coursecat = (int)$DB->get_field('course', 'category', ['id' => $courseid]);
            $sql .= " AND (s.courseid = :{$prefix}cid"
                  . " OR s.scopekind IN ('learner', 'site')"
                  . " OR (s.scopekind = 'category' AND s.categoryid = :{$prefix}cat))";
            $params["{$prefix}cid"] = $courseid;
            $params["{$prefix}cat"] = $coursecat > 0 ? $coursecat : -1;
        } else if ($categoryid > 0) {
            // Category audience: the scope must actually intersect the target category, not merely
            // be any active scope for the learner. Prevents a Course-B scope receiving a Category-A send.
            $sql .= " AND (s.scopekind IN ('learner', 'site')"
                  . " OR (s.scopekind = 'category' AND s.categoryid = :{$prefix}tcat)"
                  . " OR (s.scopekind = 'course' AND EXISTS ("
                  . "       SELECT 1 FROM {course} {$prefix}c"
                  . "        WHERE {$prefix}c.id = s.courseid AND {$prefix}c.category = :{$prefix}tcat2)))";
            $params["{$prefix}tcat"] = $categoryid;
            $params["{$prefix}tcat2"] = $categoryid;
        } else if ($broadscopeonly) {
            $sql .= " AND s.scopekind IN ('learner', 'site')";
        }
        return [$sql, $params];
    }


    /**
     * Return an adult's digest preference for a learner.
     *
     * @param int $adultid
     * @param int $learnerid
     * @return object|null
     */
    public static function get_digest_preference(int $adultid, int $learnerid): ?object {
        global $DB;
        $record = $DB->get_record('tool_guardianlink_digestpref', ['guardianid' => $adultid, 'childid' => $learnerid]);
        return $record ?: null;
    }

    /**
     * Save digest preferences from the adult-facing child admin area.
     *
     * @param int $adultid
     * @param int $learnerid
     * @param object|array $data
     * @return int
     */
    public static function save_digest_preference(int $adultid, int $learnerid, object|array $data): int {
        global $DB;
        if (!self::can_access_child($adultid, $learnerid, 0, 'overview')) {
            throw new \moodle_exception('accessdenied', 'tool_guardianlink');
        }
        $existing = self::get_digest_preference($adultid, $learnerid);
        $frequency = clean_param((string)self::value($data, 'frequency', 'weekly'), PARAM_ALPHANUMEXT);
        if (!in_array($frequency, ['daily', 'weekly', 'fortnightly', 'monthly'], true)) {
            $frequency = 'weekly';
        }
        $enabled = (int)self::value($data, 'enabled', 1) === 1;
        $now = time();
        $record = (object)[
            'guardianid' => $adultid,
            'childid' => $learnerid,
            'frequency' => $frequency,
            'channels' => clean_param((string)self::value($data, 'channels', 'moodle'), PARAM_TEXT),
            'includegrades' => self::can_access_child($adultid, $learnerid, 0, 'grades')
                ? (empty(self::value($data, 'includegrades', 0)) ? 0 : 1) : 0,
            'includeoverdue' => empty(self::value($data, 'includeoverdue', 1)) ? 0 : 1,
            'includeattendance' => self::can_access_child($adultid, $learnerid, 0, 'attendance')
                ? (empty(self::value($data, 'includeattendance', 0)) ? 0 : 1) : 0,
            'includehealth' => self::can_access_child($adultid, $learnerid, 0, 'healthsummary')
                ? (empty(self::value($data, 'includehealth', 0)) ? 0 : 1) : 0,
            'status' => $enabled ? self::STATUS_ACTIVE : self::STATUS_SUSPENDED,
            'lastsent' => $existing ? (int)$existing->lastsent : 0,
            'nextsend' => $enabled ? (int)self::value($data, 'nextsend', ($existing ? (int)$existing->nextsend : $now)) : 0,
            'lockeduntil' => 0,
            'timecreated' => $existing ? (int)$existing->timecreated : $now,
            'timemodified' => $now,
        ];
        if ($existing) {
            $record->id = (int)$existing->id;
            $DB->update_record('tool_guardianlink_digestpref', $record);
            $id = (int)$existing->id;
        } else {
            $id = (int)$DB->insert_record('tool_guardianlink_digestpref', $record);
        }
        self::log_access(
            $adultid,
            $learnerid,
            'digest_preference_saved',
            0,
            'digestpref',
            $id,
            ['frequency' => $frequency, 'enabled' => $enabled]
        );
        return $id;
    }

    /**
     * Return active digest preferences ready for sending.
     *
     * @param int $limit
     * @return array
     */
    public static function get_due_digest_preferences(int $limit = 100): array {
        global $DB;
        $now = time();
        $sql = "SELECT p.*, r.id AS relationshipid
                  FROM {tool_guardianlink_digestpref} p
                  JOIN {tool_guardianlink_rel} r ON r.guardianid = p.guardianid AND r.childid = p.childid
                  JOIN {user} adult ON adult.id = p.guardianid
                  JOIN {user} child ON child.id = p.childid
                 WHERE p.status = :pstatus
                   AND r.status = :rstatus
                   AND r.authoritystatus = :verified
                   AND (p.nextsend = 0 OR p.nextsend <= :now1)
                   AND (p.lockeduntil = 0 OR p.lockeduntil < :now2)
                   AND (r.starttime = 0 OR r.starttime <= :now3)
                   AND (r.endtime = 0 OR r.endtime >= :now4)
                   AND adult.deleted = 0 AND adult.suspended = 0
                   AND child.deleted = 0 AND child.suspended = 0
              ORDER BY p.nextsend ASC, p.id ASC";
        return $DB->get_records_sql($sql, [
            'pstatus' => self::STATUS_ACTIVE,
            'rstatus' => self::STATUS_ACTIVE,
            'verified' => 'verified',
            'now1' => $now,
            'now2' => $now,
            'now3' => $now,
            'now4' => $now,
        ], 0, $limit);
    }

    /**
     * Mark a digest preference as sent and schedule its next send.
     *
     * @param object $preference
     */
    public static function mark_digest_sent(object $preference): void {
        global $DB;
        $now = time();
        $record = (object)[
            'id' => (int)$preference->id,
            'lastsent' => $now,
            'nextsend' => self::next_digest_time((string)$preference->frequency, $now),
            'lockeduntil' => 0,
            'timemodified' => $now,
        ];
        $DB->update_record('tool_guardianlink_digestpref', $record);
        self::log_access(
            (int)$preference->guardianid,
            (int)$preference->childid,
            'digest_sent',
            0,
            'digestpref',
            (int)$preference->id,
            ['frequency' => (string)$preference->frequency]
        );
    }

    /**
     * Return next digest timestamp.
     *
     * @param string $frequency
     * @param int $from
     * @return int
     */
    public static function next_digest_time(string $frequency, int $from = 0): int {
        $from = $from > 0 ? $from : time();
        return match ($frequency) {
            'daily' => $from + DAYSECS,
            'fortnightly' => $from + (14 * DAYSECS),
            'monthly' => $from + (30 * DAYSECS),
            default => $from + WEEKSECS,
        };
    }

    /**
     * Revoke relationship by external ID.
     *
     * @param string $sourcecode
     * @param string $externalid
     * @param int $userid
     * @param string $reason
     * @return bool
     */
    public static function revoke_relationship_by_externalid(
        string $sourcecode,
        string $externalid,
        int $userid,
        string $reason = ''
    ): bool {
        global $DB;
        $relationship = $DB->get_record('tool_guardianlink_rel', ['sourcecode' => $sourcecode, 'externalid' => $externalid]);
        if (!$relationship) {
            return false;
        }
        $relationship->status = self::STATUS_REVOKED;
        $relationship->authoritystatus = self::STATUS_REVOKED;
        $relationship->timemodified = time();
        $relationship->notes = trim((string)$relationship->notes . "\n" . clean_param($reason, PARAM_TEXT));
        $DB->update_record('tool_guardianlink_rel', $relationship);
        setup::strip_all_grants((int)$relationship->guardianid, (int)$relationship->childid);
        self::trigger_event(
            'relationship_revoked',
            \context_user::instance((int)$relationship->childid),
            (int)$relationship->childid,
            (int)$relationship->id,
            ['reason' => $reason]
        );
        self::log_access(
            $userid,
            (int)$relationship->childid,
            'relationship_revoked',
            0,
            'relationship',
            (int)$relationship->id,
            ['reason' => $reason],
            'success',
            $sourcecode
        );
        return true;
    }

    /**
     * Expire relationships and scopes whose end time has passed.
     *
     * @return int
     */
    public static function expire_due_grants(): int {
        global $DB;
        $now = time();
        // Capture the relationships about to expire so their standing grants can be stripped
        // immediately (rather than waiting for the assist-cleanup backstop).
        $expiring = $DB->get_records_select(
            'tool_guardianlink_rel',
            'status = :active AND endtime > 0 AND endtime < :now',
            ['active' => self::STATUS_ACTIVE, 'now' => $now],
            '',
            'id, guardianid, childid'
        );
        $DB->execute("UPDATE {tool_guardianlink_rel} SET status = :expired, timemodified = :now1 "
            . "WHERE status = :active AND endtime > 0 AND endtime < :now2", [
            'expired' => self::STATUS_EXPIRED,
            'now1' => $now,
            'active' => self::STATUS_ACTIVE,
            'now2' => $now,
        ]);
        $DB->execute("UPDATE {tool_guardianlink_scope} SET status = :expired, timemodified = :now1 "
            . "WHERE status = :active AND endtime > 0 AND endtime < :now2", [
            'expired' => self::STATUS_EXPIRED,
            'now1' => $now,
            'active' => self::STATUS_ACTIVE,
            'now2' => $now,
        ]);
        foreach ($expiring as $rel) {
            setup::strip_all_grants((int)$rel->guardianid, (int)$rel->childid);
        }
        return (int)$DB->count_records('tool_guardianlink_rel', ['status' => self::STATUS_EXPIRED]);
    }

    /**
     * Write an audit record.
     *
     * @param int $actorid
     * @param int $learnerid
     * @param string $action
     * @param int $courseid
     * @param string|null $targettype
     * @param int $targetid
     * @param array $other
     * @param string $result
     * @param string $sourcecode
     */
    public static function log_access(
        int $actorid,
        int $learnerid,
        string $action,
        int $courseid = 0,
        ?string $targettype = null,
        int $targetid = 0,
        array $other = [],
        string $result = 'success',
        string $sourcecode = ''
    ): void {
        global $DB;
        if (!$DB->get_manager()->table_exists('tool_guardianlink_accesslog')) {
            return;
        }
        $relationship = ($actorid > 0 && $learnerid > 0) ? self::get_active_relationship($actorid, $learnerid) : null;
        $record = (object)[
            'actorid' => $actorid,
            'childid' => $learnerid,
            'relationshipid' => $relationship ? (int)$relationship->id : 0,
            'courseid' => $courseid,
            'action' => clean_param($action, PARAM_ALPHANUMEXT),
            'targettype' => $targettype ? clean_param($targettype, PARAM_ALPHANUMEXT) : null,
            'targetid' => $targetid,
            'result' => clean_param($result, PARAM_ALPHANUMEXT),
            'ip' => getremoteaddr(null),
            'sourcecode' => clean_param($sourcecode, PARAM_ALPHANUMEXT),
            'otherjson' => empty($other) ? null : json_encode($other),
            'timecreated' => time(),
        ];
        $DB->insert_record('tool_guardianlink_accesslog', $record);
    }

    /**
     * Log ERP sync summary.
     *
     * @param string $sourcecode
     * @param string $entitytype
     * @param string $action
     * @param string $status
     * @param int $received
     * @param int $succeeded
     * @param int $failed
     * @param int $userid
     * @param string $message
     * @param string $payloadhash
     * @return int
     */
    public static function log_sync_event(
        string $sourcecode,
        string $entitytype,
        string $action,
        string $status,
        int $received,
        int $succeeded,
        int $failed,
        int $userid,
        string $message = '',
        string $payloadhash = ''
    ): int {
        global $DB;
        $now = time();
        $record = (object)[
            'sourcecode' => clean_param($sourcecode, PARAM_ALPHANUMEXT),
            'jobid' => uniqid('gl_', true),
            'entitytype' => clean_param($entitytype, PARAM_ALPHANUMEXT),
            'action' => clean_param($action, PARAM_ALPHANUMEXT),
            'status' => clean_param($status, PARAM_ALPHANUMEXT),
            'recordsreceived' => $received,
            'recordssucceeded' => $succeeded,
            'recordsfailed' => $failed,
            'userid' => $userid,
            'payloadhash' => clean_param($payloadhash, PARAM_TEXT),
            'message' => clean_param($message, PARAM_TEXT),
            'started' => $now,
            'finished' => $now,
        ];
        return (int)$DB->insert_record('tool_guardianlink_erpsync', $record);
    }

    /**
     * Purge audit records older than retention configuration.
     *
     * @return int
     */
    public static function purge_old_audit(): int {
        global $DB;
        $months = (int)get_config('tool_guardianlink', 'auditretentionmonths');
        if ($months <= 0) {
            return 0;
        }
        $cutoff = time() - ($months * 31 * DAYSECS);
        $count = (int)$DB->count_records_select('tool_guardianlink_accesslog', 'timecreated < :cutoff', ['cutoff' => $cutoff]);
        $DB->delete_records_select('tool_guardianlink_accesslog', 'timecreated < :cutoff', ['cutoff' => $cutoff]);
        return $count;
    }

    /**
     * Retrieve relationships for API reconciliation.
     *
     * @param array $filters
     * @param int $limit
     * @return array
     */
    public static function get_relationships(array $filters = [], int $limit = 100): array {
        global $DB;
        $conditions = [];
        $params = [];
        foreach (['sourcecode', 'externalid', 'status', 'guardianid', 'childid'] as $field) {
            if (isset($filters[$field]) && $filters[$field] !== '' && (string)$filters[$field] !== '0') {
                $conditions[] = "$field = :$field";
                $params[$field] = $filters[$field];
            }
        }
        $where = $conditions ? implode(' AND ', $conditions) : '1=1';
        return $DB->get_records_select('tool_guardianlink_rel', $where, $params, 'timemodified DESC, id DESC', '*', 0, $limit);
    }

    /**
     * Upsert external map row.
     *
     * @param string $entitytype
     * @param string $sourcecode
     * @param string $externalid
     * @param array $ids
     */
    public static function upsert_external_map(string $entitytype, string $sourcecode, string $externalid, array $ids): void {
        global $DB;
        if ($sourcecode === '' || $externalid === '' || !$DB->get_manager()->table_exists('tool_guardianlink_extmap')) {
            return;
        }
        $existing = $DB->get_record(
            'tool_guardianlink_extmap',
            ['sourcecode' => $sourcecode, 'entitytype' => $entitytype, 'externalid' => $externalid]
        );
        $now = time();
        $record = (object)[
            'entitytype' => clean_param($entitytype, PARAM_ALPHANUMEXT),
            'externalid' => clean_param($externalid, PARAM_TEXT),
            'sourcecode' => clean_param($sourcecode, PARAM_ALPHANUMEXT),
            'moodleuserid' => (int)($ids['moodleuserid'] ?? 0),
            'relationshipid' => (int)($ids['relationshipid'] ?? 0),
            'orgid' => (int)($ids['orgid'] ?? 0),
            'healthid' => (int)($ids['healthid'] ?? 0),
            'status' => self::STATUS_ACTIVE,
            'timecreated' => $existing ? (int)$existing->timecreated : $now,
            'timemodified' => $now,
        ];
        if ($existing) {
            $record->id = (int)$existing->id;
            $DB->update_record('tool_guardianlink_extmap', $record);
        } else {
            $DB->insert_record('tool_guardianlink_extmap', $record);
        }
    }

    /**
     * Return recent audit records.
     *
     * @param int $limit
     * @param int $since
     * @return array
     */
    public static function get_recent_audit(int $limit = 100, int $since = 0): array {
        global $DB;
        if ($since > 0) {
            return $DB->get_records_select(
                'tool_guardianlink_accesslog',
                'timecreated >= :since',
                ['since' => $since],
                'timecreated DESC, id DESC',
                '*',
                0,
                $limit
            );
        }
        return $DB->get_records('tool_guardianlink_accesslog', null, 'timecreated DESC, id DESC', '*', 0, $limit);
    }

    /**
     * Trigger a Moodle Events API event (standard logstore) for stronger logging.
     *
     * @param string $shortclass Event class short name in tool_guardianlink\event.
     * @param \context $context
     * @param int $relateduserid
     * @param int $objectid
     * @param array $other
     */
    public static function trigger_event(
        string $shortclass,
        \context $context,
        int $relateduserid = 0,
        int $objectid = 0,
        array $other = []
    ): void {
        $class = '\\tool_guardianlink\\event\\' . $shortclass;
        if (!class_exists($class)) {
            return;
        }
        $params = ['context' => $context, 'other' => $other];
        if ($relateduserid > 0) {
            $params['relateduserid'] = $relateduserid;
        }
        if ($objectid > 0) {
            $params['objectid'] = $objectid;
        }
        try {
            $class::create($params)->trigger();
        } catch (\Throwable $e) {
            debugging('GuardianLink event failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Global admin maximum grant duration in days (0 = no global cap configured).
     *
     * @return int
     */
    public static function global_max_grant_days(): int {
        $secs = (int)get_config('tool_guardianlink', 'maxdefaultdurationdays');
        return $secs > 0 ? (int)round($secs / DAYSECS) : 0;
    }

    /**
     * Global admin default grant duration in days (0 = none).
     *
     * @return int
     */
    public static function global_default_grant_days(): int {
        return (int)get_config('tool_guardianlink', 'defaultgrantdays');
    }

    /**
     * Per-course GuardianLink policy set by a teacher, or null if unset.
     *
     * @param int $courseid
     * @return object|null
     */
    public static function get_course_config(int $courseid): ?object {
        global $DB;
        if ($courseid <= 0) {
            return null;
        }
        $rec = $DB->get_record('tool_guardianlink_courseconfig', ['courseid' => $courseid]);
        return $rec ?: null;
    }

    /**
     * Whether teacher proxy messaging is permitted for a course (course policy gate).
     *
     * Courses with no explicit policy row default to ENABLED, preserving prior behaviour. Once a
     * teacher/manager saves the course policy, the stored allowteacherproxy flag is authoritative.
     * This is enforced in both pages and service methods so no caller can bypass the course policy.
     *
     * @param int $courseid
     * @return bool
     */
    public static function course_allows_teacher_proxy(int $courseid): bool {
        if ($courseid <= 0) {
            return false;
        }
        $cfg = self::get_course_config($courseid);
        // No explicit policy row → enabled by default (backwards compatible).
        return !$cfg || !empty($cfg->allowteacherproxy);
    }

    /**
     * Whether authorised adults may propose tutors/support contacts for a course (course policy gate).
     *
     * Courses with no explicit policy row default to ENABLED. Enforced in nomination and tutor-request
     * flows so a course that has disabled proposals cannot be bypassed.
     *
     * @param int $courseid 0 for non-course-scoped proposals (always allowed at this gate).
     * @return bool
     */
    public static function course_allows_parent_propose(int $courseid): bool {
        if ($courseid <= 0) {
            return true;
        }
        $cfg = self::get_course_config($courseid);
        return !$cfg || !empty($cfg->allowparentpropose);
    }

    /**
     * Save per-course policy (teacher-set). The course max is capped to the global admin max
     * so a teacher can only ever tighten, never widen, the admin limit (permissions trickle down).
     *
     * @param int $courseid
     * @param object|array $data
     * @param int $userid
     * @return int Record id.
     */
    public static function save_course_config(int $courseid, object|array $data, int $userid): int {
        global $DB;
        $globalmax = self::global_max_grant_days();
        $maxdays = (int)self::value($data, 'maxgrantdays', 0);
        if ($globalmax > 0 && $maxdays > $globalmax) {
            $maxdays = $globalmax; // Trickle-down: cannot exceed the global admin maximum.
        }
        $defaultdays = (int)self::value($data, 'defaultgrantdays', 0);
        if ($maxdays > 0 && $defaultdays > $maxdays) {
            $defaultdays = $maxdays;
        }
        $now = time();
        $existing = $DB->get_record('tool_guardianlink_courseconfig', ['courseid' => $courseid]);
        $record = (object)[
            'courseid' => $courseid,
            'maxgrantdays' => $maxdays,
            'defaultgrantdays' => $defaultdays,
            'allowparentpropose' => empty(self::value($data, 'allowparentpropose', 1)) ? 0 : 1,
            'allowteacherproxy' => empty(self::value($data, 'allowteacherproxy', 1)) ? 0 : 1,
            'allowassistedaccess' => empty(self::value($data, 'allowassistedaccess', 0)) ? 0 : 1,
            'allowindependentaccess' => empty(self::value(
                $data,
                'allowindependentaccess',
                $existing->allowindependentaccess ?? 0
            )) ? 0 : 1,
            'createdby' => $existing ? (int)$existing->createdby : $userid,
            'timecreated' => $existing ? (int)$existing->timecreated : $now,
            'timemodified' => $now,
        ];
        if ($existing) {
            $record->id = (int)$existing->id;
            $DB->update_record('tool_guardianlink_courseconfig', $record);
            return (int)$existing->id;
        }
        return (int)$DB->insert_record('tool_guardianlink_courseconfig', $record);
    }

    /**
     * Effective maximum grant duration in seconds for a course, cascading
     * Admin (global max) -> course (teacher max). Returns 0 when no cap is configured.
     *
     * @param int $courseid
     * @return int
     */
    public static function effective_max_grant_seconds(int $courseid = 0): int {
        $globaldays = self::global_max_grant_days();
        $cfg = self::get_course_config($courseid);
        $coursedays = ($cfg && (int)$cfg->maxgrantdays > 0) ? (int)$cfg->maxgrantdays : 0;
        if ($globaldays > 0 && $coursedays > 0) {
            $days = min($globaldays, $coursedays);
        } else {
            $days = $coursedays > 0 ? $coursedays : $globaldays;
        }
        return $days > 0 ? $days * DAYSECS : 0;
    }

    /**
     * Cap a proposed end-time to the effective maximum for one or more courses.
     * Parents/tutors can never exceed the course cap, which can never exceed the admin cap.
     *
     * @param int $endtime Proposed end time (0 = open-ended).
     * @param int[] $courseids Course ids the grant applies to.
     * @return int Capped end time (0 only when no cap is configured anywhere).
     */
    public static function cap_grant_endtime(int $endtime, array $courseids = []): int {
        $now = time();
        $tightest = 0; // Largest allowed duration in seconds; 0 means uncapped so far.
        $havecap = false;
        foreach (array_merge($courseids, [0]) as $cid) {
            $max = self::effective_max_grant_seconds((int)$cid);
            if ($max > 0) {
                $tightest = $havecap ? min($tightest, $max) : $max;
                $havecap = true;
            }
        }
        if (!$havecap) {
            return $endtime; // No cap configured anywhere.
        }
        $latest = $now + $tightest;
        if ($endtime <= 0) {
            return $latest;
        }
        return min($endtime, $latest);
    }

    /**
     * A parent/guardian nominates another authorised adult for a learner.
     * The grant is created as PENDING and unverified; the institution remains the gatekeeper.
     *
     * @param object|array $data Expects learnerid/childid, nominee (id/idnumber), reltype, courseids.
     * @param int $nominatorid
     * @return int New relationship id.
     */
    public static function nominate_guardian(object|array $data, int $nominatorid): int {
        $learnerid = self::resolve_user_id($data, 'learnerid', 'learneridnumber');
        if (!$learnerid) {
            $learnerid = self::resolve_user_id($data, 'childid', 'childidnumber');
        }
        $nomineeid = self::resolve_user_id($data, 'nomineeid', 'nomineeidnumber');
        if ($learnerid <= 0 || $nomineeid <= 0 || $nomineeid === $learnerid || $nomineeid === $nominatorid) {
            throw new \invalid_parameter_exception('Invalid nominee/learner mapping.');
        }
        // The nominator must hold an active relationship that may delegate (legal responsibility).
        $relationship = self::get_active_relationship($nominatorid, $learnerid);
        if (!$relationship || empty($relationship->legal)) {
            throw new \required_capability_exception(
                \context_system::instance(),
                'tool/guardianlink:maprelationships',
                'nopermissions',
                ''
            );
        }
        $courseids = array_filter(array_map('intval', explode(',', (string)self::value($data, 'courseids', ''))));
        $endtime = self::cap_grant_endtime((int)self::value($data, 'endtime', 0), $courseids);
        $reltype = clean_param((string)self::value($data, 'reltype', 'guardian'), PARAM_ALPHANUMEXT);
        $payload = (object)[
            'adultid' => $nomineeid,
            'learnerid' => $learnerid,
            'reltype' => $reltype,
            'legal' => 0,
            'authoritybasis' => 'parent_nomination',
            'authoritystatus' => 'unverified',
            'status' => self::STATUS_PENDING,
            'accessprofile' => 'family_basic',
            'courseids' => implode(',', $courseids),
            'endtime' => $endtime,
            'notes' => clean_param((string)self::value($data, 'purpose', ''), PARAM_TEXT),
        ];
        $relationshipid = self::add_or_update_relationship($payload, $nominatorid, false);
        self::trigger_event(
            'guardian_nominated',
            \context_user::instance($learnerid),
            $learnerid,
            $nomineeid,
            ['relationshipid' => $relationshipid, 'reltype' => $reltype]
        );
        self::log_access(
            $nominatorid,
            $learnerid,
            'guardian_nominated',
            0,
            'relationship',
            $relationshipid,
            ['nomineeid' => $nomineeid]
        );
        return $relationshipid;
    }

    /**
     * Decide whether a governed assisted (parent/caregiver co-login as learner) session may start.
     * Fail-closed: every gate must pass — organisation switch, course permission, an active
     * relationship with an explicit assisted scope in its time window, and (optionally) the learner
     * not already being online.
     *
     * @param int $adultid
     * @param int $childid
     * @param int $courseid
     * @return array ['allowed' => bool, 'reason' => string]
     */
    public static function assisted_access_status(int $adultid, int $childid, int $courseid): array {
        if (!self::assisted_feature_enabled()) {
            return ['allowed' => false, 'reason' => get_string('assistedreason_orgoff', 'tool_guardianlink')];
        }
        $cfg = self::get_course_config($courseid);
        if (!$cfg || empty($cfg->allowassistedaccess)) {
            return ['allowed' => false, 'reason' => get_string('assistedreason_courseoff', 'tool_guardianlink')];
        }
        // Active relationship + explicit allowassisted scope + time window (and org switch) are all
        // verified by can_access_child() for the 'assisted' permission.
        if (!self::can_access_child($adultid, $childid, $courseid, 'assisted')) {
            return ['allowed' => false, 'reason' => get_string('assistedreason_norel', 'tool_guardianlink')];
        }
        if (get_config('tool_guardianlink', 'blockassistedwhenlearneronline') && self::learner_has_active_session($childid)) {
            return ['allowed' => false, 'reason' => get_string('assistedreason_online', 'tool_guardianlink')];
        }
        // Honour Moodle's concurrent-login limit. Login-as bypasses core's auth-time enforcement
        // (apply_concurrent_login_limit runs only after complete_user_login), so we enforce it here:
        // refuse an assisted session if the learner is already at their configured session cap.
        global $CFG;
        $limit = (int)($CFG->limitconcurrentlogins ?? 0);
        if ($limit > 0 && self::learner_active_session_count($childid) >= $limit) {
            return ['allowed' => false, 'reason' => get_string('assistedreason_concurrent', 'tool_guardianlink', $limit)];
        }
        // Optional: require consent on behalf of the learner before assisted access.
        if (
            get_config('tool_guardianlink', 'requireconsentforassist')
                && !consent_service::all_consented($adultid, $childid)
        ) {
            return ['allowed' => false, 'reason' => get_string('assistedreason_consent', 'tool_guardianlink')];
        }
        return ['allowed' => true, 'reason' => ''];
    }

    /**
     * Set or clear restricted-contact (safeguarding) status on a relationship, with a logged reason
     * (dispute/evidence trail). A restricted relationship conveys no access and is excluded from all
     * messaging and audiences.
     *
     * @param int $relationshipid
     * @param bool $restrict
     * @param string $reason
     * @param int $userid
     * @return bool
     */
    public static function set_restricted(int $relationshipid, bool $restrict, string $reason, int $userid): bool {
        global $DB;
        $rel = $DB->get_record('tool_guardianlink_rel', ['id' => $relationshipid]);
        if (!$rel) {
            return false;
        }
        $rel->authoritystatus = $restrict ? 'restricted' : 'verified';
        $rel->confidentiality = $restrict ? 'safeguarding' : $rel->confidentiality;
        $rel->timemodified = time();
        $note = ($restrict ? '[RESTRICTED] ' : '[UNRESTRICTED] ') . userdate(time()) . ': ' . clean_param($reason, PARAM_TEXT);
        $rel->notes = trim((string)$rel->notes . "\n" . $note);
        $DB->update_record('tool_guardianlink_rel', $rel);
        // Strip every standing grant (auto-assigned role AND assisted course-views) immediately so a
        // restricted adult cannot assist or inspect; cleanup tasks are only a backstop. When the
        // restriction is lifted, restore the optional auto-role if the relationship is otherwise active.
        if ($restrict) {
            setup::strip_all_grants((int)$rel->guardianid, (int)$rel->childid);
        } else {
            setup::maybe_sync_role((int)$rel->guardianid, (int)$rel->childid, $rel->status === self::STATUS_ACTIVE);
        }
        self::log_access(
            $userid,
            (int)$rel->childid,
            $restrict ? 'relationship_restricted' : 'relationship_unrestricted',
            0,
            'relationship',
            $relationshipid,
            ['reason' => $reason]
        );
        return true;
    }

    /**
     * Compute the next re-validation review time from a base time, using the configured period/unit
     * (days, months or years). Returns 0 when re-validation is disabled (period <= 0).
     *
     * @param int $from
     * @return int
     */
    public static function next_review_time(int $from = 0): int {
        $from = $from > 0 ? $from : time();
        $period = (int)get_config('tool_guardianlink', 'revalidationperiod');
        $unit = (string)get_config('tool_guardianlink', 'revalidationunit');
        if ($period <= 0) {
            return 0;
        }
        if (!in_array($unit, ['days', 'months', 'years'], true)) {
            $unit = 'months';
        }
        return (int)strtotime("+{$period} {$unit}", $from);
    }

    /**
     * Re-validate a relationship: confirm it is still valid, push its review date forward by the
     * configured period, and record the validation note (custody/audit trail).
     *
     * @param int $relationshipid
     * @param int $userid
     * @param string $note
     * @return bool
     */
    public static function revalidate(int $relationshipid, int $userid, string $note = ''): bool {
        global $DB;
        $rel = $DB->get_record('tool_guardianlink_rel', ['id' => $relationshipid]);
        if (!$rel) {
            return false;
        }
        $rel->reviewtime = self::next_review_time();
        $rel->timemodified = time();
        $entry = '[RE-VALIDATED] ' . userdate(time()) . ': ' . clean_param($note, PARAM_TEXT);
        $rel->notes = trim((string)$rel->notes . "\n" . $entry);
        $DB->update_record('tool_guardianlink_rel', $rel);
        self::trigger_event(
            'relationship_revalidated',
            \context_user::instance((int)$rel->childid),
            (int)$rel->childid,
            $relationshipid,
            ['nextreview' => $rel->reviewtime]
        );
        self::log_access(
            $userid,
            (int)$rel->childid,
            'relationship_revalidated',
            0,
            'relationship',
            $relationshipid,
            ['note' => $note]
        );
        return true;
    }

    /**
     * Relationships whose re-validation is due or overdue (reviewtime set and within lead window).
     *
     * @param int $leaddays How many days ahead to include as "due soon".
     * @param int $limit
     * @return array
     */
    public static function get_revalidation_due(int $leaddays = 14, int $limit = 200): array {
        global $DB;
        $soon = time() + ($leaddays * DAYSECS);
        return $DB->get_records_select(
            'tool_guardianlink_rel',
            "status = :status AND reviewtime > 0 AND reviewtime <= :soon",
            ['status' => self::STATUS_ACTIVE, 'soon' => $soon],
            'reviewtime ASC',
            '*',
            0,
            $limit
        );
    }

    /**
     * Add a custody-chain entry (HTML validation note + proof file attachments) to a relationship.
     * Sensitive — pages calling this must restrict to admin-level users.
     *
     * @param int $relationshipid
     * @param int $userid
     * @param array $noteeditor Editor field array (text, format, itemid).
     * @param int $attachmentsdraft Draft item id of the attachments file manager.
     * @return int Proof entry id.
     */
    public static function add_proof(int $relationshipid, int $userid, array $noteeditor, int $attachmentsdraft): int {
        global $DB, $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $context = \context_system::instance();
        $id = (int)$DB->insert_record('tool_guardianlink_proof', (object)[
            'relationshipid' => $relationshipid,
            'note' => '',
            'noteformat' => (int)($noteeditor['format'] ?? FORMAT_HTML),
            'userid' => $userid,
            'timecreated' => time(),
        ]);
        // Save embedded images in the note, then store the rewritten HTML.
        $note = file_save_draft_area_files(
            (int)($noteeditor['itemid'] ?? 0),
            $context->id,
            'tool_guardianlink',
            'proofnote',
            $id,
            ['subdirs' => 0],
            (string)($noteeditor['text'] ?? '')
        );
        $DB->set_field('tool_guardianlink_proof', 'note', $note, ['id' => $id]);
        // Save the proof file attachments.
        if ($attachmentsdraft) {
            file_save_draft_area_files($attachmentsdraft, $context->id, 'tool_guardianlink', 'proof', $id, ['subdirs' => 0]);
        }
        $childid = (int)($DB->get_field('tool_guardianlink_rel', 'childid', ['id' => $relationshipid]) ?: 0);
        self::log_access($userid, $childid, 'proof_added', 0, 'proof', $id);
        return $id;
    }

    /**
     * Custody-chain entries for a relationship (newest first).
     *
     * @param int $relationshipid
     * @return array
     */
    public static function get_proofs(int $relationshipid): array {
        global $DB;
        return $DB->get_records('tool_guardianlink_proof', ['relationshipid' => $relationshipid], 'timecreated DESC');
    }

    /**
     * Proof file attachments for a custody entry.
     *
     * @param int $proofid
     * @return \stored_file[]
     */
    public static function get_proof_files(int $proofid): array {
        $fs = get_file_storage();
        $context = \context_system::instance();
        return $fs->get_area_files($context->id, 'tool_guardianlink', 'proof', $proofid, 'filename', false);
    }

    /**
     * Number of active Moodle sessions the learner currently holds.
     *
     * @param int $childid
     * @return int
     */
    public static function learner_active_session_count(int $childid): int {
        global $DB, $CFG;
        if (!$DB->get_manager()->table_exists('sessions')) {
            return 0;
        }
        $cutoff = time() - (int)($CFG->sessiontimeout ?? 7200);
        return $DB->count_records_select(
            'sessions',
            'userid = :uid AND timemodified > :cutoff',
            ['uid' => $childid, 'cutoff' => $cutoff]
        );
    }

    /**
     * Whether the learner currently has an active Moodle session (anti-simultaneous-access guard).
     *
     * @param int $childid
     * @return bool
     */
    public static function learner_has_active_session(int $childid): bool {
        return self::learner_active_session_count($childid) > 0;
    }

    /**
     * Maximum assisted-session duration in seconds (0 = no cap).
     *
     * @return int
     */
    public static function assisted_max_seconds(): int {
        $mins = (int)get_config('tool_guardianlink', 'assistedmaxminutes');
        return $mins > 0 ? $mins * MINSECS : 0;
    }

    /**
     * Determine permission database field.
     *
     * @param string $permission
     * @return string
     */
    private static function permission_field(string $permission): string {
        $map = [
            'overview' => 'allowoverview',
            'grades' => 'allowgrades',
            'completion' => 'allowcompletion',
            'activities' => 'allowactivities',
            'attendance' => 'allowattendance',
            'calendar' => 'allowcalendar',
            'teachercontact' => 'allowteachercontact',
            'messaging' => 'allowmessaging',
            'assisted' => 'allowassisted',
            'healthsummary' => 'allowhealthsummary',
            'tutormanagement' => 'allowtutormanagement',
            'policyconsent' => 'allowpolicyconsent',
        ];
        return $map[$permission] ?? 'allowoverview';
    }

    /**
     * Check whether any active learner/site/category/course scope explicitly allows a permission.
     *
     * @param int $relationshipid
     * @param string $field
     * @param bool $requirelearnerscope When true, only a learner/site scope satisfies (a course
     *                                  scope must not grant a learner-wide privilege such as health).
     * @return bool
     */
    private static function relationship_has_permission_scope(
        int $relationshipid,
        string $field,
        bool $requirelearnerscope = false
    ): bool {
        global $DB;
        $allowedfields = array_values(array_unique(array_map([self::class, 'permission_field'], [
            'overview', 'grades', 'completion', 'activities', 'attendance', 'calendar',
            'teachercontact', 'messaging', 'assisted', 'healthsummary', 'tutormanagement', 'policyconsent',
        ])));
        if (!in_array($field, $allowedfields, true)) {
            return false;
        }
        $now = time();
        $select = "relationshipid = :relationshipid AND status = :status AND {$field} = 1" .
            " AND (starttime = 0 OR starttime <= :now1)" .
            " AND (endtime = 0 OR endtime >= :now2)";
        if ($requirelearnerscope) {
            // A learner-wide privilege must be backed by a learner/site scope. A course-specific scope
            // only grants the permission for that one course and must NOT satisfy a learner-level check.
            $select .= " AND scopekind IN ('learner', 'site')";
        }
        return $DB->record_exists_select(
            'tool_guardianlink_scope',
            $select,
            ['relationshipid' => $relationshipid, 'status' => self::STATUS_ACTIVE, 'now1' => $now, 'now2' => $now]
        );
    }

    /**
     * Fetch relationship type record by shortname.
     *
     * @param string $shortname
     * @return object|null
     */
    private static function get_relationship_type_record(string $shortname): ?object {
        global $DB;
        if (!$DB->get_manager()->table_exists('tool_guardianlink_reltype')) {
            return null;
        }
        $record = $DB->get_record('tool_guardianlink_reltype', ['shortname' => $shortname]);
        return $record ?: null;
    }

    /**
     * Get array/object value.
     *
     * @param object|array $data
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    private static function value(object|array $data, string $key, mixed $default = null): mixed {
        if (is_array($data)) {
            return array_key_exists($key, $data) ? $data[$key] : $default;
        }
        return property_exists($data, $key) ? $data->{$key} : $default;
    }

    /**
     * Clean JSON input while accepting arrays.
     *
     * @param mixed $value
     * @return string|null
     */
    private static function normalise_json(mixed $value): ?string {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }
        $trimmed = trim((string)$value);
        if ($trimmed === '') {
            return null;
        }
        json_decode($trimmed);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $trimmed;
        }
        return json_encode(['text' => clean_param($trimmed, PARAM_TEXT)]);
    }
}

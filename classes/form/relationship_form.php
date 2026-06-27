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
 * Relationship form for GuardianLink.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guardianlink\form;

use tool_guardianlink\local\relationship_service;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Relationship mapping form using neutral authorised-adult/learner language.
 */
class relationship_form extends \moodleform {
    /**
     * Define fields.
     */
    public function definition(): void {
        $mform = $this->_form;

        $mform->addElement('header', 'identity', get_string('relationships', 'tool_guardianlink'));

        // Editing an existing relationship carries its id.
        $mform->addElement('hidden', 'relationshipid');
        $mform->setType('relationshipid', PARAM_INT);

        // Native user autocomplete — only existing Moodle users can be selected (ajax-filtered).
        $usercallback = function ($userid) {
            global $DB;
            if (empty($userid) || !is_numeric($userid)) {
                return false;
            }
            $user = $DB->get_record('user', ['id' => (int)$userid, 'deleted' => 0], '*', IGNORE_MISSING);
            return $user ? fullname($user) : false;
        };
        $useropts = ['ajax' => 'core_user/form_user_selector', 'multiple' => false, 'valuehtmlcallback' => $usercallback];
        $mform->addElement('autocomplete', 'adultid', get_string('adultid', 'tool_guardianlink'), [], $useropts);
        $mform->setType('adultid', PARAM_INT);
        $mform->addRule('adultid', null, 'required', null, 'client');
        $mform->addElement('autocomplete', 'learnerid', get_string('learnerid', 'tool_guardianlink'), [], $useropts);
        $mform->setType('learnerid', PARAM_INT);
        $mform->addRule('learnerid', null, 'required', null, 'client');

        // Optional parent phone capture (stored on the adult's core user record; never shown to teachers).
        $mform->addElement('text', 'adultphone1', get_string('adultphone1', 'tool_guardianlink'));
        $mform->setType('adultphone1', PARAM_NOTAGS);
        $mform->addElement('text', 'adultphone2', get_string('adultphone2', 'tool_guardianlink'));
        $mform->setType('adultphone2', PARAM_NOTAGS);

        $mform->addElement(
            'select',
            'reltype',
            get_string('reltype', 'tool_guardianlink'),
            relationship_service::get_relationship_type_options()
        );
        $mform->setDefault('reltype', 'legal_parent');

        $basis = [
            'school_record' => 'School record',
            'court_order' => 'Court order',
            'care_order' => 'Care order',
            'hostel_record' => 'Hostel/boarding record',
            'residential_record' => 'Residential care record',
            'guardian_consent' => 'Guardian consent',
            'tutoring_request' => 'Tutor/helper request',
            'contract' => 'Contract',
            'other' => 'Other',
        ];
        $mform->addElement('select', 'authoritybasis', get_string('authoritybasis', 'tool_guardianlink'), $basis);
        $mform->setDefault('authoritybasis', 'school_record');

        $authoritystatus = [
            'unverified' => 'Unverified',
            'verified' => 'Verified',
            'restricted' => 'Restricted',
            'disputed' => 'Disputed',
            'revoked' => 'Revoked',
        ];
        $mform->addElement('select', 'authoritystatus', get_string('authoritystatus', 'tool_guardianlink'), $authoritystatus);
        $mform->setDefault('authoritystatus', 'verified');

        $confidentiality = [
            'standard' => 'Standard',
            'restricted' => 'Restricted',
            'sensitive' => 'Sensitive',
            'safeguarding' => 'Safeguarding',
        ];
        $mform->addElement('select', 'confidentiality', get_string('confidentiality', 'tool_guardianlink'), $confidentiality);
        $mform->setDefault('confidentiality', 'standard');
        $mform->addElement('advcheckbox', 'legal', get_string('legal', 'tool_guardianlink'));
        $mform->setType('legal', PARAM_INT);

        $statuses = [
            relationship_service::STATUS_ACTIVE => get_string('status_active', 'tool_guardianlink'),
            relationship_service::STATUS_PENDING => get_string('status_pending', 'tool_guardianlink'),
            relationship_service::STATUS_SUSPENDED => get_string('status_suspended', 'tool_guardianlink'),
            relationship_service::STATUS_REVOKED => get_string('status_revoked', 'tool_guardianlink'),
        ];
        $mform->addElement('select', 'status', get_string('status', 'tool_guardianlink'), $statuses);
        $mform->setDefault('status', relationship_service::STATUS_ACTIVE);

        $mform->addElement('header', 'scope', get_string('accessprofile', 'tool_guardianlink'));
        $profiles = relationship_service::get_profile_options();
        $mform->addElement('select', 'accessprofile', get_string('accessprofile', 'tool_guardianlink'), $profiles);
        $mform->setDefault('accessprofile', 'family_basic');

        // Native Moodle course selector (autocomplete) — only valid courses can be chosen.
        $mform->addElement('course', 'courseids', get_string('courseids', 'tool_guardianlink'), ['multiple' => true]);
        $mform->addHelpButton('courseids', 'courseids', 'tool_guardianlink');
        // Native category selector — only valid categories can be chosen.
        $mform->addElement(
            'autocomplete',
            'categoryids',
            get_string('categoryids', 'tool_guardianlink'),
            \core_course_category::make_categories_list(),
            ['multiple' => true]
        );

        foreach (array_keys(relationship_service::access_profiles()['family_basic']) as $permission) {
            $mform->addElement('advcheckbox', $permission, get_string($permission, 'tool_guardianlink'));
            $mform->setType($permission, PARAM_INT);
        }
        $mform->setDefault('allowoverview', 1);
        $mform->setDefault('allowcompletion', 1);
        $mform->setDefault('allowactivities', 1);
        $mform->setDefault('allowcalendar', 1);
        $mform->setDefault('allowteachercontact', 1);
        $mform->setDefault('allowmessaging', 1);

        $mform->addElement('header', 'lifecycle', get_string('status', 'tool_guardianlink'));
        $mform->addElement('date_time_selector', 'starttime', get_string('starttime', 'tool_guardianlink'), ['optional' => true]);
        $mform->addElement('date_time_selector', 'endtime', get_string('endtime', 'tool_guardianlink'), ['optional' => true]);
        $mform->addElement('date_time_selector', 'reviewtime', get_string('reviewtime', 'tool_guardianlink'), ['optional' => true]);

        $mform->addElement('header', 'external', get_string('erpapi', 'tool_guardianlink'));
        $mform->addElement('text', 'sourcecode', get_string('sourcecode', 'tool_guardianlink'));
        $mform->setType('sourcecode', PARAM_ALPHANUMEXT);
        $mform->addElement('text', 'externalid', get_string('externalid', 'tool_guardianlink'));
        $mform->setType('externalid', PARAM_TEXT);
        $mform->addElement('text', 'householdkey', get_string('householdkey', 'tool_guardianlink'));
        $mform->setType('householdkey', PARAM_TEXT);
        $mform->addElement('text', 'contactgroupkey', get_string('contactgroupkey', 'tool_guardianlink'));
        $mform->setType('contactgroupkey', PARAM_TEXT);
        $mform->addElement('textarea', 'notes', get_string('notes', 'tool_guardianlink'), ['rows' => 3, 'cols' => 80]);
        $mform->setType('notes', PARAM_TEXT);

        $this->add_action_buttons(true, get_string('save', 'tool_guardianlink'));
    }

    /**
     * Validate user and scope identifiers.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        global $DB;
        $errors = parent::validation($data, $files);
        if (empty($data['adultid']) || !$DB->record_exists('user', ['id' => (int)$data['adultid'], 'deleted' => 0])) {
            $errors['adultid'] = get_string('invaliduser', 'tool_guardianlink');
        }
        if (empty($data['learnerid']) || !$DB->record_exists('user', ['id' => (int)$data['learnerid'], 'deleted' => 0])) {
            $errors['learnerid'] = get_string('invaliduser', 'tool_guardianlink');
        }
        if (!empty($data['adultid']) && !empty($data['learnerid']) && (int)$data['adultid'] === (int)$data['learnerid']) {
            $errors['learnerid'] = get_string('invalidrelationship', 'tool_guardianlink');
        }
        return $errors;
    }
}

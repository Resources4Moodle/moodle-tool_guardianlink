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
 * Health/care summary form for GuardianLink.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guardianlink\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Restricted health/care summary form.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class health_record_form extends \moodleform {
    /**
     * Define form fields.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;
        $mform->addElement('header', 'general', get_string('healthrecord', 'tool_guardianlink'));
        $mform->addElement('text', 'childid', get_string('learnerid', 'tool_guardianlink'));
        $mform->setType('childid', PARAM_INT);
        $mform->addRule('childid', null, 'required', null, 'client');
        $types = [
            'allergy' => 'Allergy',
            'medication' => 'Medication',
            'care_plan' => 'Care plan',
            'emergency' => 'Emergency',
            'wellbeing' => 'Wellbeing',
            'other' => 'Other',
        ];
        $mform->addElement('select', 'healthtype', get_string('healthtype', 'tool_guardianlink'), $types);
        $mform->addElement('text', 'title', get_string('healthtitle', 'tool_guardianlink'));
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');
        $mform->addElement('textarea', 'summary', get_string('healthsummary', 'tool_guardianlink'), ['rows' => 5, 'cols' => 80]);
        $mform->setType('summary', PARAM_TEXT);
        $severity = ['routine' => 'Routine', 'important' => 'Important', 'urgent' => 'Urgent', 'critical' => 'Critical'];
        $mform->addElement('select', 'severity', get_string('severity', 'tool_guardianlink'), $severity);
        $visibility = [
            'legal_guardian' => 'Legal guardians only',
            'authorised_care' => 'Authorised family/care adults',
            'emergency_only' => 'Emergency only',
            'restricted_staff' => 'Restricted staff only',
        ];
        $mform->addElement('select', 'visibility', get_string('visibility', 'tool_guardianlink'), $visibility);
        $mform->addElement('text', 'courseid', get_string('course', 'tool_guardianlink'));
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('date_time_selector', 'starttime', get_string('starttime', 'tool_guardianlink'), ['optional' => true]);
        $mform->addElement('date_time_selector', 'endtime', get_string('endtime', 'tool_guardianlink'), ['optional' => true]);
        $mform->addElement('date_time_selector', 'reviewtime', get_string('reviewtime', 'tool_guardianlink'), ['optional' => true]);
        $mform->addElement('text', 'sourcecode', get_string('sourcecode', 'tool_guardianlink'));
        $mform->setType('sourcecode', PARAM_ALPHANUMEXT);
        $mform->addElement('text', 'externalid', get_string('externalid', 'tool_guardianlink'));
        $mform->setType('externalid', PARAM_TEXT);
        $this->add_action_buttons(true, get_string('save', 'tool_guardianlink'));
    }
}

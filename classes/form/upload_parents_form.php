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
 * Admin CSV upload form for bulk parent/guardian provisioning.
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
 * Upload a CSV of parents/guardians to create Moodle accounts and bind relationships.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class upload_parents_form extends \moodleform {
    /**
     * Define form fields.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('uploadparents', 'tool_guardianlink'));
        $mform->addElement('static', 'help', '', get_string('uploadparents_help', 'tool_guardianlink'));

        $mform->addElement(
            'filepicker',
            'csvfile',
            get_string('uploadcsvfile', 'tool_guardianlink'),
            null,
            ['accepted_types' => ['.csv', '.txt']]
        );
        $mform->addRule('csvfile', null, 'required');

        // Phase-1 (Moodle account creation) options.
        $mform->addElement('header', 'accountoptions', get_string('uploadaccountoptions', 'tool_guardianlink'));
        $mform->addElement('text', 'defaultpassword', get_string('uploaddefaultpassword', 'tool_guardianlink'));
        $mform->setType('defaultpassword', PARAM_RAW);
        $mform->addElement('static', 'pwnote', '', get_string('uploaddefaultpassword_help', 'tool_guardianlink'));
        $mform->addElement('advcheckbox', 'forcepasswordchange', get_string('uploadforcepwchange', 'tool_guardianlink'));
        $mform->setDefault('forcepasswordchange', 1);

        // Safety: preview before committing.
        $mform->addElement('advcheckbox', 'previewonly', get_string('uploadpreviewonly', 'tool_guardianlink'));
        $mform->setDefault('previewonly', 1);
        $mform->addHelpButton('previewonly', 'uploadpreviewonly', 'tool_guardianlink');

        $this->add_action_buttons(false, get_string('uploadprocess', 'tool_guardianlink'));
    }
}

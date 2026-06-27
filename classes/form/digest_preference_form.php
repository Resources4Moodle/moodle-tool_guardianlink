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
 * Adult digest-preference form for GuardianLink.
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
 * Digest preference form shown to authorised adults for one learner.
 */
class digest_preference_form extends \moodleform {
    /**
     * Define form fields.
     */
    public function definition(): void {
        $mform = $this->_form;
        $mform->addElement('hidden', 'childid');
        $mform->setType('childid', PARAM_INT);

        $mform->addElement('advcheckbox', 'enabled', get_string('digestenabled', 'tool_guardianlink'));
        $mform->setDefault('enabled', 1);

        $frequencies = [
            'daily' => get_string('frequency_daily', 'tool_guardianlink'),
            'weekly' => get_string('frequency_weekly', 'tool_guardianlink'),
            'fortnightly' => get_string('frequency_fortnightly', 'tool_guardianlink'),
            'monthly' => get_string('frequency_monthly', 'tool_guardianlink'),
        ];
        $mform->addElement('select', 'frequency', get_string('digestfrequency', 'tool_guardianlink'), $frequencies);
        $mform->setDefault('frequency', 'weekly');

        $mform->addElement('text', 'channels', get_string('digestchannels', 'tool_guardianlink'));
        $mform->setType('channels', PARAM_TEXT);
        $mform->setDefault('channels', 'moodle');

        $mform->addElement('advcheckbox', 'includeoverdue', get_string('digestincludeoverdue', 'tool_guardianlink'));
        $mform->setDefault('includeoverdue', 1);
        $mform->addElement('advcheckbox', 'includegrades', get_string('digestincludegrades', 'tool_guardianlink'));
        $mform->addElement('advcheckbox', 'includeattendance', get_string('digestincludeattendance', 'tool_guardianlink'));
        $mform->addElement('advcheckbox', 'includehealth', get_string('digestincludehealth', 'tool_guardianlink'));

        $this->add_action_buttons(true, get_string('save', 'tool_guardianlink'));
    }
}

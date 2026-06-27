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
 * Tutor/helper request form for GuardianLink.
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
 * Tutor/helper request form.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tutor_request_form extends \moodleform {
    /**
     * Define form fields.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;
        $mform->addElement('header', 'general', get_string('tutorrequest', 'tool_guardianlink'));
        $mform->addElement('text', 'childid', get_string('learnerid', 'tool_guardianlink'));
        $mform->setType('childid', PARAM_INT);
        $mform->addRule('childid', null, 'required', null, 'client');
        $mform->addElement('text', 'tutorid', get_string('tutorid', 'tool_guardianlink'));
        $mform->setType('tutorid', PARAM_INT);
        $mform->addRule('tutorid', null, 'required', null, 'client');
        $mform->addElement('textarea', 'courseids', get_string('courseids', 'tool_guardianlink'), ['rows' => 2, 'cols' => 70]);
        $mform->setType('courseids', PARAM_TEXT);
        $mform->addElement('textarea', 'purpose', get_string('purpose', 'tool_guardianlink'), ['rows' => 4, 'cols' => 70]);
        $mform->setType('purpose', PARAM_TEXT);
        $mform->addElement('date_time_selector', 'starttime', get_string('starttime', 'tool_guardianlink'), ['optional' => true]);
        $mform->addElement('date_time_selector', 'endtime', get_string('endtime', 'tool_guardianlink'), ['optional' => true]);
        $this->add_action_buttons(true, get_string('save', 'tool_guardianlink'));
    }
}

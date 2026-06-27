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
 * Access-profile editor form.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guardianlink\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

use tool_guardianlink\local\relationship_service;

/**
 * Create/edit a reusable access profile (a named bundle of scope permissions).
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile_form extends \moodleform {
    /**
     * Define form fields.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('accessprofile', 'tool_guardianlink'));
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'shortname', get_string('templateshortname', 'tool_guardianlink'), ['size' => 40]);
        $mform->setType('shortname', PARAM_ALPHANUMEXT);
        $mform->addRule('shortname', null, 'required', null, 'client');

        $mform->addElement('text', 'name', get_string('templatename', 'tool_guardianlink'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement('advcheckbox', 'enabled', get_string('templateenabled', 'tool_guardianlink'));
        $mform->setDefault('enabled', 1);
        $mform->addElement('text', 'sortorder', get_string('sortorder', 'tool_guardianlink'), ['size' => 5]);
        $mform->setType('sortorder', PARAM_INT);

        $mform->addElement('header', 'perms', get_string('profilepermissions', 'tool_guardianlink'));
        foreach (relationship_service::profile_fields() as $f) {
            $mform->addElement('advcheckbox', $f, get_string($f, 'tool_guardianlink'));
            $mform->setType($f, PARAM_INT);
        }

        $this->add_action_buttons(true, get_string('save', 'tool_guardianlink'));
    }
}

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
 * Parent/guardian "nominate another authorised adult" form.
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
 * Form for a parent to nominate another authorised adult (pending admin approval).
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_guardian_form extends \moodleform {
    /**
     * Define form fields.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;
        $mform->addElement('header', 'general', get_string('addguardian', 'tool_guardianlink'));

        $mform->addElement('hidden', 'childid');
        $mform->setType('childid', PARAM_INT);

        // Nominee lookup by username/idnumber/email (resolved server-side; no PII pre-fill).
        $mform->addElement('text', 'nomineeidnumber', get_string('nomineelookup', 'tool_guardianlink'), ['size' => 50]);
        $mform->setType('nomineeidnumber', PARAM_TEXT);
        $mform->addRule('nomineeidnumber', null, 'required', null, 'client');
        $mform->addHelpButton('nomineeidnumber', 'nomineelookup', 'tool_guardianlink');

        $types = [
            'guardian' => get_string('reltype_guardian', 'tool_guardianlink'),
            'carer' => get_string('reltype_carer', 'tool_guardianlink'),
            'tutor' => get_string('reltype_tutor', 'tool_guardianlink'),
            'mentor' => get_string('reltype_mentor', 'tool_guardianlink'),
        ];
        $mform->addElement('select', 'reltype', get_string('relationship', 'tool_guardianlink'), $types);

        $mform->addElement('textarea', 'courseids', get_string('courseids', 'tool_guardianlink'), ['rows' => 2, 'cols' => 60]);
        $mform->setType('courseids', PARAM_TEXT);

        $mform->addElement('textarea', 'purpose', get_string('purpose', 'tool_guardianlink'), ['rows' => 3, 'cols' => 60]);
        $mform->setType('purpose', PARAM_TEXT);

        $mform->addElement('date_time_selector', 'endtime', get_string('endtime', 'tool_guardianlink'), ['optional' => true]);
        $globalmax = relationship_service::global_max_grant_days();
        if ($globalmax > 0) {
            $mform->addElement('static', 'capnote', '', get_string('durationcapnote', 'tool_guardianlink', $globalmax));
        }

        $mform->addElement('static', 'pendingnote', '', get_string('nominationpendingnote', 'tool_guardianlink'));

        $this->add_action_buttons(true, get_string('addguardian', 'tool_guardianlink'));
    }
}

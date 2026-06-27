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
 * Per-course GuardianLink policy form (teacher-set, capped by admin global).
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
 * Teacher-facing per-course access-duration / delegation policy.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_policy_form extends \moodleform {
    /**
     * Define form fields.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;
        $mform->addElement('header', 'general', get_string('coursepolicy', 'tool_guardianlink'));

        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

        $globalmax = relationship_service::global_max_grant_days();
        $capnote = $globalmax > 0
            ? get_string('coursemaxcapped', 'tool_guardianlink', $globalmax)
            : get_string('coursemaxnocap', 'tool_guardianlink');

        $mform->addElement('text', 'maxgrantdays', get_string('coursemaxgrantdays', 'tool_guardianlink'), ['size' => 6]);
        $mform->setType('maxgrantdays', PARAM_INT);
        $mform->addElement('static', 'maxnote', '', $capnote);

        $mform->addElement('text', 'defaultgrantdays', get_string('coursedefaultgrantdays', 'tool_guardianlink'), ['size' => 6]);
        $mform->setType('defaultgrantdays', PARAM_INT);

        $mform->addElement('advcheckbox', 'allowparentpropose', get_string('courseallowparentpropose', 'tool_guardianlink'));
        $mform->setDefault('allowparentpropose', 1);
        $mform->addElement('advcheckbox', 'allowteacherproxy', get_string('courseallowteacherproxy', 'tool_guardianlink'));
        $mform->setDefault('allowteacherproxy', 1);

        $mform->addElement('advcheckbox', 'allowassistedaccess', get_string('courseallowassisted', 'tool_guardianlink'));
        $mform->setDefault('allowassistedaccess', 0);
        $mform->addHelpButton('allowassistedaccess', 'courseallowassisted', 'tool_guardianlink');

        // Independent (unsupervised) access switch — only offered when the admin enabled the feature.
        if (\tool_guardianlink\local\supervision_service::feature_enabled()) {
            $mform->addElement(
                'advcheckbox',
                'allowindependentaccess',
                get_string('courseallowindependent', 'tool_guardianlink')
            );
            $mform->setDefault('allowindependentaccess', 0);
            $mform->addHelpButton('allowindependentaccess', 'courseallowindependent', 'tool_guardianlink');
        }

        $this->add_action_buttons(true, get_string('save', 'tool_guardianlink'));
    }

    /**
     * Validate submitted form data.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $globalmax = relationship_service::global_max_grant_days();
        if ($globalmax > 0 && (int)$data['maxgrantdays'] > $globalmax) {
            $errors['maxgrantdays'] = get_string('coursemaxexceedsglobal', 'tool_guardianlink', $globalmax);
        }
        return $errors;
    }
}

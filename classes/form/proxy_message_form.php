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
 * Proxy message form for GuardianLink.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guardianlink\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

use tool_guardianlink\local\template_service;

/**
 * Teacher-to-authorised-adult proxy message form.
 *
 * Customdata:
 *  - 'gradeitems' => array [itemid => name] to offer a "specific test/activity result" selector.
 *  - 'activityplaceholders' => array [token => description] of per-activity {grade_<id>} tokens.
 */
class proxy_message_form extends \moodleform {
    /**
     * Define form fields.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;
        $gradeitems = $this->_customdata['gradeitems'] ?? [];
        $activityplaceholders = $this->_customdata['activityplaceholders'] ?? [];

        $mform->addElement('header', 'general', get_string('messageauthorisedadults', 'tool_guardianlink'));
        $mform->addElement('hidden', 'childid');
        $mform->setType('childid', PARAM_INT);
        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('text', 'subject', get_string('subject', 'tool_guardianlink'), ['size' => 80]);
        $mform->setType('subject', PARAM_TEXT);
        $mform->addRule('subject', null, 'required', null, 'client');

        // HTML message body with placeholder support (parity with admin templates).
        $mform->addElement('editor', 'message', get_string('message', 'tool_guardianlink'), ['rows' => 10]);
        $mform->setType('message', PARAM_RAW);
        $mform->addRule('message', null, 'required', null, 'client');

        // Optionally send a specific test/activity result (fills {testname} and {testresult}).
        if (!empty($gradeitems)) {
            $options = [0 => get_string('notestselected', 'tool_guardianlink')] + $gradeitems;
            $mform->addElement('select', 'gradeitemid', get_string('specifictest', 'tool_guardianlink'), $options);
            $mform->setType('gradeitemid', PARAM_INT);
            $mform->addHelpButton('gradeitemid', 'specifictest', 'tool_guardianlink');
        } else {
            $mform->addElement('hidden', 'gradeitemid', 0);
            $mform->setType('gradeitemid', PARAM_INT);
        }

        // Placeholder reference (general + per-activity tokens for this course).
        $list = [];
        foreach (template_service::placeholders() as $key => $desc) {
            $list[] = '<code>{' . $key . '}</code> — ' . s($desc);
        }
        $mform->addElement(
            'static',
            'placeholders',
            get_string('templateplaceholders', 'tool_guardianlink'),
            implode('<br>', $list)
        );
        if ($activityplaceholders) {
            $alist = [];
            foreach ($activityplaceholders as $key => $desc) {
                $alist[] = '<code>{' . $key . '}</code> — ' . s($desc);
            }
            $mform->addElement(
                'static',
                'activityplaceholders',
                get_string('activityplaceholders', 'tool_guardianlink'),
                implode('<br>', $alist)
            );
        }

        $this->add_action_buttons(true, get_string('send', 'tool_guardianlink'));
    }
}

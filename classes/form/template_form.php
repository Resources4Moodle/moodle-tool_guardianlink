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
 * Email/message template editor form.
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
 * Create/edit an email template with placeholders.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class template_form extends \moodleform {
    /**
     * Define form fields.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('template', 'tool_guardianlink'));
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        // 0 = global/admin template; >0 = course-scoped teacher template.
        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('text', 'shortname', get_string('templateshortname', 'tool_guardianlink'), ['size' => 40]);
        $mform->setType('shortname', PARAM_ALPHANUMEXT);
        $mform->addRule('shortname', null, 'required', null, 'client');

        $mform->addElement('text', 'name', get_string('templatename', 'tool_guardianlink'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $mform->addElement(
            'select',
            'triggerkey',
            get_string('templatetrigger', 'tool_guardianlink'),
            template_service::triggers()
        );

        $mform->addElement('advcheckbox', 'enabled', get_string('templateenabled', 'tool_guardianlink'));
        $mform->setDefault('enabled', 1);

        $mform->addElement('text', 'subject', get_string('subject', 'tool_guardianlink'), ['size' => 70]);
        $mform->setType('subject', PARAM_TEXT);
        $mform->addRule('subject', null, 'required', null, 'client');

        $mform->addElement('editor', 'body', get_string('templatebody', 'tool_guardianlink'), ['rows' => 12]);
        $mform->setType('body', PARAM_RAW);

        // Placeholder reference.
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
        // Per-activity {grade_<id>} tokens, when this template is scoped to a course.
        $activityplaceholders = $this->_customdata['activityplaceholders'] ?? [];
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

        $this->add_action_buttons(true, get_string('save', 'tool_guardianlink'));
    }
}

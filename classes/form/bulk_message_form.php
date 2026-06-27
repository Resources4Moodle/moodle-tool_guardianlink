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
 * Bulk audience message form for GuardianLink.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guardianlink\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/cohort/lib.php');

/**
 * Admin/staff bulk message-to-authorised-adults form.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bulk_message_form extends \moodleform {
    /**
     * Define form fields.
     *
     * @return void
     */
    public function definition(): void {
        global $DB;
        $mform = $this->_form;

        $mform->addElement('header', 'audience', get_string('bulkaudience', 'tool_guardianlink'));

        $types = [
            'course' => get_string('bulkaudience_course', 'tool_guardianlink'),
            'category' => get_string('bulkaudience_category', 'tool_guardianlink'),
            'cohort' => get_string('bulkaudience_cohort', 'tool_guardianlink'),
            'overdue' => get_string('bulkaudience_overdue', 'tool_guardianlink'),
        ];
        $mform->addElement('select', 'audiencetype', get_string('bulkaudiencetype', 'tool_guardianlink'), $types);
        $mform->setDefault('audiencetype', 'course');

        // Course selector — used by both the "course" and "overdue" (at-risk) audiences.
        $mform->addElement('course', 'courseid', get_string('course'), ['multiple' => false]);
        $mform->hideIf('courseid', 'audiencetype', 'eq', 'category');
        $mform->hideIf('courseid', 'audiencetype', 'eq', 'cohort');

        // Category selector.
        $catoptions = ['0' => get_string('choose')] + \core_course_category::make_categories_list();
        $mform->addElement('select', 'categoryid', get_string('category'), $catoptions);
        $mform->setType('categoryid', PARAM_INT);
        $mform->hideIf('categoryid', 'audiencetype', 'neq', 'category');

        // Cohort selector.
        $cohortoptions = ['0' => get_string('choose')];
        foreach ($DB->get_records('cohort', ['visible' => 1], 'name ASC', 'id, name') as $cohort) {
            $cohortoptions[$cohort->id] = format_string($cohort->name);
        }
        $mform->addElement('select', 'cohortid', get_string('cohort', 'cohort'), $cohortoptions);
        $mform->setType('cohortid', PARAM_INT);
        $mform->hideIf('cohortid', 'audiencetype', 'neq', 'cohort');

        $mform->addElement('header', 'filters', get_string('bulkfilters', 'tool_guardianlink'));
        $mform->addElement('advcheckbox', 'legalonly', get_string('bulklegalonly', 'tool_guardianlink'));
        $mform->setDefault('legalonly', 0);
        $mform->addElement('advcheckbox', 'verifiedonly', get_string('bulkverifiedonly', 'tool_guardianlink'));
        $mform->setDefault('verifiedonly', 1);
        $mform->addElement('advcheckbox', 'excluderestricted', get_string('bulkexcluderestricted', 'tool_guardianlink'));
        $mform->setDefault('excluderestricted', 1);
        $mform->addHelpButton('excluderestricted', 'bulkexcluderestricted', 'tool_guardianlink');

        $mform->addElement('header', 'content', get_string('bulkmessagecontent', 'tool_guardianlink'));
        $mform->addElement('text', 'subject', get_string('subject', 'tool_guardianlink'), ['size' => 80]);
        $mform->setType('subject', PARAM_TEXT);
        $mform->addRule('subject', null, 'required', null, 'client');
        $mform->addElement('textarea', 'message', get_string('message', 'tool_guardianlink'), ['rows' => 10, 'cols' => 80]);
        $mform->setType('message', PARAM_TEXT);
        $mform->addRule('message', null, 'required', null, 'client');

        // The "preview" button recalculates the audience; "send" dispatches.
        $buttonarray = [
            $mform->createElement('submit', 'preview', get_string('bulkpreview', 'tool_guardianlink')),
            $mform->createElement('submit', 'send', get_string('bulksend', 'tool_guardianlink')),
            $mform->createElement('cancel'),
        ];
        $mform->addGroup($buttonarray, 'buttonar', '', ' ', false);
    }

    /**
     * Validate that the selected audience target is present.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $type = $data['audiencetype'] ?? 'course';
        if (in_array($type, ['course', 'overdue'], true) && empty($data['courseid'])) {
            $errors['courseid'] = get_string('required');
        }
        if ($type === 'category' && empty($data['categoryid'])) {
            $errors['categoryid'] = get_string('required');
        }
        if ($type === 'cohort' && empty($data['cohortid'])) {
            $errors['cohortid'] = get_string('required');
        }
        return $errors;
    }
}

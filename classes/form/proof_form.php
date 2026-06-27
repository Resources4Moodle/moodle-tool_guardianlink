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
 * Custody-chain entry form: HTML validation note + proof attachments.
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
 * Add a custody entry (HTML validation note + proof files).
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class proof_form extends \moodleform {
    /**
     * Define form fields.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('proofadd', 'tool_guardianlink'));
        $mform->addElement('hidden', 'relationshipid');
        $mform->setType('relationshipid', PARAM_INT);

        $mform->addElement(
            'editor',
            'note',
            get_string('proofnote', 'tool_guardianlink'),
            ['rows' => 8],
            ['maxfiles' => EDITOR_UNLIMITED_FILES, 'context' => \context_system::instance()]
        );
        $mform->setType('note', PARAM_RAW);
        $mform->addRule('note', null, 'required', null, 'client');

        $mform->addElement(
            'filemanager',
            'attachments',
            get_string('proofattachments', 'tool_guardianlink'),
            null,
            ['subdirs' => 0, 'maxbytes' => 0, 'maxfiles' => 20]
        );
        $mform->addHelpButton('attachments', 'proofattachments', 'tool_guardianlink');

        $this->add_action_buttons(true, get_string('proofsave', 'tool_guardianlink'));
    }
}

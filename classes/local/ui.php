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
 * Small shared UI helpers for GuardianLink pages.
 *
 * @package    tool_guardianlink
 * @copyright  2026 GuardianLink contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_guardianlink\local;

/**
 * Presentation helpers shared across GuardianLink entry points.
 */
class ui {
    /**
     * A "Help for this page" link that opens the manual at the relevant section.
     * Echo this right after a page heading.
     *
     * @param string $anchor Manual section id (see manual.php).
     * @return string HTML
     */
    public static function help_link(string $anchor): string {
        $url = new \moodle_url('/admin/tool/guardianlink/manual.php', [], $anchor);
        return \html_writer::div(
            \html_writer::link(
                $url,
                get_string('helpforthispage', 'tool_guardianlink'),
                ['class' => 'btn btn-link btn-sm p-0', 'target' => '_blank', 'rel' => 'noopener']
            ),
            'tool_guardianlink-help float-sm-right mb-2'
        );
    }
}

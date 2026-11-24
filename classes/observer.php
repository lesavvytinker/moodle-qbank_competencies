<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace qbank_competencies;

/**
 * Keeps mappings tidy when questions or competencies are deleted.
 *
 * @package    qbank_competencies
 * @copyright  2026 Daniel Maloney
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * A question version or a competency was deleted: remove mappings that no longer point anywhere.
     *
     * A mapping belongs to the question bank entry, so it stays until the last version of the question is deleted.
     *
     * @param \core\event\base $event
     */
    public static function cleanup(\core\event\base $event): void {
        local\api::delete_orphans();
    }
}

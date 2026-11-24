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

namespace qbank_competencies\event;

/**
 * A question's competencies were changed.
 *
 * @property-read array $other questionbankentryid, added (competency IDs), removed (competency IDs)
 *
 * @package    qbank_competencies
 * @copyright  2026 Daniel Maloney
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class competencies_updated extends \core\event\base {
    #[\Override]
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'question';
    }

    #[\Override]
    public static function get_name() {
        return get_string('eventcompetenciesupdated', 'qbank_competencies');
    }

    #[\Override]
    public function get_description() {
        return "The user with id '{$this->userid}' changed the competencies of the question with id '{$this->objectid}': "
            . 'added ' . (implode(', ', $this->other['added'] ?? []) ?: 'none') . '; removed '
            . (implode(', ', $this->other['removed'] ?? []) ?: 'none') . '.';
    }

    #[\Override]
    public function get_url() {
        return new \moodle_url('/question/bank/competencies/edit.php', ['id' => $this->objectid]);
    }

    #[\Override]
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->other['questionbankentryid'])) {
            throw new \coding_exception('The \'questionbankentryid\' value must be set in other.');
        }
    }

    #[\Override]
    public static function get_objectid_mapping() {
        return ['db' => 'question', 'restore' => 'question'];
    }

    #[\Override]
    public static function get_other_mapping() {
        return false;
    }
}

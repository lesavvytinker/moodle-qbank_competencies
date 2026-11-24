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

use core_question\local\bank\question_action_base;
use qbank_competencies\local\api;

/**
 * "Competencies" in each question's action menu.
 *
 * @package    qbank_competencies
 * @copyright  2026 Daniel Maloney
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class competencies_action extends question_action_base {
    #[\Override]
    public function get_menu_position(): int {
        return 350;
    }

    #[\Override]
    protected function get_url_icon_and_label(\stdClass $question): array {
        if (!question_has_capability_on($question, 'edit')) {
            return [null, null, null];
        }
        return [api::edit_url((int) $question->id, $this->qbank->returnurl), 'i/competencies',
            get_string('competencies', 'qbank_competencies')];
    }
}

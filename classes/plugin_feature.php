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

use core_question\local\bank\view;

/**
 * What the plugin adds to the question bank: a Competencies column and a menu action to edit them.
 *
 * @package    qbank_competencies
 * @copyright  2026 Daniel Maloney
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plugin_feature extends \core_question\local\bank\plugin_features_base {
    #[\Override]
    public function get_question_columns(view $qbank): array {
        return local\api::is_enabled() ? [new competencies_column($qbank)] : [];
    }

    #[\Override]
    public function get_question_actions(view $qbank): array {
        return local\api::is_enabled() ? [new competencies_action($qbank)] : [];
    }
}

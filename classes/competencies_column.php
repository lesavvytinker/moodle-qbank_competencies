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

use core_question\local\bank\column_base;
use html_writer;
use qbank_competencies\local\api;

/**
 * Question bank column listing the competencies each question is mapped to.
 *
 * @package    qbank_competencies
 * @copyright  2026 Daniel Maloney
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class competencies_column extends column_base {
    #[\Override]
    public function get_name(): string {
        return 'competencies';
    }

    #[\Override]
    public function get_title(): string {
        return get_string('competencies', 'qbank_competencies');
    }

    #[\Override]
    public function load_additional_data(array $questions): void {
        $entryids = [];
        foreach ($questions as $question) {
            if (!empty($question->questionbankentryid)) {
                $entryids[] = (int) $question->questionbankentryid;
            }
        }
        $mapped = api::get_for_entries($entryids);
        foreach ($questions as $question) {
            $question->qbankcompetencies = $mapped[(int) ($question->questionbankentryid ?? 0)] ?? [];
        }
    }

    #[\Override]
    protected function display_content($question, $rowclasses): void {
        $competencies = $question->qbankcompetencies ?? null;
        if ($competencies === null) {
            $competencies = api::get_for_entries([(int) $question->questionbankentryid])[(int) $question->questionbankentryid]
                ?? [];
        }
        $badges = [];
        foreach ($competencies as $competency) {
            $short = trim((string) $competency->idnumber) !== '' ? $competency->idnumber
                : format_string($competency->shortname, true, ['escape' => false]);
            $badges[] = html_writer::span(
                s($short),
                'badge bg-secondary text-wrap me-1 mb-1',
                ['title' => api::label($competency)]
            );
        }
        if ($badges) {
            echo html_writer::div(implode('', $badges), 'qbank-competencies');
        }
        if (question_has_capability_on($question, 'edit')) {
            $label = get_string($badges ? 'editcompetencies' : 'addcompetencies', 'qbank_competencies');
            echo html_writer::link(
                api::edit_url((int) $question->id, $this->qbank->returnurl),
                s($label),
                ['class' => 'small', 'aria-label' => $label . ': ' . format_string($question->name)]
            );
        }
    }

    #[\Override]
    public function get_extra_classes(): array {
        return ['pe-3'];
    }

    #[\Override]
    public function get_default_width(): int {
        return 200;
    }
}

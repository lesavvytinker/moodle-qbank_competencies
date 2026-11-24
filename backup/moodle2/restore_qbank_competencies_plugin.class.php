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

/**
 * Restore of question competencies.
 *
 * @package    qbank_competencies
 * @copyright  2026 Daniel Maloney
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restores each question's competencies: the same competency on the same site, otherwise the competency with the
 * same ID number in a framework with the same ID number. Competencies that can't be found are skipped.
 */
class restore_qbank_competencies_plugin extends restore_qbank_plugin {
    /**
     * Paths handled at question level.
     *
     * @return restore_path_element[]
     */
    protected function define_question_plugin_structure() {
        return [new restore_path_element('competency', $this->get_pathfor('/competencies/competency'))];
    }

    /**
     * Restore one competency mapping.
     *
     * @param array $data
     */
    public function process_competency($data) {
        global $DB;
        $data = (object) $data;
        if (!$this->get_mappingid('question_created', $this->get_old_parentid('question'))) {
            // The question already existed, with its own mappings.
            return;
        }
        $newquestionid = $this->get_new_parentid('question');
        $entryid = $DB->get_field('question_versions', 'questionbankentryid', ['questionid' => $newquestionid]);
        if (!$entryid) {
            return;
        }
        $competencyid = null;
        $same = $DB->get_record('competency', ['id' => $data->competencyid], 'id, idnumber');
        if ($same && $same->idnumber === $data->idnumber && $this->task->is_samesite()) {
            $competencyid = (int) $same->id;
        } else {
            $competencyid = $DB->get_field_sql(
                "SELECT c.id
                                                  FROM {competency} c
                                                  JOIN {competency_framework} cf ON cf.id = c.competencyframeworkid
                                                 WHERE c.idnumber = :idnumber AND cf.idnumber = :frameworkidnumber",
                ['idnumber' => $data->idnumber, 'frameworkidnumber' => $data->frameworkidnumber]
            );
        }
        if ($competencyid) {
            \qbank_competencies\local\api::add_competency((int) $entryid, (int) $competencyid);
        }
    }
}

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
 * Backup of question competencies.
 *
 * @package    qbank_competencies
 * @copyright  2026 Daniel Maloney
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Adds each question's competencies to the backup, with ID numbers so they can be matched on another site.
 */
class backup_qbank_competencies_plugin extends \backup_qbank_plugin {
    /**
     * The competencies of the question being backed up.
     *
     * @return backup_plugin_element
     */
    protected function define_question_plugin_structure() {
        $plugin = $this->get_plugin_element();
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());
        $plugin->add_child($pluginwrapper);

        $competencies = new backup_nested_element('competencies');
        $competency = new backup_nested_element('competency', ['id'], ['competencyid', 'idnumber', 'frameworkidnumber']);
        $pluginwrapper->add_child($competencies);
        $competencies->add_child($competency);

        $competency->set_source_sql(
            "SELECT qcm.id, qcm.competencyid, c.idnumber, cf.idnumber AS frameworkidnumber
               FROM {qbank_competencies} qcm
               JOIN {question_versions} qv ON qv.questionbankentryid = qcm.questionbankentryid
               JOIN {competency} c ON c.id = qcm.competencyid
               JOIN {competency_framework} cf ON cf.id = c.competencyframeworkid
              WHERE qv.questionid = :questionid",
            ['questionid' => backup::VAR_PARENTID]
        );
        return $plugin;
    }
}

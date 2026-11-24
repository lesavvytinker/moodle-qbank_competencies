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
 * Strings for Question competencies.
 *
 * @package    qbank_competencies
 * @copyright  2026 Daniel Maloney
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addcompetencies'] = 'Add competencies';
$string['competencies'] = 'Competencies';
$string['competencies_help'] = 'The competencies this question gives supporting evidence of. They apply to every version of the question. You can choose from the course\'s competencies. A question\'s result never rates a competency: the quiz must still be linked to the competency in its own settings, and the assessor makes the judgement.';
$string['competenciessaved'] = 'Competencies saved';
$string['editcompetencies'] = 'Edit competencies';
$string['editcompetenciesfor'] = 'Competencies: {$a}';
$string['eventcompetenciesupdated'] = 'Question competencies updated';
$string['nocompetencies'] = 'No competencies';
$string['nocoursecompetencies'] = 'This course has no competencies yet. Add competencies to the course first (More → Competencies), then map questions to them.';
$string['notincourselabel'] = '{$a} (not in this course)';
$string['passfraction'] = 'Question counts as passed at';
$string['passfraction_desc'] = 'The share of a question\'s marks a learner needs for the question to show as passed in supporting evidence. Manually marked questions use the mark the assessor gave.';
$string['passpercent'] = '{$a}% of its marks';
$string['pluginname'] = 'Question competencies';
$string['privacy:metadata'] = 'The Question competencies plugin maps questions to competencies. It stores no personal data.';
$string['supportingnote'] = 'Questions are supporting evidence only. To count towards a competency, the quiz must also be linked to it in the quiz\'s settings.';

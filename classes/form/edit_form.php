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

namespace qbank_competencies\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Choose the competencies a question is mapped to.
 *
 * Custom data: options (competencyid => label), courseid (int|null).
 *
 * @package    qbank_competencies
 * @copyright  2026 Daniel Maloney
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_form extends \moodleform {
    #[\Override]
    protected function definition() {
        $mform = $this->_form;
        $options = $this->_customdata['options'];

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'returnurl');
        $mform->setType('returnurl', PARAM_LOCALURL);

        $mform->addElement('static', 'supportingnote', '', get_string('supportingnote', 'qbank_competencies'));

        if (!$options) {
            $mform->addElement(
                'static',
                'nooptions',
                get_string('competencies', 'qbank_competencies'),
                get_string('nocoursecompetencies', 'qbank_competencies')
            );
        } else {
            $mform->addElement('autocomplete', 'competencies', get_string('competencies', 'qbank_competencies'), $options, [
                'multiple' => true,
                'noselectionstring' => get_string('nocompetencies', 'qbank_competencies'),
            ]);
            $mform->addHelpButton('competencies', 'competencies', 'qbank_competencies');
        }
        $this->add_action_buttons(true, get_string('savechanges'));
    }
}

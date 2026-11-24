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
 * Settings for Question competencies.
 *
 * @package    qbank_competencies
 * @copyright  2026 Daniel Maloney
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $choices = [];
    foreach ([100, 90, 80, 75, 70, 60, 50] as $percent) {
        $choices[(string) ($percent / 100)] = new lang_string('passpercent', 'qbank_competencies', $percent);
    }
    $settings->add(new admin_setting_configselect(
        'qbank_competencies/passfraction',
        new lang_string('passfraction', 'qbank_competencies'),
        new lang_string('passfraction_desc', 'qbank_competencies'),
        '1',
        $choices
    ));
}

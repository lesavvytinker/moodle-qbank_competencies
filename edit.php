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
 * Map a question (all its versions) to competencies.
 *
 * @package    qbank_competencies
 * @copyright  2026 Daniel Maloney
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/questionlib.php');

use qbank_competencies\local\api;

$questionid = required_param('id', PARAM_INT);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

$question = $DB->get_record('question', ['id' => $questionid], '*', MUST_EXIST);
$entry = $DB->get_record_sql("SELECT qbe.id, qc.contextid
                                FROM {question_versions} qv
                                JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                                JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                               WHERE qv.questionid = ?", [$questionid], MUST_EXIST);
$context = context::instance_by_id((int) $entry->contextid);

if ($context->contextlevel == CONTEXT_MODULE) {
    $cm = get_coursemodule_from_id('', $context->instanceid, 0, false, MUST_EXIST);
    require_login($cm->course, false, $cm);
} else if ($courseid = api::get_bank_courseid($context)) {
    require_login($courseid, false);
} else {
    require_login();
}
question_require_capability_on($question, 'edit');
if (!api::is_enabled()) {
    throw new moodle_exception('competenciesarenotenabled', 'core_competency');
}

$url = new moodle_url('/question/bank/competencies/edit.php', ['id' => $questionid]);
if ($returnurl) {
    $url->param('returnurl', $returnurl);
}
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$title = get_string('editcompetenciesfor', 'qbank_competencies', format_string($question->name));
$PAGE->set_title($title);
$PAGE->set_heading($COURSE->fullname ?? $SITE->fullname);

if ($returnurl) {
    $back = new moodle_url($returnurl);
} else if ($context->contextlevel == CONTEXT_MODULE) {
    $back = new moodle_url('/question/edit.php', ['cmid' => $context->instanceid]);
} else {
    $back = new moodle_url('/');
}

$current = api::get_competencyids((int) $entry->id);
$options = api::get_options($context, $current);
$form = new \qbank_competencies\form\edit_form($url, ['options' => $options]);
$form->set_data(['id' => $questionid, 'returnurl' => $returnurl, 'competencies' => $current]);

if ($form->is_cancelled()) {
    redirect($back);
} else if ($data = $form->get_data()) {
    $chosen = array_intersect(array_map('intval', (array) ($data->competencies ?? [])), array_keys($options));
    [$added, $removed] = api::set_competencies((int) $entry->id, $chosen);
    if ($added || $removed) {
        \qbank_competencies\event\competencies_updated::create([
            'context' => $context,
            'objectid' => $questionid,
            'other' => ['questionbankentryid' => (int) $entry->id, 'added' => $added, 'removed' => $removed],
        ])->trigger();
    }
    redirect($back, get_string('competenciessaved', 'qbank_competencies'), null, \core\output\notification::NOTIFY_SUCCESS);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
$form->display();
echo $OUTPUT->footer();

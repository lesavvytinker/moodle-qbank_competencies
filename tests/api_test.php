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

use qbank_competencies\local\api;
use stdClass;

/**
 * Tests for the question competency API.
 *
 * @package    qbank_competencies
 * @copyright  2026 Daniel Maloney
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(api::class)]
final class api_test extends \advanced_testcase {
    /** @var stdClass */
    private stdClass $course;

    /** @var int[] Competency IDs: c1 and c2 are course competencies, c3 isn't. */
    private array $comps = [];

    /** @var stdClass Category in the course's question bank. */
    private stdClass $category;

    #[\Override]
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $this->resetAfterTest();
        set_config('enabled', 1, 'core_competency');
        $this->setAdminUser();

        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course();
        $ccg = $gen->get_plugin_generator('core_competency');
        $framework = $ccg->create_framework(['idnumber' => 'FW1']);
        foreach (['c1', 'c2', 'c3'] as $name) {
            $this->comps[$name] = (int) $ccg->create_competency(['competencyframeworkid' => $framework->get('id'),
                'idnumber' => strtoupper($name), 'shortname' => "Competency {$name}"])->get('id');
        }
        $ccg->create_course_competency(['courseid' => $this->course->id, 'competencyid' => $this->comps['c1']]);
        $ccg->create_course_competency(['courseid' => $this->course->id, 'competencyid' => $this->comps['c2']]);

        $qbank = $gen->create_module('qbank', ['course' => $this->course->id]);
        $this->category = $gen->get_plugin_generator('core_question')->create_question_category(
            ['contextid' => \context_module::instance($qbank->cmid)->id]
        );
    }

    /**
     * A true/false question (correct answer: True) in the course's bank.
     *
     * @return stdClass
     */
    private function create_question(): stdClass {
        return $this->getDataGenerator()->get_plugin_generator('core_question')
            ->create_question('truefalse', null, ['category' => $this->category->id]);
    }

    /**
     * A quiz with a pass mark of 50%.
     *
     * @param stdClass[] $questions Fixed questions to add
     * @return stdClass
     */
    private function create_quiz(array $questions): stdClass {
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id, 'grade' => 10,
            'sumgrades' => count($questions), 'gradepass' => 5]);
        foreach ($questions as $question) {
            quiz_add_quiz_question($question->id, $quiz);
        }
        \mod_quiz\quiz_settings::create($quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();
        return $quiz;
    }

    public function test_set_and_get(): void {
        $question = $this->create_question();
        $entryid = api::get_entryid((int) $question->id);

        [$added, $removed] = api::set_competencies($entryid, [$this->comps['c1'], $this->comps['c2'], 999999]);
        $this->assertEqualsCanonicalizing([$this->comps['c1'], $this->comps['c2']], $added);
        $this->assertSame([], $removed);

        [$added, $removed] = api::set_competencies($entryid, [$this->comps['c2']]);
        $this->assertSame([], $added);
        $this->assertSame([$this->comps['c1']], $removed);

        $mapped = api::get_for_entries([$entryid]);
        $this->assertSame([$this->comps['c2']], array_keys($mapped[$entryid]));
        $this->assertSame('C2', $mapped[$entryid][$this->comps['c2']]->idnumber);
    }

    public function test_mapping_survives_new_version_and_goes_with_last_version(): void {
        $qgen = $this->getDataGenerator()->get_plugin_generator('core_question');
        $question = $this->create_question();
        $entryid = api::get_entryid((int) $question->id);
        api::set_competencies($entryid, [$this->comps['c1']]);

        $v2 = $qgen->update_question($question, null, ['name' => 'Version 2']);
        $this->assertSame($entryid, api::get_entryid((int) $v2->id));
        $this->assertSame([$this->comps['c1']], api::get_competencyids($entryid));

        question_delete_question($v2->id);
        $this->assertSame([$this->comps['c1']], api::get_competencyids($entryid), 'Version 1 still exists');
        question_delete_question($question->id);
        $this->assertSame([], api::get_competencyids($entryid));
    }

    public function test_deleting_a_competency_removes_its_mappings(): void {
        $question = $this->create_question();
        $entryid = api::get_entryid((int) $question->id);
        api::set_competencies($entryid, [$this->comps['c3']]);
        \core_competency\api::delete_competency($this->comps['c3']);
        $this->assertSame([], api::get_competencyids($entryid));
    }

    public function test_options_are_course_competencies_plus_current(): void {
        $context = \context::instance_by_id($this->category->contextid);
        $options = api::get_options($context);
        $this->assertSame([$this->comps['c1'], $this->comps['c2']], array_keys($options));
        $this->assertSame('C1 Competency c1', $options[$this->comps['c1']]);

        $options = api::get_options($context, [$this->comps['c3']]);
        $this->assertStringContainsString('not in this course', $options[$this->comps['c3']]);
    }

    public function test_outcome(): void {
        $this->assertSame(api::PASSED, api::outcome(1.0, 'gradedright'));
        $this->assertSame(api::NOTPASSED, api::outcome(0.5, 'gradedpartial'));
        $this->assertSame(api::PENDING, api::outcome(null, 'needsgrading'));
        $this->assertSame(api::NOTPASSED, api::outcome(null, 'gaveup'));
        $this->assertSame(api::NOTANSWERED, api::outcome(null, 'todo'));
        set_config('passfraction', '0.5', 'qbank_competencies');
        $this->assertSame(api::PASSED, api::outcome(0.5, 'gradedpartial'));
    }

    public function test_user_results(): void {
        $gen = $this->getDataGenerator();
        $learner = $gen->create_and_enrol($this->course, 'student');
        $q1 = $this->create_question();
        $q2 = $this->create_question();
        $q3 = $this->create_question();
        api::set_competencies(api::get_entryid((int) $q1->id), [$this->comps['c1']]);
        api::set_competencies(api::get_entryid((int) $q2->id), [$this->comps['c1'], $this->comps['c2']]);
        $quiz = $this->create_quiz([$q1, $q2, $q3]);
        $gen->get_plugin_generator('core_competency')->create_course_module_competency(
            ['cmid' => $quiz->cmid, 'competencyid' => $this->comps['c1']]
        );

        $qgen = $gen->get_plugin_generator('mod_quiz');
        $this->setUser($learner);
        $attempt = $qgen->create_attempt($quiz->id, $learner->id);
        $qgen->submit_responses($attempt->id, [1 => 'True', 2 => 'False', 3 => 'True'], false, true);
        $attempt2 = $qgen->create_attempt($quiz->id, $learner->id);
        $qgen->submit_responses($attempt2->id, [1 => 'True', 2 => 'True', 3 => 'True'], false, true);
        $this->setAdminUser();

        $results = api::get_user_results((int) $learner->id);
        // Attempt 1: q1 (c1), q2 (c1, c2); attempt 2: the same. q3 isn't mapped.
        $this->assertCount(6, $results);
        $first = array_values(array_filter($results, fn($r) => $r->attemptno === 1));
        $this->assertSame([1, 2, 2], array_column($first, 'slot'));
        $this->assertSame(api::PASSED, $first[0]->outcome);
        $this->assertTrue($first[0]->linkedatquiz);
        $this->assertFalse($first[0]->islatest);
        $c2 = array_values(array_filter($first, fn($r) => $r->competencyid === $this->comps['c2']))[0];
        $this->assertSame(api::NOTPASSED, $c2->outcome);
        $this->assertFalse($c2->linkedatquiz, 'The quiz is only linked to c1');
        $this->assertEquals(0.0, $c2->mark);

        $results = api::get_user_results((int) $learner->id, [$this->comps['c2']]);
        $this->assertCount(2, $results);
        $this->assertSame(api::PASSED, $results[1]->outcome);
        $this->assertTrue($results[1]->islatest);

        $this->assertSame([], api::get_user_results((int) $learner->id, []));
        $this->assertCount(3, api::get_user_results((int) $learner->id, null, ['qza.attempt = :one', ['one' => 1]]));
    }

    public function test_course_overview_flags(): void {
        $gen = $this->getDataGenerator();
        $ccg = $gen->get_plugin_generator('core_competency');

        $unused = $this->create_question();
        $fixed = $this->create_question();
        api::set_competencies(api::get_entryid((int) $unused->id), [$this->comps['c1']]);
        api::set_competencies(api::get_entryid((int) $fixed->id), [$this->comps['c1'], $this->comps['c3']]);
        $quiz = $this->create_quiz([$fixed]);
        $ccg->create_course_module_competency(['cmid' => $quiz->cmid, 'competencyid' => $this->comps['c1']]);

        // A question only drawn at random, in its own category, mapped to c2.
        $randomcat = $gen->get_plugin_generator('core_question')->create_question_category(
            ['contextid' => $this->category->contextid]
        );
        $randomq = $gen->get_plugin_generator('core_question')->create_question(
            'truefalse',
            null,
            ['category' => $randomcat->id]
        );
        api::set_competencies(api::get_entryid((int) $randomq->id), [$this->comps['c2']]);
        \mod_quiz\quiz_settings::create($quiz->id)->get_structure()->add_random_questions(0, 1, ['filter' => [
            'category' => ['jointype' => 1, 'values' => [$randomcat->id], 'filteroptions' => ['includesubcategories' => false]],
        ]]);

        $overview = api::get_course_overview((int) $this->course->id);
        $this->assertEqualsCanonicalizing([$this->comps['c1'], $this->comps['c2'], $this->comps['c3']], array_keys($overview));

        $c1 = $overview[$this->comps['c1']];
        $this->assertSame([(int) $quiz->cmid], $c1->activitycmids);
        $this->assertFalse($c1->questiononly);
        $byentry = [];
        foreach ($c1->questions as $q) {
            $byentry[$q->entryid] = $q;
        }
        $this->assertSame([api::FLAG_NOTUSED], $byentry[api::get_entryid((int) $unused->id)]->flags);
        $fixedinfo = $byentry[api::get_entryid((int) $fixed->id)];
        $this->assertSame([], $fixedinfo->flags);
        $this->assertSame((int) $fixed->id, $fixedinfo->questionid);
        $this->assertTrue($fixedinfo->quizzes[0]->linked);
        $this->assertFalse($fixedinfo->quizzes[0]->random);

        $c2 = $overview[$this->comps['c2']];
        $this->assertTrue($c2->questiononly);
        $this->assertEqualsCanonicalizing([api::FLAG_NOTLINKEDATQUIZ, api::FLAG_RANDOM], $c2->questions[0]->flags);
        $this->assertTrue($c2->questions[0]->quizzes[0]->random);

        $c3 = $overview[$this->comps['c3']];
        $this->assertSame([api::FLAG_NOTINCOURSE], $c3->flags);
        $this->assertSame([api::FLAG_NOTLINKEDATQUIZ], $c3->questions[0]->flags);
    }

    public function test_backup_and_restore(): void {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        $CFG->backup_file_logger_level = \backup::LOG_NONE;

        $question = $this->create_question();
        api::set_competencies(api::get_entryid((int) $question->id), [$this->comps['c1']]);
        $this->create_quiz([$question]);

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $this->course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id
        );
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        $newcourse = $this->getDataGenerator()->create_course();
        $rc = new \restore_controller(
            $backupid,
            $newcourse->id,
            \backup::INTERACTIVE_NO,
            \backup::MODE_IMPORT,
            $USER->id,
            \backup::TARGET_EXISTING_ADDING
        );
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        global $DB;
        $newentries = $DB->get_fieldset_sql("SELECT qbe.id
                                               FROM {question_bank_entries} qbe
                                               JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                                               JOIN {context} ctx ON ctx.id = qc.contextid
                                               JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :lvl
                                              WHERE cm.course = :course", ['lvl' => CONTEXT_MODULE, 'course' => $newcourse->id]);
        $this->assertNotEmpty($newentries);
        $mapped = api::get_for_entries($newentries);
        $this->assertCount(1, $mapped);
        $this->assertSame([$this->comps['c1']], array_keys(reset($mapped)));
    }

    public function test_question_bank_column(): void {
        global $CFG;
        require_once($CFG->dirroot . '/question/editlib.php');
        $question = $this->getDataGenerator()->get_plugin_generator('core_question')
            ->create_question('truefalse', null, ['category' => $this->category->id, 'name' => 'Mapped question']);
        api::set_competencies(api::get_entryid((int) $question->id), [$this->comps['c1']]);

        $context = \context::instance_by_id($this->category->contextid);
        $cm = get_coursemodule_from_id('qbank', $context->instanceid);
        $params = ['qpage' => 0, 'qperpage' => 20, 'cat' => $this->category->id . ',' . $context->id, 'recurse' => false,
            'qbshowtext' => false, 'tabname' => 'questions'];
        $view = new \core_question\local\bank\view(
            new \core_question\local\bank\question_edit_contexts($context),
            new \moodle_url('/question/edit.php', ['cmid' => $cm->id]),
            $this->course,
            $cm,
            $params,
            ['cmid' => $cm->id]
        );
        ob_start();
        $view->display();
        $html = ob_get_clean();
        $this->assertStringContainsString('Mapped question', $html);
        $this->assertStringContainsString('>C1</span>', $html);
        $this->assertStringContainsString('Edit competencies', $html);
        $this->assertStringContainsString('/question/bank/competencies/edit.php?id=' . $question->id, $html);
    }
}

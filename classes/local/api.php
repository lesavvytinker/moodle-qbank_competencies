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

namespace qbank_competencies\local;

use context;
use moodle_url;
use stdClass;

/**
 * Question-to-competency mappings, and what other plugins need from them.
 *
 * A mapping belongs to a question bank entry (every version of the question), so editing a question keeps it.
 * Mappings are supporting evidence only: nothing here rates a competency or writes competency evidence.
 *
 * Used by Evidence Reporter ({@see get_user_results()}) and Mapping tracker ({@see get_course_overview()}).
 *
 * @package    qbank_competencies
 * @copyright  2026 Daniel Maloney
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {
    /** @var string The mapping table. */
    public const TABLE = 'qbank_competencies';

    /** Question passed: its mark met the pass threshold. */
    public const PASSED = 'passed';
    /** Question not passed: marked below the threshold, or given up. */
    public const NOTPASSED = 'notpassed';
    /** Question waiting for manual marking. */
    public const PENDING = 'pending';
    /** Question not answered. */
    public const NOTANSWERED = 'notanswered';

    /** Flag: the question isn't in any quiz in the course. */
    public const FLAG_NOTUSED = 'notused';
    /** Flag: a quiz uses the question but doesn't have the competency in its own competency settings. */
    public const FLAG_NOTLINKEDATQUIZ = 'notlinkedatquiz';
    /** Flag: the question is only drawn at random, so not every learner gets it. */
    public const FLAG_RANDOM = 'random';
    /** Flag: the competency isn't one of the course's competencies, so no activity can be linked to it yet. */
    public const FLAG_NOTINCOURSE = 'notincourse';

    /**
     * Are competencies enabled on the site?
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        return \core_competency\api::is_enabled();
    }

    /**
     * The question bank entry of a question (any version).
     *
     * @param int $questionid
     * @return int
     */
    public static function get_entryid(int $questionid): int {
        global $DB;
        return (int) $DB->get_field('question_versions', 'questionbankentryid', ['questionid' => $questionid], MUST_EXIST);
    }

    /**
     * Competencies mapped to question bank entries.
     *
     * @param int[] $entryids
     * @return array entryid => [competencyid => stdClass (id, idnumber, shortname, competencyframeworkid)]
     */
    public static function get_for_entries(array $entryids): array {
        global $DB;
        $entryids = array_values(array_unique(array_filter(array_map('intval', $entryids))));
        if (!$entryids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($entryids, SQL_PARAMS_NAMED, 'qce');
        $sql = "SELECT qcm.id AS mappingid, qcm.questionbankentryid, c.id, c.idnumber, c.shortname, c.competencyframeworkid
                  FROM {" . self::TABLE . "} qcm
                  JOIN {competency} c ON c.id = qcm.competencyid
                 WHERE qcm.questionbankentryid {$insql}
              ORDER BY c.competencyframeworkid, c.path, c.sortorder, c.id";
        $result = [];
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $row) {
            $entryid = (int) $row->questionbankentryid;
            unset($row->mappingid, $row->questionbankentryid);
            $result[$entryid][(int) $row->id] = $row;
        }
        $rs->close();
        return $result;
    }

    /**
     * IDs of the competencies mapped to one entry.
     *
     * @param int $entryid
     * @return int[]
     */
    public static function get_competencyids(int $entryid): array {
        global $DB;
        return array_map('intval', $DB->get_fieldset_select(
            self::TABLE,
            'competencyid',
            'questionbankentryid = ?',
            [$entryid]
        ));
    }

    /**
     * Replace the competencies mapped to an entry.
     *
     * @param int $entryid
     * @param int[] $competencyids
     * @return array [added int[], removed int[]]
     */
    public static function set_competencies(int $entryid, array $competencyids): array {
        global $DB;
        $wanted = array_values(array_unique(array_filter(array_map('intval', $competencyids))));
        $current = self::get_competencyids($entryid);
        $add = array_values(array_diff($wanted, $current));
        $remove = array_values(array_diff($current, $wanted));
        if ($add) {
            // Only competencies that exist.
            $add = array_values(array_intersect($add, array_map('intval', array_keys(
                $DB->get_records_list('competency', 'id', $add, '', 'id')
            ))));
        }

        $transaction = $DB->start_delegated_transaction();
        if ($remove) {
            [$insql, $params] = $DB->get_in_or_equal($remove, SQL_PARAMS_NAMED, 'qcr');
            $DB->delete_records_select(
                self::TABLE,
                "questionbankentryid = :entryid AND competencyid {$insql}",
                ['entryid' => $entryid] + $params
            );
        }
        $now = time();
        foreach ($add as $competencyid) {
            $DB->insert_record(self::TABLE, (object) ['questionbankentryid' => $entryid, 'competencyid' => $competencyid,
                'timecreated' => $now]);
        }
        $transaction->allow_commit();
        return [$add, $remove];
    }

    /**
     * Add one mapping if it isn't there already (used by restore).
     *
     * @param int $entryid
     * @param int $competencyid
     */
    public static function add_competency(int $entryid, int $competencyid): void {
        global $DB;
        if (!$DB->record_exists(self::TABLE, ['questionbankentryid' => $entryid, 'competencyid' => $competencyid])) {
            $DB->insert_record(self::TABLE, (object) ['questionbankentryid' => $entryid, 'competencyid' => $competencyid,
                'timecreated' => time()]);
        }
    }

    /**
     * Remove mappings whose question bank entry or competency has been deleted.
     */
    public static function delete_orphans(): void {
        global $DB;
        $ids = $DB->get_fieldset_sql("SELECT qcm.id
                                        FROM {" . self::TABLE . "} qcm
                                   LEFT JOIN {question_bank_entries} qbe ON qbe.id = qcm.questionbankentryid
                                   LEFT JOIN {competency} c ON c.id = qcm.competencyid
                                       WHERE qbe.id IS NULL OR c.id IS NULL");
        foreach (array_chunk($ids, 1000) as $chunk) {
            $DB->delete_records_list(self::TABLE, 'id', $chunk);
        }
    }

    /**
     * The course a question bank belongs to.
     *
     * @param context $bankcontext Context of the question category
     * @return int|null
     */
    public static function get_bank_courseid(context $bankcontext): ?int {
        $coursecontext = $bankcontext->get_course_context(false);
        return $coursecontext ? (int) $coursecontext->instanceid : null;
    }

    /**
     * Competencies a question in this bank can be mapped to: the course's competencies (as for activities), plus any
     * already mapped to it, so they aren't dropped without notice.
     *
     * @param context $bankcontext
     * @param int[] $current Competencies already mapped
     * @return array competencyid => label
     */
    public static function get_options(context $bankcontext, array $current = []): array {
        global $DB;
        $options = [];
        $courseid = self::get_bank_courseid($bankcontext);
        $ids = [];
        if ($courseid) {
            foreach ($DB->get_records('competency_coursecomp', ['courseid' => $courseid], 'sortorder', 'competencyid') as $cc) {
                $ids[] = (int) $cc->competencyid;
            }
        }
        $all = array_values(array_unique(array_merge($ids, array_map('intval', $current))));
        if (!$all) {
            return [];
        }
        $syscontext = \context_system::instance();
        $records = $DB->get_records_list('competency', 'id', $all, '', 'id, idnumber, shortname');
        foreach ($all as $id) {
            if (!isset($records[$id])) {
                continue;
            }
            $label = self::label($records[$id], $syscontext);
            if (!in_array($id, $ids)) {
                $label = get_string('notincourselabel', 'qbank_competencies', $label);
            }
            $options[$id] = $label;
        }
        return $options;
    }

    /**
     * How a competency is shown: its ID number and name, as plain text (escape it for HTML).
     *
     * @param stdClass $competency With idnumber and shortname
     * @param context|null $context
     * @return string
     */
    public static function label(stdClass $competency, ?context $context = null): string {
        $name = format_string(
            $competency->shortname,
            true,
            ['context' => $context ?? \context_system::instance(), 'escape' => false]
        );
        $idnumber = trim((string) $competency->idnumber);
        return $idnumber !== '' && $idnumber !== $name ? $idnumber . ' ' . $name : $name;
    }

    /**
     * The mark (fraction of the question's marks) a question needs to count as passed.
     *
     * @return float
     */
    public static function get_pass_fraction(): float {
        $value = get_config('qbank_competencies', 'passfraction');
        return ($value === false || $value === '') ? 1.0 : max(0.0, min(1.0, (float) $value));
    }

    /**
     * The outcome of one question in an attempt.
     *
     * @param float|string|null $fraction The fraction from the question's latest step
     * @param string $state The question's latest state (e.g. gradedright, needsgrading)
     * @return string One of the PASSED, NOTPASSED, PENDING, NOTANSWERED constants
     */
    public static function outcome($fraction, string $state): string {
        if ($fraction === null || $fraction === '') {
            if ($state === 'needsgrading') {
                return self::PENDING;
            }
            if (in_array($state, ['gaveup', 'mangaveup'])) {
                return self::NOTPASSED;
            }
            return self::NOTANSWERED;
        }
        return ((float) $fraction + 0.0000001 >= self::get_pass_fraction()) ? self::PASSED : self::NOTPASSED;
    }

    /**
     * One learner's results on mapped questions in finished quiz attempts (previews excluded), oldest first.
     *
     * Every quiz is included, whether or not the quiz itself is linked to the competency.
     *
     * @param int $userid
     * @param int[]|null $competencyids Limit to these competencies
     * @param array|null $condition Extra [SQL, params] restriction; aliases available: qza (quiz_attempts), cm
     *                              (course_modules), qcm (the mapping)
     * @return stdClass[] competencyid, cmid, courseid, quizid, attemptid, attemptno, timefinish, sumgrades, slot,
     *                    questionid, entryid, questionname, maxmark, fraction, state, mark (null if not marked),
     *                    outcome (see {@see outcome()}), linkedatquiz (bool), islatest (bool)
     */
    public static function get_user_results(int $userid, ?array $competencyids = null, ?array $condition = null): array {
        global $DB;
        if ($competencyids !== null && !$competencyids) {
            return [];
        }
        $params = ['userid' => $userid];
        $where = "qza.userid = :userid AND qza.state = 'finished' AND qza.preview = 0";
        if ($competencyids !== null) {
            [$insql, $inparams] = $DB->get_in_or_equal(array_map('intval', $competencyids), SQL_PARAMS_NAMED, 'qcc');
            $where .= " AND qcm.competencyid {$insql}";
            $params += $inparams;
        }
        if ($condition) {
            $where .= ' AND (' . $condition[0] . ')';
            $params += $condition[1];
        }
        $sql = "SELECT qcm.competencyid, cm.id AS cmid, cm.course AS courseid, qz.id AS quizid, qza.id AS attemptid,
                       qza.attempt AS attemptno, qza.timefinish, qza.sumgrades, qat.slot, qat.questionid,
                       qv.questionbankentryid AS entryid, q.name AS questionname, qat.maxmark, qas.fraction, qas.state,
                       CASE WHEN EXISTS (SELECT 1 FROM {competency_modulecomp} qmc
                                          WHERE qmc.cmid = cm.id AND qmc.competencyid = qcm.competencyid)
                            THEN 1 ELSE 0 END AS linkedatquiz,
                       CASE WHEN qza.attempt = (SELECT MAX(qza2.attempt) FROM {quiz_attempts} qza2
                                                 WHERE qza2.quiz = qza.quiz AND qza2.userid = qza.userid
                                                   AND qza2.state = 'finished' AND qza2.preview = 0)
                            THEN 1 ELSE 0 END AS islatest
                  FROM {quiz_attempts} qza
                  JOIN {quiz} qz ON qz.id = qza.quiz
                  JOIN {modules} m ON m.name = 'quiz'
                  JOIN {course_modules} cm ON cm.module = m.id AND cm.instance = qz.id
                  JOIN {question_attempts} qat ON qat.questionusageid = qza.uniqueid
                  JOIN {question_versions} qv ON qv.questionid = qat.questionid
                  JOIN {" . self::TABLE . "} qcm ON qcm.questionbankentryid = qv.questionbankentryid
                  JOIN {question} q ON q.id = qat.questionid
                  JOIN {question_attempt_steps} qas ON qas.questionattemptid = qat.id
                       AND qas.sequencenumber = (SELECT MAX(qas2.sequencenumber) FROM {question_attempt_steps} qas2
                                                  WHERE qas2.questionattemptid = qat.id)
                 WHERE {$where}
              ORDER BY cm.course, cm.id, qza.attempt, qat.slot, qcm.competencyid";
        $result = [];
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $row) {
            foreach (
                ['competencyid', 'cmid', 'courseid', 'quizid', 'attemptid', 'attemptno', 'slot', 'questionid',
                    'entryid'] as $field
            ) {
                $row->$field = (int) $row->$field;
            }
            $row->maxmark = (float) $row->maxmark;
            $row->fraction = $row->fraction === null ? null : (float) $row->fraction;
            $row->mark = $row->fraction === null ? null : $row->fraction * $row->maxmark;
            $row->outcome = self::outcome($row->fraction, (string) $row->state);
            $row->linkedatquiz = (bool) $row->linkedatquiz;
            $row->islatest = (bool) $row->islatest;
            $result[] = $row;
        }
        $rs->close();
        return $result;
    }

    /**
     * Link to one question in a learner's attempt (the quiz's own rules decide what the viewer may see).
     *
     * @param int $attemptid
     * @param int $slot
     * @return moodle_url
     */
    public static function review_url(int $attemptid, int $slot): moodle_url {
        return new moodle_url('/mod/quiz/reviewquestion.php', ['attempt' => $attemptid, 'slot' => $slot]);
    }

    /**
     * Link to comment on or override the mark of one question in an attempt.
     *
     * @param int $attemptid
     * @param int $slot
     * @return moodle_url
     */
    public static function comment_url(int $attemptid, int $slot): moodle_url {
        return new moodle_url('/mod/quiz/comment.php', ['attempt' => $attemptid, 'slot' => $slot]);
    }

    /**
     * Link to preview a question.
     *
     * @param int $questionid
     * @param context $bankcontext
     * @return moodle_url
     */
    public static function preview_url(int $questionid, context $bankcontext): moodle_url {
        return \qbank_previewquestion\helper::question_preview_url($questionid, null, null, null, null, $bankcontext);
    }

    /**
     * Link to edit a question's competencies.
     *
     * @param int $questionid
     * @param moodle_url|string|null $returnurl
     * @return moodle_url
     */
    public static function edit_url(int $questionid, $returnurl = null): moodle_url {
        $params = ['id' => $questionid];
        if ($returnurl) {
            $params['returnurl'] = $returnurl instanceof moodle_url ? $returnurl->out_as_local_url(false) : $returnurl;
        }
        return new moodle_url('/question/bank/competencies/edit.php', $params);
    }

    /**
     * Mapped questions in a course's question banks (its question banks and its quizzes' own banks), for course
     * builders: where each question is used in the course's quizzes, and what still needs doing.
     *
     * Questions are supporting evidence only: they never count as an activity instance of a competency.
     *
     * @param int $courseid
     * @return array competencyid => stdClass:
     *     activitycmids int[] (activities in the course linked to the competency),
     *     incourse bool (the competency is one of the course's competencies),
     *     questiononly bool (mapped to questions but no activity in the course is linked to it),
     *     flags string[] (FLAG_NOTINCOURSE),
     *     questions stdClass[]: entryid, questionid (latest version), name, contextid,
     *         quizzes stdClass[] (cmid, random bool, linked bool), flags string[] (FLAG_* constants)
     */
    public static function get_course_overview(int $courseid): array {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/question/engine/lib.php');

        // Mapped entries whose question category is in one of the course's banks.
        $params = ['modlevel' => CONTEXT_MODULE, 'courselevel' => CONTEXT_COURSE, 'c1' => $courseid, 'c2' => $courseid];
        $sql = "SELECT qcm.id, qcm.competencyid, qcm.questionbankentryid AS entryid, qc.contextid
                  FROM {" . self::TABLE . "} qcm
                  JOIN {question_bank_entries} qbe ON qbe.id = qcm.questionbankentryid
                  JOIN {question_categories} qc ON qc.id = qbe.questioncategoryid
                  JOIN {context} ctx ON ctx.id = qc.contextid
                 WHERE (ctx.contextlevel = :modlevel
                        AND ctx.instanceid IN (SELECT bcm.id FROM {course_modules} bcm WHERE bcm.course = :c1))
                    OR (ctx.contextlevel = :courselevel AND ctx.instanceid = :c2)";
        $mappings = $DB->get_records_sql($sql, $params);
        if (!$mappings) {
            return [];
        }
        $entryids = array_values(array_unique(array_map(fn($m) => (int) $m->entryid, $mappings)));

        // Latest version of each entry, for its name and links.
        [$insql, $inparams] = $DB->get_in_or_equal($entryids, SQL_PARAMS_NAMED, 'qle');
        $latest = $DB->get_records_sql(
            "SELECT qv.questionbankentryid, q.id, q.name
                                          FROM {question_versions} qv
                                          JOIN {question} q ON q.id = qv.questionid
                                         WHERE qv.questionbankentryid {$insql}
                                           AND qv.version = (SELECT MAX(qv2.version) FROM {question_versions} qv2
                                                              WHERE qv2.questionbankentryid = qv.questionbankentryid)",
            $inparams
        );

        // Where the entries are used in the course's quizzes: fixed slots, and random slots that can draw them.
        $usage = [];
        $quizmodule = (int) $DB->get_field('modules', 'id', ['name' => 'quiz']);
        $slotsql = "FROM {quiz_slots} qs
                    JOIN {quiz} qz ON qz.id = qs.quizid
                    JOIN {course_modules} cm ON cm.instance = qz.id AND cm.module = :quizmodule
                    JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :modlevel";
        $slotparams = ['quizmodule' => $quizmodule, 'modlevel' => CONTEXT_MODULE, 'courseid' => $courseid];
        $fixed = $DB->get_recordset_sql(
            "SELECT qs.id AS slotid, qr.questionbankentryid AS entryid, cm.id AS cmid
                                           {$slotsql}
                                           JOIN {question_references} qr ON qr.usingcontextid = ctx.id
                                                AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
                                                AND qr.itemid = qs.id
                                          WHERE qz.course = :courseid AND qr.questionbankentryid {$insql}",
            $slotparams + $inparams
        );
        foreach ($fixed as $row) {
            $usage[(int) $row->entryid][(int) $row->cmid] = false;
        }
        $fixed->close();

        $randoms = $DB->get_records_sql("SELECT qs.id AS slotid, qsr.filtercondition, cm.id AS cmid
                                           {$slotsql}
                                           JOIN {question_set_references} qsr ON qsr.usingcontextid = ctx.id
                                                AND qsr.component = 'mod_quiz' AND qsr.questionarea = 'slot'
                                                AND qsr.itemid = qs.id
                                          WHERE qz.course = :courseid", $slotparams);
        if ($randoms) {
            $entrybyquestion = [];
            $loaded = [];
            $loader = new \core_question\local\bank\random_question_loader(new \qubaid_list([]));
            foreach ($randoms as $random) {
                $condition = json_decode((string) $random->filtercondition, true);
                if (empty($condition['filter'])) {
                    continue;
                }
                $key = md5(json_encode($condition['filter']));
                if (!isset($loaded[$key])) {
                    try {
                        $questionids = array_keys($loader->get_filtered_questions($condition['filter'], 0, 0, ['id']));
                    } catch (\Throwable $e) {
                        $questionids = [];
                    }
                    $loaded[$key] = self::entries_for_questions($questionids, $entrybyquestion);
                }
                foreach ($loaded[$key] as $entryid) {
                    if (in_array($entryid, $entryids) && !isset($usage[$entryid][(int) $random->cmid])) {
                        $usage[$entryid][(int) $random->cmid] = true;
                    }
                }
            }
        }

        // Activities in the course linked to each competency, and the course's competencies.
        $competencyids = array_values(array_unique(array_map(fn($m) => (int) $m->competencyid, $mappings)));
        [$cinsql, $cinparams] = $DB->get_in_or_equal($competencyids, SQL_PARAMS_NAMED, 'qlc');
        $activitylinks = [];
        $rs = $DB->get_recordset_sql("SELECT mc.id, mc.competencyid, mc.cmid
                                        FROM {competency_modulecomp} mc
                                        JOIN {course_modules} cm ON cm.id = mc.cmid
                                       WHERE cm.course = :courseid AND cm.deletioninprogress = 0
                                         AND mc.competencyid {$cinsql}", ['courseid' => $courseid] + $cinparams);
        foreach ($rs as $link) {
            $activitylinks[(int) $link->competencyid][(int) $link->cmid] = (int) $link->cmid;
        }
        $rs->close();
        $incourse = array_map('intval', $DB->get_fieldset_select(
            'competency_coursecomp',
            'competencyid',
            "courseid = :courseid AND competencyid {$cinsql}",
            ['courseid' => $courseid] + $cinparams
        ));

        $result = [];
        foreach ($competencyids as $competencyid) {
            $cmids = array_values($activitylinks[$competencyid] ?? []);
            $result[$competencyid] = (object) [
                'activitycmids' => $cmids,
                'incourse' => in_array($competencyid, $incourse),
                'questiononly' => !$cmids,
                'flags' => in_array($competencyid, $incourse) ? [] : [self::FLAG_NOTINCOURSE],
                'questions' => [],
            ];
        }
        foreach ($mappings as $mapping) {
            $entryid = (int) $mapping->entryid;
            $competencyid = (int) $mapping->competencyid;
            $quizzes = [];
            foreach ($usage[$entryid] ?? [] as $cmid => $random) {
                $quizzes[] = (object) ['cmid' => $cmid, 'random' => $random,
                    'linked' => isset($activitylinks[$competencyid][$cmid])];
            }
            $flags = [];
            if (!$quizzes) {
                $flags[] = self::FLAG_NOTUSED;
            }
            if (array_filter($quizzes, fn($q) => !$q->linked)) {
                $flags[] = self::FLAG_NOTLINKEDATQUIZ;
            }
            if ($quizzes && !array_filter($quizzes, fn($q) => !$q->random)) {
                $flags[] = self::FLAG_RANDOM;
            }
            $result[$competencyid]->questions[] = (object) [
                'entryid' => $entryid,
                'questionid' => isset($latest[$entryid]) ? (int) $latest[$entryid]->id : 0,
                'name' => isset($latest[$entryid]) ? $latest[$entryid]->name : '',
                'contextid' => (int) $mapping->contextid,
                'quizzes' => $quizzes,
                'flags' => $flags,
            ];
        }
        return $result;
    }

    /**
     * Question bank entries of the given questions.
     *
     * @param int[] $questionids
     * @param array $cache questionid => entryid, filled as it goes
     * @return int[] Entry IDs
     */
    private static function entries_for_questions(array $questionids, array &$cache): array {
        global $DB;
        $missing = array_values(array_diff(array_map('intval', $questionids), array_keys($cache)));
        foreach (array_chunk($missing, 1000) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED, 'qeq');
            foreach (
                $DB->get_records_select(
                    'question_versions',
                    "questionid {$insql}",
                    $params,
                    '',
                    'questionid, questionbankentryid'
                ) as $version
            ) {
                $cache[(int) $version->questionid] = (int) $version->questionbankentryid;
            }
        }
        $entries = [];
        foreach ($questionids as $questionid) {
            if (isset($cache[(int) $questionid])) {
                $entries[] = $cache[(int) $questionid];
            }
        }
        return array_values(array_unique($entries));
    }
}

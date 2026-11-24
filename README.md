# Question competencies (qbank_competencies)

Map individual questions in the Moodle question bank to competencies, as **supporting evidence**.

Moodle links competencies to activities (a whole quiz), not to questions. This plugin adds the missing link, so you can see which questions give evidence of which performance criteria, and what each learner did on them. It never rates a competency or writes competency evidence: the quiz still has to be linked to the competency in its own settings, and the assessor makes the judgement.

Supports Moodle 5.0, 5.1 and 5.2. Licensed under the GNU GPL v3 or later.

## Install

1. Copy the `competencies` folder into `question/bank/` (on Moodle 5.1+ that is `public/question/bank/`).
2. Visit *Site administration → Notifications*.
3. Competencies must be enabled (*Site administration → Competencies → Competencies settings*).

## Use

In any question bank, the **Competencies** column shows each question's competencies (ID numbers, with the full name on hover). Choose **Add competencies** or **Edit competencies** in the column, or **Competencies** in the question's action menu, then pick from the course's competencies.

- The mapping belongs to the question, not one version, so editing the question keeps it.
- As with activities, you choose from the **course's competencies**. Add competencies to the course first (*More → Competencies*).
- Changes are logged as *Question competencies updated*.
- Mappings are copied with the question in backups, course copies and imports. On another site, competencies are matched by ID number within a framework with the same ID number; any that can't be found are skipped.
- Deleting the last version of a question, or deleting a competency, removes its mappings.

Who can map: anyone who can edit the question.

## Settings

*Site administration → Plugins → Question bank plugins → Question competencies*

| Setting | What it does |
|---|---|
| **Question counts as passed at** | The share of a question's marks a learner needs for it to show as passed (100% by default). Manually marked questions use the mark the marker gave. |

## Used by

- **Evidence Reporter** (`report_evidencereporter`) 0.23.0+: shows each learner's results on mapped questions under each competency, grouped by quiz attempt with the whole quiz's result, flags where the two disagree, and links to the learner's answer.
- **Mapping tracker** (`block_mappingtracker`): lists mapped questions under each competency for course builders, separately from activity links, with flags for what still needs doing.

## API for other plugins

`\qbank_competencies\local\api`:

| Method | Returns |
|---|---|
| `get_for_entries(array $entryids)` | Competencies mapped to question bank entries |
| `set_competencies(int $entryid, array $competencyids)` | Replaces an entry's competencies |
| `get_user_results(int $userid, ?array $competencyids, ?array $condition)` | A learner's results on mapped questions in finished quiz attempts: quiz, attempt, slot, mark, outcome (`passed`, `notpassed`, `pending`, `notanswered`), whether the quiz is linked to the competency, whether it's the latest attempt. `$condition` is an extra `[sql, params]` restriction using the aliases `qza` (quiz_attempts), `cm` and `qcm`. |
| `get_course_overview(int $courseid)` | For course builders: each competency's mapped questions in the course's banks, where they're used in the course's quizzes, and flags (see below) |
| `review_url()`, `comment_url()`, `preview_url()`, `edit_url()` | Links to a question in an attempt, its marking page, its preview and its competencies |

Flags from `get_course_overview()`:

| Flag | Meaning |
|---|---|
| `notused` | The question isn't in any quiz in the course |
| `notlinkedatquiz` | A quiz uses the question but doesn't have the competency in its competency settings |
| `random` | The question is only drawn at random, so not every learner gets it |
| `notincourse` (on the competency) | The competency isn't one of the course's competencies |

Each competency also has `activitycmids` (activities linked to it) and `questiononly` (mapped to questions, but no activity is linked to it, so it counts as 0 against any target).

## Tests

```
vendor/bin/phpunit public/question/bank/competencies/tests/api_test.php
```

## Licence

GNU GPL v3 or later. Copyright 2026 Daniel Maloney.

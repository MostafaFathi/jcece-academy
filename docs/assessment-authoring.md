# Assessment authoring — local implementation

The Course workspace now links to dedicated Quiz and Assignment editors. Admin and Instructor use the same authoring page and question builder; navigation and the server policy determine the visible actions. The Instructor's Course is contextual, and the lesson selector is populated from that Course's curriculum only. The separate Instructor result and submission/grading screens remain unchanged.

## Visible settings and existing API contract

| Editor | Visible setting/action | Backend contract |
| --- | --- | --- |
| Quiz | Title, description, instructions, lesson | `StoreQuizRequest` / `UpdateQuizRequest`; lesson must belong to the Course. |
| Quiz | Passing percentage, time limit, maximum attempts | `passing_score`, `time_limit_minutes`, `max_attempts`; null limits mean no limit. |
| Quiz | Shuffle questions/answers, result visibility, correct-answer visibility | `shuffle_questions`, `shuffle_answers`, `show_results`, `show_correct_answers`. |
| Quiz | Availability start/end | `available_from`, `available_until`. |
| Quiz | Questions, options, positive points, explanation | `QuizQuestionController` / `QuizQuestionService`. Types are `single_choice`, `multiple_choice`, `true_false`; single/true-false require exactly one correct option, multiple choice at least one. |
| Quiz | Add, edit, remove, reorder questions | Existing question resource endpoints and `questions/reorder`. |
| Quiz | Draft, publish, unpublish, archive | Quiz publication and delete endpoints; publication requires at least one valid question. |
| Assignment | Title, description, instructions, lesson | `StoreAssignmentRequest` / `UpdateAssignmentRequest`; lesson must belong to the Course. |
| Assignment | Submission type, maximum/passing score, maximum attempts | `submission_type` (`text`, `file`, `text_and_file`), `maximum_score`, `passing_score`, `max_attempts`. |
| Assignment | Availability start, due date, late submissions | `available_from`, `due_at`, `allow_late_submissions`. |
| Assignment | Private attachment upload/download/remove | Existing `assignments.attachments` and protected download endpoints. Filename and size are shown; storage paths are not. |
| Assignment | Draft, publish, unpublish, archive | Existing assignment publication and delete endpoints. |

The form's unsaved-change warning protects local details, question edits, and selected attachments. It cannot recover unsaved content after a browser crash. Save a draft before adding questions or attachments. Server-side validation and policies remain authoritative. API responses now include effective capability flags for the editor and question counts for Instructor Quiz cards.

## Still not exposed from the backend

- Admin Quiz attempt/result review is available through API, but the new Admin assessment list does not include a dedicated results screen. Instructor results remain linked from the Course workspace and authoring page.
- Admin Assignment submissions/grading APIs exist, but the new Admin assessment list does not include a dedicated grading screen. Instructor submissions and grading remain linked and separate.
- The API supports arbitrary text for the two True/False options. The authoring UI creates explicit canonical `True`/`False` options; existing option labels are preserved when editing. It does not offer a separate label-localization control.

## Desirable features not implemented by the backend

Question banks/import, rich-text or media-based questions, per-option feedback, rubrics, automatic essay grading, peer review, and granular draft autosave are not part of the current assessment engine and were not added here.

## Manual local QA checklist (use non-production Course/test accounts)

1. Sign in as an Instructor assigned to a test Course with assessment permissions. Open its workspace and create a Quiz. Set title, lesson, instructions, passing score, attempts, time limit, visibility, and dates; save the draft.
2. Add one Single Choice question with exactly one correct answer, one Multiple Choice with two correct answers, and one True/False question. Edit an option, reorder questions, and confirm an unsaved edit warns before leaving.
3. Publish. Sign in as an enrolled Student with Course access. Open the Quiz, start an attempt, answer all three types, submit, and verify the score and result/correct-answer visibility matches the Quiz settings.
4. Return as the Instructor; open Results and inspect the submitted attempt. Confirm a different Instructor cannot view or edit the test Quiz.
5. Create an Assignment from the same Course workspace. Choose a lesson and submission type, add instructions, maximum/passing score, attempts, due date, and late setting; save. Upload a real, harmless test PDF and verify its private download.
6. Publish. As the enrolled Student, create a draft, upload a real harmless submission file, then submit. As Instructor open Submissions, download the file, grade it, and inspect the grading history.
7. For a separate submitted attempt, request a revision. As Student create and submit the new attempt; confirm the earlier attempt remains unchanged. Confirm an unassigned Instructor cannot view or grade either submission.
8. Repeat authoring at 320, 390, 768, and at least 1280 px in Arabic and English. Check that question options, attachments, status actions, and lesson selector remain usable without horizontal overflow.

This is local automated coverage plus a manual QA plan, not staging acceptance. No permanent test records are created by this checklist automatically.

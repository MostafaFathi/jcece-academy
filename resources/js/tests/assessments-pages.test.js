import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import i18n, { setLocale } from '../i18n';
import * as api from '../api/assessments';
import QuizPage from '../pages/QuizPage.vue';
import AssignmentPage from '../pages/AssignmentPage.vue';
import CertificatesPage from '../pages/CertificatesPage.vue';
import CertificateVerificationPage from '../pages/CertificateVerificationPage.vue';
import CourseAssessments from '../components/learning/CourseAssessments.vue';

vi.mock('../api/assessments', () => Object.fromEntries([
    'fetchQuizzes', 'fetchQuiz', 'fetchQuizAttempts', 'startQuizAttempt', 'fetchQuizAttempt', 'fetchQuizResult', 'saveQuizAnswers', 'submitQuizAttempt',
    'fetchAssignments', 'fetchAssignment', 'fetchAssignmentSubmissions', 'startAssignmentDraft', 'fetchAssignmentSubmission', 'saveAssignmentDraft', 'uploadAssignmentFiles', 'deleteAssignmentFile', 'submitAssignment', 'downloadAssignmentAttachment', 'downloadSubmissionFile',
    'fetchCertificates', 'fetchCertificate', 'fetchCertificateEligibility', 'issueCertificate', 'downloadCertificate', 'verifyCertificate',
].map((name) => [name, vi.fn()])));

const quiz = { id: 3, course_id: 2, lesson_id: 9, title: 'Safety quiz', instructions: 'Choose carefully', max_attempts: 2, available_until: null };
const questions = [
    { id: 10, type: 'single_choice', question_text: 'Single?', points: '1.00', selected_option_ids: [], options: [{ id: 1, answer_text: 'One' }, { id: 2, answer_text: 'Two' }] },
    { id: 11, type: 'multiple_choice', question_text: 'Multiple?', points: '1.00', selected_option_ids: [], options: [{ id: 3, answer_text: 'Three' }, { id: 4, answer_text: 'Four' }] },
    { id: 12, type: 'true_false', question_text: 'True?', points: '1.00', selected_option_ids: [], options: [{ id: 5, answer_text: 'True' }, { id: 6, answer_text: 'False' }] },
];
const attempt = (extra = {}) => ({ id: 20, quiz_id: 3, attempt_number: 1, status: 'in_progress', expires_at: new Date(Date.now() + 60000).toISOString(), questions, ...extra });
const assignment = (extra = {}) => ({ id: 8, course_id: 2, lesson_id: 9, title: 'Safety assignment', instructions: 'Submit safely', submission_type: 'text_and_file', maximum_score: '10.00', passing_score: '6.00', max_attempts: 2, due_at: null, allow_late_submissions: false, attachments: [{ id: 4, original_filename: 'brief.pdf' }], ...extra });
const draft = (extra = {}) => ({ id: 30, assignment_id: 8, attempt_number: 1, status: 'draft', submission_type: 'text_and_file', text_answer: '', files: [], ...extra });
const certificate = (extra = {}) => ({ id: 5, certificate_number: 'JCEC-TEST', course_title: 'Safety Course', student_name: 'Student', status: 'issued', issued_at: '2026-09-30T00:00:00Z', ...extra });
function render(component, props = {}) { return mount(component, { props, global: { plugins: [i18n], stubs: { RouterLink: { props: ['to'], template: '<a><slot /></a>' } } } }); }
function deferred() { let resolve; const promise = new Promise((yes) => { resolve = yes; }); return { promise, resolve }; }
function button(wrapper, text) { return wrapper.findAll('button').find((item) => item.text().includes(text)); }
beforeEach(() => {
    vi.resetAllMocks(); setLocale('en'); vi.spyOn(window, 'confirm').mockReturnValue(true);
    api.fetchQuiz.mockResolvedValue(quiz); api.fetchQuizAttempts.mockResolvedValue([]); api.fetchQuizAttempt.mockResolvedValue(attempt()); api.startQuizAttempt.mockResolvedValue(attempt()); api.saveQuizAnswers.mockResolvedValue(attempt()); api.submitQuizAttempt.mockResolvedValue(attempt({ status: 'submitted' })); api.fetchQuizResult.mockResolvedValue(attempt({ status: 'submitted' }));
    api.fetchAssignment.mockResolvedValue(assignment()); api.fetchAssignmentSubmissions.mockResolvedValue([]); api.fetchAssignmentSubmission.mockResolvedValue(draft()); api.startAssignmentDraft.mockResolvedValue(draft()); api.saveAssignmentDraft.mockResolvedValue(draft({ text_answer: 'My answer' })); api.uploadAssignmentFiles.mockResolvedValue(draft({ files: [{ id: 7, original_filename: 'answer.txt' }] })); api.submitAssignment.mockResolvedValue(draft({ status: 'submitted' }));
    api.fetchCertificates.mockResolvedValue({ items: [certificate()], meta: null }); api.fetchCertificateEligibility.mockResolvedValue({ eligible: false, reasons: ['lessons_incomplete'], certificate_status: null }); api.issueCertificate.mockResolvedValue(certificate()); api.verifyCertificate.mockResolvedValue({ status: 'issued', certificate_number: 'JCEC-TEST', student_name: 'Student', course_title: 'Safety Course', issued_at: '2026-09-30T00:00:00Z' });
    api.fetchQuizzes.mockResolvedValue([quiz]); api.fetchAssignments.mockResolvedValue([assignment()]);
});

describe('student quizzes', () => {
    it('starts only an available attempt and renders the three real question types', async () => {
        const wrapper = render(QuizPage, { id: 3 }); await flushPromises();
        expect(wrapper.text()).toContain('Start attempt');
        await button(wrapper, 'Start attempt').trigger('click'); await flushPromises();
        expect(api.startQuizAttempt).toHaveBeenCalledWith(3);
        expect(wrapper.text()).toContain('Single?'); expect(wrapper.text()).toContain('Multiple?'); expect(wrapper.text()).toContain('True?');
        expect(wrapper.findAll('input[type="checkbox"]')).toHaveLength(2);
        expect(wrapper.findAll('input[type="radio"]')).toHaveLength(4);
        expect(wrapper.find('[role="timer"]').text()).toMatch(/\d\d:\d\d/);
        wrapper.unmount();
    });
    it('saves exact multiple-choice set and handles submitted results only from the server', async () => {
        api.fetchQuizAttempts.mockResolvedValue([attempt()]);
        const wrapper = render(QuizPage, { id: 3 }); await flushPromises();
        await wrapper.findAll('input[type="checkbox"]')[0].setValue(true); await wrapper.findAll('input[type="checkbox"]')[1].setValue(true);
        await wrapper.findAll('input[type="radio"]')[0].setValue(true);
        await button(wrapper, 'Submit quiz').trigger('click'); await flushPromises();
        expect(api.saveQuizAnswers).toHaveBeenCalledWith(20, expect.arrayContaining([{ question_id: 11, option_ids: [3, 4] }]));
        expect(api.submitQuizAttempt).toHaveBeenCalledTimes(1);
        expect(wrapper.text()).toContain('Results are not available'); expect(wrapper.text()).not.toContain('Correct options');
        wrapper.unmount();
    });
    it('shows score and correct answers only when returned by the backend', async () => {
        api.fetchQuizAttempts.mockResolvedValue([attempt({ status: 'submitted' })]);
        api.fetchQuizAttempt.mockResolvedValue(attempt({ status: 'submitted', score: '2.00', maximum_score: '3.00', percentage: '66.67', passed: false, questions: [{ ...questions[0], earned_points: '1.00', correct_option_ids: [1], explanation: 'Because' }] }));
        const wrapper = render(QuizPage, { id: 3 }); await flushPromises();
        expect(wrapper.text()).toContain('2.00 / 3.00'); expect(wrapper.text()).toContain('Correct options: One'); expect(wrapper.text()).toContain('Because');
        wrapper.unmount();
    });
    it('blocks new attempts at the backend limit and handles expiry and access loss', async () => {
        api.fetchQuiz.mockResolvedValue({ ...quiz, max_attempts: 1 }); api.fetchQuizAttempts.mockResolvedValue([attempt({ status: 'expired' })]); api.fetchQuizAttempt.mockResolvedValue(attempt({ status: 'expired' }));
        const wrapper = render(QuizPage, { id: 3 }); await flushPromises();
        expect(button(wrapper, 'Attempt limit reached').attributes('disabled')).toBeDefined();
        wrapper.unmount();
        api.fetchQuiz.mockRejectedValue({ status: 403 });
        const denied = render(QuizPage, { id: 3 }); await flushPromises(); expect(denied.text()).toContain('Access is no longer available'); denied.unmount();
    });
    it('does not duplicate final submission while the server request is pending', async () => {
        api.fetchQuizAttempts.mockResolvedValue([attempt()]); const pending = deferred(); api.submitQuizAttempt.mockReturnValue(pending.promise);
        const wrapper = render(QuizPage, { id: 3 }); await flushPromises();
        await button(wrapper, 'Submit quiz').trigger('click'); await flushPromises();
        expect(button(wrapper, 'Submit quiz').attributes('disabled')).toBeDefined();
        pending.resolve(attempt({ status: 'submitted' })); await flushPromises();
        expect(api.submitQuizAttempt).toHaveBeenCalledTimes(1); wrapper.unmount();
    });
    it('refreshes server state after a rejected late answer save', async () => {
        api.fetchQuizAttempts.mockResolvedValue([attempt()]);
        api.saveQuizAnswers.mockRejectedValue({ status: 422, message: 'The time limit has expired.' });
        api.fetchQuizAttempt.mockResolvedValueOnce(attempt()).mockResolvedValueOnce(attempt({ status: 'expired' }));
        const wrapper = render(QuizPage, { id: 3 }); await flushPromises();
        await button(wrapper, 'Save answers').trigger('click'); await flushPromises();
        expect(wrapper.text()).toContain('Expired'); expect(api.submitQuizAttempt).not.toHaveBeenCalled(); wrapper.unmount();
    });
    it('does not save answers or submit if final confirmation is canceled', async () => {
        api.fetchQuizAttempts.mockResolvedValue([attempt()]); window.confirm.mockReturnValue(false);
        const wrapper = render(QuizPage, { id: 3 }); await flushPromises();
        await button(wrapper, 'Submit quiz').trigger('click'); await flushPromises();
        expect(api.saveQuizAnswers).not.toHaveBeenCalled(); expect(api.submitQuizAttempt).not.toHaveBeenCalled(); wrapper.unmount();
    });
    it('clears protected questions after access is lost during answer saving', async () => {
        api.fetchQuizAttempts.mockResolvedValue([attempt()]);
        api.saveQuizAnswers.mockRejectedValue({ status: 403, message: 'Access expired' });
        const wrapper = render(QuizPage, { id: 3 }); await flushPromises();
        expect(wrapper.text()).toContain('Single?');
        await button(wrapper, 'Save answers').trigger('click'); await flushPromises();
        expect(wrapper.text()).toContain('Access is no longer available');
        expect(wrapper.text()).not.toContain('Single?');
        expect(api.fetchQuizAttempt).toHaveBeenCalledTimes(1); wrapper.unmount();
    });
});

describe('student assignments', () => {
    it('shows details and downloads attachments through the protected helper', async () => {
        const wrapper = render(AssignmentPage, { id: 8 }); await flushPromises();
        expect(wrapper.text()).toContain('Safety assignment'); expect(wrapper.text()).toContain('Text and files'); expect(wrapper.text()).toContain('brief.pdf');
        await button(wrapper, 'Download').trigger('click'); await flushPromises();
        expect(api.downloadAssignmentAttachment).toHaveBeenCalledWith(4); wrapper.unmount();
    });
    it('creates a draft, saves text and uploads files separately from final submit', async () => {
        api.fetchAssignmentSubmissions.mockResolvedValue([draft()]);
        const wrapper = render(AssignmentPage, { id: 8 }); await flushPromises();
        await wrapper.get('textarea').setValue('My answer');
        const file = new File(['answer'], 'answer.txt', { type: 'text/plain' });
        const input = wrapper.get('input[type="file"]'); Object.defineProperty(input.element, 'files', { configurable: true, value: [file] }); await input.trigger('change');
        await button(wrapper, 'Save draft').trigger('click'); await flushPromises();
        expect(api.saveAssignmentDraft).toHaveBeenCalledWith(30, 'My answer');
        expect(api.uploadAssignmentFiles).toHaveBeenCalledWith(30, [file]);
        expect(api.submitAssignment).not.toHaveBeenCalled(); wrapper.unmount();
    });
    it('requires confirmation and guards duplicate final submissions', async () => {
        api.fetchAssignmentSubmissions.mockResolvedValue([draft()]); const pending = deferred(); api.submitAssignment.mockReturnValue(pending.promise);
        const wrapper = render(AssignmentPage, { id: 8 }); await flushPromises();
        await button(wrapper, 'Submit assignment').trigger('click'); await flushPromises();
        expect(button(wrapper, 'Submit assignment').attributes('disabled')).toBeDefined();
        pending.resolve(draft({ status: 'submitted', is_late: true })); await flushPromises();
        expect(api.submitAssignment).toHaveBeenCalledTimes(1); expect(wrapper.text()).toContain('Submitted'); expect(wrapper.text()).toContain('Submitted after the deadline'); wrapper.unmount();
    });
    it('shows revision feedback without editing history, then starts a distinct attempt', async () => {
        api.fetchAssignmentSubmissions.mockResolvedValue([draft({ status: 'revision_requested', feedback: 'Add sources' })]);
        api.fetchAssignmentSubmission.mockResolvedValue(draft({ status: 'revision_requested', feedback: 'Add sources' }));
        api.startAssignmentDraft.mockResolvedValue(draft({ id: 31, attempt_number: 2 }));
        const wrapper = render(AssignmentPage, { id: 8 }); await flushPromises();
        expect(wrapper.text()).toContain('Add sources'); expect(wrapper.get('textarea').attributes('readonly')).toBeDefined();
        await button(wrapper, 'Start a new revision attempt').trigger('click'); await flushPromises();
        expect(api.startAssignmentDraft).toHaveBeenCalledWith(8); expect(wrapper.text()).toContain('Attempt 2'); wrapper.unmount();
    });
    it('shows authoritative grade and refuses access errors', async () => {
        api.fetchAssignmentSubmissions.mockResolvedValue([draft({ status: 'graded' })]);
        api.fetchAssignmentSubmission.mockResolvedValue(draft({ status: 'graded', score: '8.00', maximum_score: '10.00', passed: true, feedback: 'Great', graded_at: '2026-09-30T00:00:00Z' }));
        const wrapper = render(AssignmentPage, { id: 8 }); await flushPromises();
        expect(wrapper.text()).toContain('8.00 / 10.00'); expect(wrapper.text()).toContain('Great'); wrapper.unmount();
        api.fetchAssignment.mockRejectedValue({ status: 403 }); const denied = render(AssignmentPage, { id: 8 }); await flushPromises(); expect(denied.text()).toContain('Access is no longer available'); denied.unmount();
    });
    it('supports file-only drafts and shows server validation without claiming a submit', async () => {
        api.fetchAssignment.mockResolvedValue(assignment({ submission_type: 'file' }));
        api.fetchAssignmentSubmissions.mockResolvedValue([draft({ submission_type: 'file' })]);
        api.fetchAssignmentSubmission.mockResolvedValue(draft({ submission_type: 'file' }));
        api.submitAssignment.mockRejectedValue({ status: 422, message: 'At least one file is required.' });
        const wrapper = render(AssignmentPage, { id: 8 }); await flushPromises();
        expect(wrapper.find('textarea').exists()).toBe(false);
        await button(wrapper, 'Submit assignment').trigger('click'); await flushPromises();
        expect(api.saveAssignmentDraft).not.toHaveBeenCalled(); expect(wrapper.text()).toContain('At least one file is required');
        expect(wrapper.text()).toContain('Draft'); wrapper.unmount();
    });
    it('respects deadline and attempt limits before offering a new draft', async () => {
        api.fetchAssignment.mockResolvedValue(assignment({ due_at: '2020-01-01T00:00:00Z', max_attempts: 1 }));
        api.fetchAssignmentSubmissions.mockResolvedValue([draft({ status: 'submitted' })]);
        api.fetchAssignmentSubmission.mockResolvedValue(draft({ status: 'submitted' }));
        const wrapper = render(AssignmentPage, { id: 8 }); await flushPromises();
        expect(wrapper.text()).toContain('The deadline has passed'); expect(button(wrapper, 'Start draft')).toBeUndefined(); wrapper.unmount();
    });
    it('does not turn a protected download error into a successful download', async () => {
        api.downloadAssignmentAttachment.mockRejectedValue({ status: 403, message: 'Forbidden' });
        const wrapper = render(AssignmentPage, { id: 8 }); await flushPromises();
        await button(wrapper, 'Download').trigger('click'); await flushPromises();
        expect(wrapper.text()).toContain('Access is no longer available'); expect(wrapper.text()).not.toContain('Safety assignment'); wrapper.unmount();
    });
    it('saves a text-only draft without attempting file upload', async () => {
        api.fetchAssignment.mockResolvedValue(assignment({ submission_type: 'text' }));
        api.fetchAssignmentSubmissions.mockResolvedValue([draft({ submission_type: 'text' })]);
        api.fetchAssignmentSubmission.mockResolvedValue(draft({ submission_type: 'text' }));
        const wrapper = render(AssignmentPage, { id: 8 }); await flushPromises();
        expect(wrapper.find('input[type="file"]').exists()).toBe(false);
        await wrapper.get('textarea').setValue('My answer'); await button(wrapper, 'Save draft').trigger('click'); await flushPromises();
        expect(api.saveAssignmentDraft).toHaveBeenCalledWith(30, 'My answer'); expect(api.uploadAssignmentFiles).not.toHaveBeenCalled(); wrapper.unmount();
    });
    it('downloads an owned submission file through the private endpoint', async () => {
        api.fetchAssignmentSubmissions.mockResolvedValue([draft({ files: [{ id: 7, original_filename: 'answer.txt' }] })]);
        api.fetchAssignmentSubmission.mockResolvedValue(draft({ files: [{ id: 7, original_filename: 'answer.txt' }] }));
        const wrapper = render(AssignmentPage, { id: 8 }); await flushPromises();
        await wrapper.findAll('button').filter((item) => item.text() === 'Download').at(-1).trigger('click'); await flushPromises();
        expect(api.downloadSubmissionFile).toHaveBeenCalledWith(7); wrapper.unmount();
    });
});

describe('certificates and course integration', () => {
    it('lists issued and revoked historical snapshots with protected PDF download', async () => {
        api.fetchCertificates.mockResolvedValue({ items: [certificate(), certificate({ id: 6, status: 'revoked', revocation_reason: 'Administrative revocation' })], meta: null });
        const wrapper = render(CertificatesPage); await flushPromises();
        expect(wrapper.text()).toContain('Safety Course'); expect(wrapper.text()).toContain('Revoked');
        await button(wrapper, 'Download').trigger('click'); await flushPromises();
        expect(api.downloadCertificate).toHaveBeenCalledWith(5); wrapper.unmount();
    });
    it('uses server eligibility, blocks ineligible issuance, and issues once', async () => {
        let wrapper = render(CourseAssessments, { courseId: 2, slug: 'safety', lessonId: 9 }); await flushPromises();
        expect(wrapper.text()).toContain('Complete the remaining lessons'); expect(wrapper.text()).toContain('Safety quiz'); expect(wrapper.text()).toContain('Safety assignment');
        expect(api.issueCertificate).not.toHaveBeenCalled(); wrapper.unmount();
        api.fetchCertificateEligibility.mockResolvedValue({ eligible: true, reasons: [], certificate_status: null });
        wrapper = render(CourseAssessments, { courseId: 2, slug: 'safety', lessonId: 9 }); await flushPromises();
        await button(wrapper, 'Issue certificate').trigger('click'); await flushPromises();
        expect(api.issueCertificate).toHaveBeenCalledTimes(1); expect(wrapper.text()).toContain('Certificate issued'); wrapper.unmount();
    });
    it('does not offer issuance for a revoked historical certificate', async () => {
        api.fetchCertificateEligibility.mockResolvedValue({ eligible: true, reasons: [], certificate_status: 'revoked' });
        const wrapper = render(CourseAssessments, { courseId: 2, slug: 'safety', lessonId: 9 }); await flushPromises();
        expect(wrapper.text()).toContain('Revoked'); expect(button(wrapper, 'Issue certificate')).toBeUndefined(); wrapper.unmount();
    });
    it('guards duplicate certificate issuance while the backend responds', async () => {
        api.fetchCertificateEligibility.mockResolvedValue({ eligible: true, reasons: [], certificate_status: null });
        const pending = deferred(); api.issueCertificate.mockReturnValue(pending.promise);
        const wrapper = render(CourseAssessments, { courseId: 2, slug: 'safety', lessonId: 9 }); await flushPromises();
        await button(wrapper, 'Issue certificate').trigger('click'); await flushPromises();
        expect(button(wrapper, 'Issue certificate').attributes('disabled')).toBeDefined();
        pending.resolve(certificate()); await flushPromises();
        expect(api.issueCertificate).toHaveBeenCalledTimes(1); wrapper.unmount();
    });
    it.each([['issued', 'Valid certificate'], ['revoked', 'no longer valid']])('verifies public %s certificates using public fields only', async (status, label) => {
        api.verifyCertificate.mockResolvedValue({ status, certificate_number: 'JCEC-TEST', student_name: 'Student', course_title: 'Safety Course', issued_at: '2026-09-30T00:00:00Z', email: 'private@example.test' });
        const wrapper = render(CertificateVerificationPage, { token: 'opaque' }); await flushPromises();
        expect(wrapper.text()).toContain(label); expect(wrapper.text()).toContain('JCEC-TEST'); expect(wrapper.text()).not.toContain('private@example.test'); wrapper.unmount();
    });
    it('treats unknown verification tokens as a normal public state', async () => {
        api.verifyCertificate.mockRejectedValue({ status: 404 });
        const wrapper = render(CertificateVerificationPage, { token: 'unknown' }); await flushPromises();
        expect(wrapper.text()).toContain('No certificate was found'); expect(wrapper.find('[role="alert"]').exists()).toBe(false); wrapper.unmount();
    });
});

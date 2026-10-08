import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import i18n, { setLocale } from '../i18n';
import * as authoring from '../api/assessment-authoring';
import * as instructor from '../api/instructor';
import AssessmentAuthoringPage from '../pages/AssessmentAuthoringPage.vue';
import QuizQuestionBuilder from '../components/assessments/QuizQuestionBuilder.vue';
import { emptyQuestion, questionPayload, validateQuestion } from '../utils/assessment-authoring';

const state = vi.hoisted(() => ({ route: { params: { courseId: '4' } }, replace: vi.fn(), leave: null, permissions: ['courses.view', 'curriculum.view', 'assessments.view', 'assessments.create', 'assessments.update', 'assessments.publish', 'assignments.view', 'assignments.create', 'assignments.update', 'assignments.publish'] }));
vi.mock('vue-router', async (original) => ({ ...(await original()), useRoute: () => state.route, useRouter: () => ({ replace: state.replace }), onBeforeRouteLeave: (callback) => { state.leave = callback; } }));
vi.mock('../stores/auth', () => ({ useAuthStore: () => ({ can: (permission) => state.permissions.includes(permission) }) }));
vi.mock('../api/admin', () => ({ fetchAdminCourse: vi.fn() }));
vi.mock('../api/admin-curriculum', () => ({ fetchSections: vi.fn() }));
vi.mock('../api/instructor', () => ({ fetchInstructorCourse: vi.fn(), fetchInstructorCurriculum: vi.fn() }));
vi.mock('../api/assessment-authoring', () => Object.fromEntries([
    'getQuiz', 'createQuiz', 'updateQuiz', 'archiveQuiz', 'publishQuiz', 'unpublishQuiz', 'addQuestion', 'updateQuestion', 'removeQuestion', 'reorderQuestions',
    'getAssignment', 'createAssignment', 'updateAssignment', 'archiveAssignment', 'publishAssignment', 'unpublishAssignment', 'uploadAssignmentAttachment', 'removeAssignmentAttachment', 'downloadAssignmentAttachment',
].map((name) => [name, vi.fn()])));

const quiz = { id: 5, course_id: 4, lesson_id: 11, title: 'Knowledge check', description: 'Description', instructions: 'Read carefully', status: 'draft', passing_score: '70.00', max_attempts: 2, time_limit_minutes: 30, shuffle_questions: true, shuffle_answers: false, show_results: true, show_correct_answers: false, available_from: null, available_until: null, questions: [], capabilities: { can_update: true, can_delete: true, can_publish: true } };
const assignment = { id: 7, course_id: 4, lesson_id: null, title: 'Project', description: 'Description', instructions: 'Submit your work', status: 'draft', submission_type: 'text_and_file', maximum_score: '100.00', passing_score: '60.00', max_attempts: 2, available_from: null, due_at: null, allow_late_submissions: false, attachments: [{ id: 9, original_filename: 'brief.pdf', file_size: 2048 }], capabilities: { can_update: true, can_delete: true, can_publish: true } };
const course = { id: 4, title: 'Owned course', capabilities: { can_view_curriculum: true, can_create_quiz: true, can_create_assignment: true } };
function renderPage(kind, scope = 'instructor') { return mount(AssessmentAuthoringPage, { props: { kind, scope }, global: { plugins: [i18n], stubs: { RouterLink: { props: ['to'], template: '<a><slot /></a>' } } } }); }
function renderBuilder(value = quiz) { return mount(QuizQuestionBuilder, { props: { quiz: value, editable: true }, global: { plugins: [i18n] } }); }
function button(wrapper, text) { return wrapper.findAll('button').find((item) => item.text().includes(text)); }

beforeEach(() => {
    vi.resetAllMocks(); setLocale('en');
    state.route = { params: { courseId: '4' } };
    state.permissions = ['courses.view', 'curriculum.view', 'assessments.view', 'assessments.create', 'assessments.update', 'assessments.publish', 'assignments.view', 'assignments.create', 'assignments.update', 'assignments.publish'];
    instructor.fetchInstructorCourse.mockResolvedValue(course);
    instructor.fetchInstructorCurriculum.mockResolvedValue({ items: [{ id: 2, title: 'Module', lessons: [{ id: 11, title: 'Introduction' }] }] });
    authoring.getQuiz.mockResolvedValue(quiz);
    authoring.createQuiz.mockResolvedValue(quiz);
    authoring.updateQuiz.mockResolvedValue(quiz);
    authoring.publishQuiz.mockResolvedValue({ ...quiz, status: 'published' });
    authoring.getAssignment.mockResolvedValue(assignment);
    authoring.createAssignment.mockResolvedValue(assignment);
    authoring.updateAssignment.mockResolvedValue(assignment);
    authoring.publishAssignment.mockResolvedValue({ ...assignment, status: 'published' });
    authoring.uploadAssignmentAttachment.mockResolvedValue({ id: 10, original_filename: 'new.pdf', file_size: 1024 });
    authoring.addQuestion.mockResolvedValue({ id: 22 });
    vi.spyOn(window, 'confirm').mockReturnValue(true);
});

describe('assessment authoring contract', () => {
    it('validates supported question types and exact correct-answer controls', () => {
        const single = emptyQuestion();
        single.question_text = 'Choose one'; single.options[0].answer_text = 'A'; single.options[1].answer_text = 'B';
        expect(validateQuestion(single).options).toBe('oneCorrectRequired');
        single.options[0].is_correct = true;
        expect(validateQuestion(single)).toEqual({});
        const multiple = { ...single, type: 'multiple_choice', options: [{ answer_text: 'A', is_correct: true }, { answer_text: 'B', is_correct: true }] };
        expect(validateQuestion(multiple)).toEqual({});
        expect(questionPayload(multiple).options).toEqual([{ answer_text: 'A', is_correct: true }, { answer_text: 'B', is_correct: true }]);
        const boolean = emptyQuestion('true_false');
        boolean.question_text = 'Statement';
        expect(validateQuestion(boolean)).toEqual({});
        expect(questionPayload(boolean).options).toEqual([{ answer_text: 'True', is_correct: true }, { answer_text: 'False', is_correct: false }]);
    });

    it('creates a contextual quiz draft and shows the selected course lesson by name', async () => {
        const wrapper = renderPage('quiz'); await flushPromises();
        expect(wrapper.text()).toContain('Owned course');
        expect(wrapper.find('#assessment-lesson').text()).toContain('Module · Introduction');
        await wrapper.find('#assessment-title').setValue('New quiz');
        await wrapper.find('form').trigger('submit'); await flushPromises();
        expect(authoring.createQuiz).toHaveBeenCalledWith('4', expect.objectContaining({ title: 'New quiz', lesson_id: null, passing_score: 70 }));
        expect(state.replace).toHaveBeenCalledWith({ name: 'instructor.quizzes.edit', params: { courseId: '4', quizId: 5 } });
        expect(wrapper.text()).toContain('Add question');
        wrapper.unmount();
    });

    it('keeps changes guarded and blocks empty quiz publication', async () => {
        state.route.params.quizId = '5';
        const wrapper = renderPage('quiz'); await flushPromises();
        await button(wrapper, 'Publish').trigger('click'); await flushPromises();
        expect(authoring.publishQuiz).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain('Add at least one question');
        await wrapper.find('#assessment-title').setValue('Edited');
        window.confirm.mockReturnValue(false);
        expect(state.leave()).toBe(false);
        expect(button(wrapper, 'Publish').attributes('disabled')).toBeDefined();
        wrapper.unmount();
    });

    it('does not offer publishing or editing after grants are removed', async () => {
        state.route.params.quizId = '5';
        state.permissions = ['courses.view', 'curriculum.view', 'assessments.view'];
        const wrapper = renderPage('quiz'); await flushPromises();
        expect(wrapper.find('#assessment-title').attributes('disabled')).toBeDefined();
        expect(button(wrapper, 'Publish')).toBeUndefined();
        expect(button(wrapper, 'Save changes')).toBeUndefined();
        wrapper.unmount();
    });

    it('exposes assignment files privately and keeps grading navigation separate', async () => {
        state.route.params.assignmentId = '7';
        const wrapper = renderPage('assignment'); await flushPromises();
        expect(wrapper.text()).toContain('brief.pdf');
        expect(wrapper.text()).not.toContain('storage_path');
        expect(wrapper.find('#assessment-type').text()).toContain('Text and file');
        await button(wrapper, 'Download').trigger('click');
        expect(authoring.downloadAssignmentAttachment).toHaveBeenCalledWith(assignment.attachments[0]);
        const file = new File(['file'], 'new.pdf', { type: 'application/pdf' });
        Object.defineProperty(wrapper.find('#assignment-attachment').element, 'files', { value: [file], configurable: true });
        await wrapper.find('#assignment-attachment').trigger('change');
        await button(wrapper, 'Upload attachment').trigger('click'); await flushPromises();
        expect(authoring.uploadAssignmentAttachment).toHaveBeenCalledWith(7, file);
        expect(wrapper.text()).toContain('new.pdf');
        wrapper.unmount();
    });

    it('renders Arabic RTL and English LTR labels', async () => {
        setLocale('ar');
        const wrapper = renderPage('quiz'); await flushPromises();
        expect(wrapper.attributes('dir')).toBe('rtl');
        expect(wrapper.text()).toContain('إنشاء اختبار');
        setLocale('en'); await flushPromises();
        expect(wrapper.attributes('dir')).toBe('ltr');
        expect(wrapper.text()).toContain('Create quiz');
        wrapper.unmount();
    });
});

describe('question builder', () => {
    it('uses radio for one correct answer and checkbox for multiple correct answers', async () => {
        const wrapper = renderBuilder();
        await button(wrapper, 'Add question').trigger('click');
        expect(wrapper.findAll('input[type="radio"]')).toHaveLength(2);
        expect(wrapper.findAll('input[type="checkbox"]')).toHaveLength(0);
        await wrapper.find('#question-type').setValue('multiple_choice');
        expect(wrapper.findAll('input[type="checkbox"]')).toHaveLength(2);
        await wrapper.find('#question-type').setValue('true_false');
        expect(wrapper.findAll('input[readonly]')).toHaveLength(2);
        expect(wrapper.text()).toContain('True / False');
        wrapper.unmount();
    });

    it('rejects missing correct answers and saves a valid question once', async () => {
        const wrapper = renderBuilder();
        await button(wrapper, 'Add question').trigger('click');
        await wrapper.find('#question-text').setValue('Which one?');
        await wrapper.find('#answer-0').setValue('A');
        await wrapper.find('#answer-1').setValue('B');
        await wrapper.find('form').trigger('submit');
        expect(wrapper.text()).toContain('Choose exactly one correct answer');
        expect(authoring.addQuestion).not.toHaveBeenCalled();
        await wrapper.findAll('input[type="radio"]')[1].setValue(true);
        await wrapper.find('form').trigger('submit'); await flushPromises();
        expect(authoring.addQuestion).toHaveBeenCalledWith(5, expect.objectContaining({ type: 'single_choice', options: [{ answer_text: 'A', is_correct: false }, { answer_text: 'B', is_correct: true }] }));
        expect(wrapper.emitted('changed')).toHaveLength(1);
        wrapper.unmount();
    });

    it('reorders only the quiz question IDs and handles server failures', async () => {
        const two = { ...quiz, questions: [{ id: 12, type: 'true_false', question_text: 'First', points: '1.00', options: [] }, { id: 13, type: 'single_choice', question_text: 'Second', points: '2.00', options: [] }] };
        const wrapper = renderBuilder(two);
        await wrapper.findAll('button[aria-label="Move question down"]')[0].trigger('click'); await flushPromises();
        expect(authoring.reorderQuestions).toHaveBeenCalledWith(5, [13, 12]);
        expect(wrapper.emitted('changed')).toHaveLength(1);
        authoring.reorderQuestions.mockRejectedValueOnce({ status: 500 });
        await wrapper.findAll('button[aria-label="Move question down"]')[0].trigger('click'); await flushPromises();
        expect(wrapper.text()).toContain('Unable to complete this action');
        wrapper.unmount();
    });
});

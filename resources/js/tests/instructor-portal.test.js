import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import i18n, { setLocale } from '../i18n';
import { routes } from '../router';
import { canAccessRoute, createAccessGuard } from '../router/access';
import { navigationByArea, visibleNavigation } from '../composables/navigation';
import { validScore } from '../utils/grading';
import * as instructor from '../api/instructor';
import InstructorDashboardPage from '../pages/InstructorDashboardPage.vue';
import InstructorCoursesPage from '../pages/InstructorCoursesPage.vue';
import InstructorCoursePage from '../pages/InstructorCoursePage.vue';
import InstructorQuizPage from '../pages/InstructorQuizPage.vue';
import InstructorAssignmentPage from '../pages/InstructorAssignmentPage.vue';
import InstructorSubmissionPage from '../pages/InstructorSubmissionPage.vue';
import InstructorLayout from '../layouts/InstructorLayout.vue';

const state = vi.hoisted(() => ({ route: { fullPath: '/instructor', path: '/instructor', params: {}, query: {} }, push: vi.fn(), permissions: ['courses.view', 'courses.update', 'curriculum.view', 'assignment_submissions.view'] }));
vi.mock('vue-router', async (original) => ({ ...(await original()), useRoute: () => state.route, useRouter: () => ({ push: state.push }) }));
vi.mock('../stores/auth', () => ({ useAuthStore: () => ({ can: (permission) => state.permissions.includes(permission), canAny: (permissions) => permissions.some((permission) => state.permissions.includes(permission)), hasAnyRole: (roles) => roles.includes('instructor'), hasRole: (role) => role === 'instructor' }) }));
vi.mock('../api/instructor', () => Object.fromEntries([
    'fetchInstructorSummary', 'fetchInstructorCourses', 'fetchInstructorCourse', 'updateInstructorCourse', 'fetchInstructorCurriculum',
    'fetchInstructorQuizzes', 'fetchInstructorQuiz', 'createInstructorQuiz', 'updateInstructorQuiz', 'archiveInstructorQuiz', 'fetchInstructorQuizAttempts', 'fetchInstructorQuizAttempt', 'fetchInstructorAssignments',
    'fetchInstructorAssignment', 'createInstructorAssignment', 'updateInstructorAssignment', 'archiveInstructorAssignment', 'fetchInstructorSubmissions', 'fetchInstructorSubmission', 'gradeInstructorSubmission',
    'requestInstructorRevision', 'correctInstructorGrade', 'downloadInstructorSubmissionFile', 'downloadInstructorAssignmentAttachment',
].map((name) => [name, vi.fn()])));

const collection = (items, lastPage = 1) => ({ items, meta: { current_page: 1, last_page: lastPage } });
const course = { id: 4, title: 'Owned course', short_description: 'A practical course', description: 'Details', status: 'published', level: 'beginner', category: { name: 'Professional skills' }, capabilities: { can_view_curriculum: true, can_create_curriculum: false, can_update_curriculum: false, can_delete_curriculum: false, can_update_course: true }, updated_at: '2026-09-25T10:00:00Z' };
const assignment = { id: 7, course_id: 4, title: 'Project', status: 'published', submission_type: 'text_and_file', maximum_score: '100.00', max_attempts: 2, attachments: [] };
const submission = { id: 11, assignment_id: 7, student: { name: 'Learner' }, user_id: 23, attempt_number: 1, status: 'submitted', assignment_title: 'Historic project', assignment_instructions: 'Historic instructions', submission_type: 'text_and_file', maximum_score: '100.00', text_answer: 'Student answer', files: [{ id: 9, original_filename: 'work.pdf' }], score: null, is_late: true, grading_history: [] };
const render = (component) => mount(component, { global: { plugins: [i18n], stubs: { RouterLink: { props: ['to'], template: '<a><slot /></a>' } } } });

beforeEach(() => {
    vi.resetAllMocks();
    setLocale('en');
    state.route = { fullPath: '/instructor', path: '/instructor', params: {}, query: {} };
    state.permissions = ['courses.view', 'courses.update', 'curriculum.view', 'assignment_submissions.view'];
    instructor.fetchInstructorSummary.mockResolvedValue({ courses: 3, published_courses: 2, draft_courses: 1, awaiting_grading: 4 });
    instructor.fetchInstructorCourses.mockResolvedValue(collection([course], 2));
    instructor.fetchInstructorCourse.mockImplementation(async () => ({ ...course, capabilities: { ...course.capabilities, can_create_quiz: state.permissions.includes('assessments.view') && state.permissions.includes('assessments.create'), can_create_assignment: state.permissions.includes('assignments.view') && state.permissions.includes('assignments.create') } }));
    instructor.updateInstructorCourse.mockResolvedValue(course);
    instructor.fetchInstructorCurriculum.mockResolvedValue(collection([{ id: 1, title: 'Section', lessons: [{ id: 2, title: 'Lesson', type: 'text', resources: [{ id: 3, title: 'Guide', download_available: true }] }] }]));
    instructor.fetchInstructorQuizzes.mockImplementation(async () => collection([{ id: 5, title: 'Quiz', status: 'published', passing_score: 70, capabilities: { can_update: state.permissions.includes('assessments.view') && state.permissions.includes('assessments.update'), can_delete: state.permissions.includes('assessments.view') && state.permissions.includes('assessments.delete') } }]));
    instructor.fetchInstructorQuiz.mockResolvedValue({ id: 5, title: 'Quiz', status: 'published', max_attempts: 2 });
    instructor.createInstructorQuiz.mockResolvedValue({ id: 8, title: 'New quiz' });
    instructor.updateInstructorQuiz.mockResolvedValue({ id: 5, title: 'Updated quiz' });
    instructor.archiveInstructorQuiz.mockResolvedValue({ id: 5, status: 'archived' });
    instructor.fetchInstructorQuizAttempts.mockResolvedValue(collection([{ id: 6, user_id: 23, student: { name: 'Learner' }, attempt_number: 1, status: 'submitted', score: '1.00', maximum_score: '1.00' }]));
    instructor.fetchInstructorQuizAttempt.mockResolvedValue({ id: 6, student: { name: 'Learner' }, attempt_number: 1, status: 'submitted', score: '1.00', maximum_score: '1.00', questions: [{ id: 30, question_text: 'Historical question', points: '1.00', earned_points: '1.00', selected_option_ids: [31], options: [{ id: 31, answer_text: 'Historic answer', is_correct: true }] }] });
    instructor.fetchInstructorAssignments.mockImplementation(async () => collection([{ ...assignment, capabilities: { can_update: state.permissions.includes('assignments.view') && state.permissions.includes('assignments.update'), can_delete: state.permissions.includes('assignments.view') && state.permissions.includes('assignments.delete') } }]));
    instructor.fetchInstructorAssignment.mockResolvedValue(assignment);
    instructor.createInstructorAssignment.mockResolvedValue({ id: 9, title: 'New assignment' });
    instructor.updateInstructorAssignment.mockResolvedValue({ id: 7, title: 'Updated assignment' });
    instructor.archiveInstructorAssignment.mockResolvedValue({ id: 7, status: 'archived' });
    instructor.fetchInstructorSubmissions.mockResolvedValue(collection([submission], 2));
    instructor.fetchInstructorSubmission.mockResolvedValue(submission);
    instructor.gradeInstructorSubmission.mockResolvedValue(submission);
    instructor.requestInstructorRevision.mockResolvedValue(submission);
    instructor.correctInstructorGrade.mockResolvedValue(submission);
    instructor.downloadInstructorSubmissionFile.mockResolvedValue();
    vi.spyOn(window, 'confirm').mockReturnValue(true);
});

describe('instructor navigation and scoped routes', () => {
    it('offers dashboard, own reports and own courses, with role and permission gates', async () => {
        expect(navigationByArea.instructor.map((item) => item.route)).toEqual(['instructor.messages', 'instructor.reports', 'instructor.dashboard', 'instructor.courses.index']);
        expect(visibleNavigation(navigationByArea.instructor, { can: () => false, canAny: () => false, hasAnyRole: () => true })).toEqual([]);
        const route = routes.find((item) => item.path === '/instructor');
        expect(route.children).toHaveLength(14);
        expect(route.children.every((item) => item.meta.roles.includes('instructor'))).toBe(true);
        expect(canAccessRoute({ hasAnyRole: () => false, can: () => true }, route.children[1].meta)).toBe(false);
        const instructorAuth = { isAuthenticated: true, initialize: vi.fn().mockResolvedValue(), hasRole: (role) => role === 'instructor', hasAnyRole: (roles) => roles.includes('instructor'), can: () => true, canAny: () => true };
        expect(await createAccessGuard(instructorAuth)({ path: '/admin', fullPath: '/admin', meta: { requiresAuth: true, roles: ['admin', 'content_manager', 'sales_support'] } })).toEqual({ name: 'forbidden' });
    });
    it('keeps language direction in the workspace', async () => {
        setLocale('ar');
        const wrapper = render(InstructorDashboardPage);
        await flushPromises();
        expect(wrapper.attributes('dir')).toBe('rtl');
        setLocale('en');
        await flushPromises();
        expect(wrapper.attributes('dir')).toBe('ltr');
    });
    it('reuses the responsive shell and mobile drawer', async () => {
        state.route.matched = [];
        const wrapper = mount(InstructorLayout, { global: { plugins: [i18n], stubs: { RouterLink: { template: '<a><slot /></a>' }, RouterView: true } } });
        expect(wrapper.find('aside').exists()).toBe(true);
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
        await wrapper.find('[aria-label="Open menu"]').trigger('click');
        expect(wrapper.find('[role="dialog"]').exists()).toBe(true);
        await wrapper.find('[aria-label="Close menu"]').trigger('click');
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
    });
});

describe('instructor dashboard and courses', () => {
    it('loads truthful summary and handles server errors', async () => {
        const wrapper = render(InstructorDashboardPage);
        expect(wrapper.text()).toContain('Loading');
        await flushPromises();
        expect(wrapper.text()).toContain('Awaiting grading');
        expect(wrapper.text()).toContain('4');
        instructor.fetchInstructorSummary.mockRejectedValueOnce({ status: 500 });
        wrapper.unmount();
        const failed = render(InstructorDashboardPage);
        await flushPromises();
        expect(failed.text()).toContain('Could not load this workspace');
    });
    it('uses server search and pagination without client-side instructor filtering', async () => {
        state.route = { fullPath: '/instructor/courses', path: '/instructor/courses', params: {}, query: { search: 'Owned', page: '2' } };
        const wrapper = render(InstructorCoursesPage);
        await flushPromises();
        expect(instructor.fetchInstructorCourses).toHaveBeenCalledWith({ page: 2, search: 'Owned' });
        expect(wrapper.text()).toContain('Owned course');
        expect(wrapper.text()).not.toContain('Foreign course');
        await wrapper.find('form').trigger('submit');
        expect(state.push).toHaveBeenCalledWith({ name: 'instructor.courses.index', query: { search: 'Owned' } });
    });
    it('shows curriculum and definitions read-only and rejects unauthorized course', async () => {
        state.route.params = { courseId: '4' };
        const wrapper = render(InstructorCoursePage);
        await flushPromises();
        expect(wrapper.text()).toContain('Section');
        expect(wrapper.text()).toContain('Lesson');
        expect(wrapper.text()).toContain('Quiz definitions are read-only');
        expect(wrapper.findAll('button').some((button) => /Create section|Publish quiz|Create assignment/.test(button.text()))).toBe(false);
        expect(instructor.fetchInstructorCourse).toHaveBeenCalledWith('4');
        wrapper.unmount();
        instructor.fetchInstructorCourse.mockRejectedValueOnce({ status: 403 });
        const forbidden = render(InstructorCoursePage);
        await flushPromises();
        expect(forbidden.text()).toContain('not authorized');
        forbidden.unmount();
        instructor.fetchInstructorCourse.mockRejectedValueOnce({ status: 404 });
        const missing = render(InstructorCoursePage);
        await flushPromises();
        expect(missing.text()).toContain('not found in your courses');
    });
    it('links to dedicated quiz and assignment editors only with their independent grants', async () => {
        state.route.params = { courseId: '4' };
        state.permissions = ['courses.view', 'assessments.view', 'assignments.view'];
        const readOnly = render(InstructorCoursePage);
        await flushPromises();
        expect(readOnly.text()).toContain('Quiz definitions are read-only');
        expect(readOnly.text()).toContain('Assignment definitions are read-only');
        expect(readOnly.text()).not.toContain('Create quiz');
        expect(readOnly.text()).not.toContain('Create assignment');
        readOnly.unmount();

        state.permissions.push('assessments.create', 'assessments.update', 'assessments.delete', 'assignments.create', 'assignments.update', 'assignments.delete');
        const granted = render(InstructorCoursePage);
        await flushPromises();
        expect(granted.text()).toContain('Create quiz');
        expect(granted.text()).toContain('Create assignment');
        expect(granted.find('#instructor-quiz-title').exists()).toBe(false);
        expect(granted.find('#instructor-assignment-title').exists()).toBe(false);
        expect(granted.findAll('a').some((link) => link.text() === 'Create quiz')).toBe(true);
        expect(instructor.createInstructorQuiz).not.toHaveBeenCalled();
        expect(instructor.createInstructorAssignment).not.toHaveBeenCalled();
        granted.unmount();

        state.permissions = state.permissions.filter((permission) => permission !== 'assessments.create' && permission !== 'assignments.create');
        const revoked = render(InstructorCoursePage);
        await flushPromises();
        expect(revoked.text()).not.toContain('Create quiz');
        expect(revoked.text()).not.toContain('Create assignment');
        expect(revoked.text()).toContain('Edit quiz');
    });
});

describe('quiz and assignment review', () => {
    it('shows paginated quiz results and historical attempt snapshot without edit actions', async () => {
        state.route.params = { courseId: '4', quizId: '5' };
        const list = render(InstructorQuizPage);
        await flushPromises();
        expect(list.text()).toContain('Learner');
        expect(list.text()).toContain('1.00');
        list.unmount();
        state.route.params.attemptId = '6';
        const detail = render(InstructorQuizPage);
        await flushPromises();
        expect(detail.text()).toContain('Historical question');
        expect(detail.text()).toContain('Historic answer');
        expect(detail.text()).not.toContain('Save score');
    });
    it('shows assignment submission queue filters, late state and protected attachment', async () => {
        state.route.params = { courseId: '4', assignmentId: '7' };
        const wrapper = render(InstructorAssignmentPage);
        await flushPromises();
        expect(wrapper.text()).toContain('Learner');
        expect(wrapper.text()).toContain('Late');
        await wrapper.find('select').setValue('submitted');
        await wrapper.find('form').trigger('submit');
        expect(state.push).toHaveBeenCalledWith({ name: 'instructor.assignments.show', params: state.route.params, query: { status: 'submitted' } });
    });
    it('shows snapshot, text and protected file, never storage path', async () => {
        state.route.params = { courseId: '4', assignmentId: '7', submissionId: '11' };
        const wrapper = render(InstructorSubmissionPage);
        await flushPromises();
        expect(wrapper.text()).toContain('Historic instructions');
        expect(wrapper.text()).toContain('Student answer');
        expect(wrapper.text()).toContain('work.pdf');
        expect(wrapper.html()).not.toContain('storage_path');
        await wrapper.findAll('button').find((button) => button.text().includes('work.pdf')).trigger('click');
        expect(instructor.downloadInstructorSubmissionFile).toHaveBeenCalledWith(9, 'work.pdf');
    });
});

describe('server-authoritative grading', () => {
    it('validates exact decimal score without float rounding', () => {
        expect(validScore('100.00', '100.00')).toBe(true);
        expect(validScore('100.01', '100.00')).toBe(false);
        expect(validScore('0.005', '100.00')).toBe(false);
        expect(validScore('999999999999999999.99', '999999999999999999.99')).toBe(true);
    });
    it('grades once and refetches authoritative submission', async () => {
        state.route.params = { courseId: '4', assignmentId: '7', submissionId: '11' };
        const wrapper = render(InstructorSubmissionPage);
        await flushPromises();
        await wrapper.find('#grade-score').setValue('100.01');
        wrapper.find('#grade-score').element.closest('form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        await flushPromises();
        expect(wrapper.text()).toContain('Enter a score between 0');
        expect(instructor.gradeInstructorSubmission).not.toHaveBeenCalled();
        await wrapper.find('#grade-score').setValue('80.25');
        await wrapper.find('#grade-score').element.closest('form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        await flushPromises();
        expect(instructor.gradeInstructorSubmission).toHaveBeenCalledWith(11, { score: '80.25', feedback: null });
        expect(instructor.fetchInstructorSubmission).toHaveBeenCalledTimes(2);
    });
    it('prevents duplicate grade requests while the first is pending', async () => {
        state.route.params = { courseId: '4', assignmentId: '7', submissionId: '11' };
        let finish;
        instructor.gradeInstructorSubmission.mockReturnValue(new Promise((resolve) => { finish = resolve; }));
        const wrapper = render(InstructorSubmissionPage);
        await flushPromises();
        await wrapper.find('#grade-score').setValue('80.00');
        const form = wrapper.find('#grade-score').element.closest('form');
        form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        expect(instructor.gradeInstructorSubmission).toHaveBeenCalledTimes(1);
        finish(submission);
        await flushPromises();
        expect(instructor.fetchInstructorSubmission).toHaveBeenCalledTimes(2);
    });
    it('requires revision feedback and keeps historical attempt visible on server error', async () => {
        state.route.params = { courseId: '4', assignmentId: '7', submissionId: '11' };
        instructor.requestInstructorRevision.mockRejectedValueOnce({ status: 422, errors: { assignment: ['Maximum attempts reached'] } });
        const wrapper = render(InstructorSubmissionPage);
        await flushPromises();
        const form = wrapper.find('#revision-feedback').element.closest('form');
        form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        await flushPromises();
        expect(wrapper.text()).toContain('Feedback is required');
        await wrapper.find('#revision-feedback').setValue('Please revise');
        form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        await flushPromises();
        expect(instructor.requestInstructorRevision).toHaveBeenCalledWith(11, { feedback: 'Please revise' });
        expect(wrapper.text()).toContain('Historic instructions');
        expect(wrapper.text()).toContain('Maximum attempts reached');
    });
    it('uses explicit correction and confirmation with reason', async () => {
        state.route.params = { courseId: '4', assignmentId: '7', submissionId: '11' };
        instructor.fetchInstructorSubmission.mockResolvedValue({ ...submission, status: 'graded', score: '70.00', grading_history: [{ id: 1, action: 'graded', score: '70.00' }] });
        const wrapper = render(InstructorSubmissionPage);
        await flushPromises();
        expect(wrapper.text()).toContain('Grading history');
        await wrapper.find('#correction-score').setValue('75.00');
        await wrapper.find('#correction-reason').setValue('Rubric correction');
        wrapper.find('#correction-reason').element.closest('form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
        await flushPromises();
        expect(window.confirm).toHaveBeenCalled();
        expect(instructor.correctInstructorGrade).toHaveBeenCalledWith(11, { score: '75.00', feedback: null, reason: 'Rubric correction' });
    });
});

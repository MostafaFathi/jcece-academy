import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import i18n, { setLocale } from '../i18n';
import * as curriculum from '../api/admin-curriculum';
import * as admin from '../api/admin';
import AdminCurriculumPage from '../pages/AdminCurriculumPage.vue';
import AdminLessonEditor from '../components/admin/AdminLessonEditor.vue';
import AdminLessonResources from '../components/admin/AdminLessonResources.vue';

const state = vi.hoisted(() => ({ permissions: [], user: { id: 8 }, roles: [], route: { params: { id: 7 } } }));
vi.mock('vue-router', async (original) => ({ ...(await original()), useRoute: () => state.route }));
vi.mock('../stores/auth', () => ({ useAuthStore: () => ({ user: state.user, can: (permission) => state.permissions.includes(permission), hasRole: (role) => state.roles.includes(role), hasAnyRole: (roles) => roles.some((role) => state.roles.includes(role)) }) }));
vi.mock('../api/admin', () => ({ fetchAdminCourse: vi.fn() }));
vi.mock('../api/admin-curriculum', () => Object.fromEntries(['fetchSections', 'createSection', 'updateSection', 'deleteSection', 'reorderSections', 'createLesson', 'updateLesson', 'deleteLesson', 'reorderLessons', 'fetchLessonResources', 'createLessonResource', 'updateLessonResource', 'deleteLessonResource', 'reorderLessonResources'].map((name) => [name, vi.fn()])));

const lesson = { id: 21, title: 'Read', slug: 'read', type: 'text', content: 'Plain <b>text</b>', is_published: true, is_preview: false, resources: [] };
const sections = [
    { id: 11, title: 'Introduction', description: 'Start here', is_active: true, lessons: [lesson] },
    { id: 12, title: 'Practice', description: null, is_active: false, lessons: [] },
];
function render(component, props = {}) { return mount(component, { props, global: { plugins: [i18n], stubs: { RouterLink: { props: ['to'], template: '<a><slot /></a>' } } } }); }
function deferred() { let resolve; const promise = new Promise((done) => { resolve = done; }); return { promise, resolve }; }
beforeEach(() => {
    vi.resetAllMocks(); setLocale('en'); state.permissions = ['courses.view', 'courses.update', 'curriculum.view', 'curriculum.create', 'curriculum.update', 'curriculum.delete']; state.roles = ['admin']; state.user = { id: 8 }; state.route = { params: { id: 7 } };
    admin.fetchAdminCourse.mockResolvedValue({ id: 7, title: 'Safe course', status: 'draft', instructor: { id: 8 } });
    curriculum.fetchSections.mockImplementation(async () => structuredClone(sections));
    curriculum.fetchLessonResources.mockResolvedValue([]);
    vi.spyOn(window, 'confirm').mockReturnValue(true);
});

describe('curriculum builder', () => {
    it('loads the hierarchy with safe text, badges and mobile-friendly cards', async () => {
        const wrapper = render(AdminCurriculumPage); await flushPromises();
        expect(wrapper.text()).toContain('Safe course'); expect(wrapper.text()).toContain('Introduction'); expect(wrapper.text()).toContain('Published lesson'); expect(wrapper.text()).toContain('Enrolled learners only');
        expect(wrapper.find('table').exists()).toBe(false); expect(wrapper.html()).not.toContain('<b>text</b>');
        expect(curriculum.fetchSections).toHaveBeenCalledWith(7); wrapper.unmount();
    });
    it('handles an empty curriculum and creates a section', async () => {
        curriculum.fetchSections.mockResolvedValue([]);
        const wrapper = render(AdminCurriculumPage); await flushPromises();
        expect(wrapper.text()).toContain('no sections');
        await wrapper.findAll('button').find((button) => button.text() === 'Add section').trigger('click');
        await wrapper.get('#new-section-title').setValue('New section');
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(curriculum.createSection).toHaveBeenCalledWith(7, { title: 'New section', description: null, is_active: true }); wrapper.unmount();
    });
    it('opens collapsed section before editing and displays validation', async () => {
        curriculum.updateSection.mockRejectedValue({ status: 422, errors: { title: ['Invalid title'] } });
        const wrapper = render(AdminCurriculumPage); await flushPromises();
        await wrapper.findAll('button').find((button) => button.text().startsWith('Practice')).trigger('click');
        await wrapper.findAll('button').find((button) => button.text() === 'Edit' && button.element.closest('li')?.textContent.includes('Practice')).trigger('click');
        expect(wrapper.find('#edit-section-title').exists()).toBe(true);
        await wrapper.get('#edit-section-title').setValue('Changed'); await wrapper.get('#edit-section-title').element.closest('form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })); await flushPromises();
        expect(curriculum.updateSection).toHaveBeenCalledWith(7, 12, expect.objectContaining({ title: 'Changed', is_active: false })); expect(wrapper.text()).toContain('Invalid title'); wrapper.unmount();
    });
    it('confirms deletion and never removes before server success', async () => {
        const pending = deferred(); curriculum.deleteSection.mockReturnValue(pending.promise);
        const wrapper = render(AdminCurriculumPage); await flushPromises();
        await wrapper.findAll('button').find((button) => button.text() === 'Delete' && button.element.closest('li')?.textContent.includes('Practice')).trigger('click');
        expect(wrapper.text()).toContain('Practice'); expect(curriculum.fetchSections).toHaveBeenCalledTimes(1);
        pending.resolve(); await flushPromises(); expect(curriculum.fetchSections).toHaveBeenCalledTimes(2); wrapper.unmount();
    });
    it('reorders by exact section IDs and suppresses duplicate submissions', async () => {
        const pending = deferred(); curriculum.reorderSections.mockReturnValue(pending.promise);
        const wrapper = render(AdminCurriculumPage); await flushPromises();
        const moveEarlier = wrapper.findAll('button[aria-label="Move earlier"]').find((button) => !button.element.disabled);
        await moveEarlier.trigger('click'); await moveEarlier.trigger('click');
        expect(curriculum.reorderSections).toHaveBeenCalledTimes(1); expect(curriculum.reorderSections).toHaveBeenCalledWith(7, [12, 11]);
        pending.resolve([]); await flushPromises(); expect(curriculum.fetchSections).toHaveBeenCalledTimes(2); wrapper.unmount();
    });
    it('refetches server ordering and reports failed reorders', async () => {
        curriculum.reorderSections.mockRejectedValue({ status: 422 });
        const wrapper = render(AdminCurriculumPage); await flushPromises();
        await wrapper.findAll('button[aria-label="Move earlier"]').find((button) => !button.element.disabled).trigger('click'); await flushPromises();
        expect(curriculum.fetchSections).toHaveBeenCalledTimes(2); expect(wrapper.text()).toContain('Review the highlighted fields'); wrapper.unmount();
    });
    it('creates, edits and deletes lessons through their section endpoint', async () => {
        const wrapper = render(AdminCurriculumPage); await flushPromises();
        await wrapper.findAll('button').find((button) => button.text() === 'Add lesson').trigger('click');
        await wrapper.get('#lesson-title').setValue('New lesson'); await wrapper.get('#lesson-slug').setValue('new-lesson'); await wrapper.get('#lesson-content').setValue('Hello');
        await wrapper.get('#lesson-title').element.closest('form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })); await flushPromises();
        expect(curriculum.createLesson).toHaveBeenCalledWith(11, expect.objectContaining({ type: 'text', content: 'Hello' }));
        const lessonCard = () => wrapper.findAll('li').find((item) => item.classes().includes('rounded-2xl') && item.text().includes('Read'));
        await lessonCard().findAll('button').find((button) => button.text() === 'Edit').trigger('click');
        await wrapper.get('#lesson-title').setValue('Revised'); await wrapper.get('#lesson-title').element.closest('form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })); await flushPromises();
        expect(curriculum.updateLesson).toHaveBeenCalledWith(11, 21, expect.objectContaining({ title: 'Revised' }));
        await lessonCard().findAll('button').find((button) => button.text() === 'Delete').trigger('click'); await flushPromises();
        expect(curriculum.deleteLesson).toHaveBeenCalledWith(11, 21); wrapper.unmount();
    });
    it('is read-only without mutation permissions and respects Arabic direction', async () => {
        state.permissions = ['courses.view', 'curriculum.view']; state.roles = ['instructor']; setLocale('ar');
        const wrapper = render(AdminCurriculumPage); await flushPromises();
        expect(wrapper.attributes('dir')).toBe('rtl'); expect(wrapper.text()).toContain('بناء المنهج'); expect(wrapper.text()).not.toContain('إضافة قسم');
        expect(wrapper.findAll('button[aria-label="نقل للأعلى"]')).toHaveLength(0); wrapper.unmount();
    });
});

describe('lesson editor and resources', () => {
    it('shows only fields for the selected type and submits preview state', async () => {
        const wrapper = render(AdminLessonEditor); await wrapper.get('#lesson-title').setValue('Video'); await wrapper.get('#lesson-slug').setValue('video');
        await wrapper.get('#lesson-type').setValue('video'); expect(wrapper.find('#video-id').exists()).toBe(true); expect(wrapper.find('#lesson-content').exists()).toBe(false);
        await wrapper.get('#video-id').setValue('abc'); await wrapper.findAll('input[type="checkbox"]').at(1).setValue(true); await wrapper.get('form').trigger('submit');
        expect(wrapper.emitted('save')[0][0]).toMatchObject({ type: 'video', video_id: 'abc', content: null, is_preview: true });
        await wrapper.get('#lesson-type').setValue('link'); expect(wrapper.find('#video-id').exists()).toBe(false); expect(wrapper.find('#lesson-url').exists()).toBe(true);
        await wrapper.get('#lesson-type').setValue('file'); expect(wrapper.find('#lesson-url').exists()).toBe(false); wrapper.unmount();
    });
    it('lists resources without storage metadata and creates only external links', async () => {
        curriculum.fetchLessonResources.mockResolvedValue([{ id: 30, title: 'Protected PDF', type: 'pdf', download_available: true, is_downloadable: true, sort_order: 0 }]);
        const wrapper = render(AdminLessonResources, { lesson, canCreate: true, canUpdate: true, canDelete: true }); await flushPromises();
        expect(wrapper.text()).toContain('Protected PDF'); expect(wrapper.html()).not.toContain('file_path'); expect(wrapper.text()).toContain('not browser file upload');
        await wrapper.findAll('button').find((button) => button.text() === 'Add external link').trigger('click'); await wrapper.get('#resource-title').setValue('Handout'); await wrapper.get('#resource-url').setValue('https://example.test/handout');
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(curriculum.createLessonResource).toHaveBeenCalledWith(21, expect.objectContaining({ title: 'Handout', type: 'link', external_url: 'https://example.test/handout' })); wrapper.unmount();
    });
    it('keeps failed resource deletion visible and shows server errors', async () => {
        curriculum.fetchLessonResources.mockResolvedValue([{ id: 30, title: 'Protected PDF', type: 'pdf', download_available: true, sort_order: 0 }]); curriculum.deleteLessonResource.mockRejectedValue({ status: 500 });
        const wrapper = render(AdminLessonResources, { lesson, canDelete: true }); await flushPromises();
        await wrapper.findAll('button').find((button) => button.text() === 'Delete').trigger('click'); await flushPromises();
        expect(wrapper.text()).toContain('Protected PDF'); expect(curriculum.fetchLessonResources).toHaveBeenCalledTimes(2); wrapper.unmount();
    });
});

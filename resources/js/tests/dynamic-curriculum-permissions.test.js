import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import i18n, { setLocale } from '../i18n';
import AdminCurriculumPage from '../pages/AdminCurriculumPage.vue';
import { fetchAdminCourse } from '../api/admin';
import { fetchSections } from '../api/admin-curriculum';

vi.mock('vue-router', () => ({
    useRoute: () => ({ name: 'instructor.courses.curriculum', params: { courseId: 3 } }),
}));
vi.mock('../api/admin', () => ({ fetchAdminCourse: vi.fn() }));
vi.mock('../api/admin-curriculum', () => ({
    fetchSections: vi.fn(),
    createSection: vi.fn(), updateSection: vi.fn(), deleteSection: vi.fn(), reorderSections: vi.fn(),
    createLesson: vi.fn(), updateLesson: vi.fn(), deleteLesson: vi.fn(), reorderLessons: vi.fn(),
}));

const sections = [
    { id: 11, title: 'First section', is_active: true, lessons: [
        { id: 21, title: 'First lesson', type: 'text', resources: [] },
        { id: 22, title: 'Second lesson', type: 'text', resources: [] },
    ] },
    { id: 12, title: 'Second section', is_active: true, lessons: [] },
];
const capabilities = (create = false, update = false, remove = false) => ({
    can_view_curriculum: true, can_create_curriculum: create,
    can_update_curriculum: update, can_delete_curriculum: remove,
    can_update_course: false,
});
function render() {
    return mount(AdminCurriculumPage, {
        global: {
            plugins: [i18n],
            stubs: {
                RouterLink: { template: '<a><slot /></a>' },
                PageHeading: { template: '<div><slot name="actions" /><slot /></div>' },
                BaseButton: { template: '<button><slot /></button>' },
                AdminLessonEditor: true,
                AdminLessonResources: true,
                LoadingState: true,
                BaseAlert: true,
            },
        },
    });
}
function actionButtons(wrapper) {
    return wrapper.findAll('button').map((button) => button.text()).join(' | ');
}

beforeEach(() => {
    vi.resetAllMocks();
    setLocale('en');
    fetchSections.mockResolvedValue(sections);
});

describe('Instructor curriculum authoring from server capabilities', () => {
    it('keeps a view-only instructor read-only', async () => {
        fetchAdminCourse.mockResolvedValue({ id: 3, title: 'Owned course', status: 'draft', capabilities: capabilities() });
        const wrapper = render();
        await flushPromises();

        expect(actionButtons(wrapper)).not.toContain('Add section');
        expect(actionButtons(wrapper)).not.toContain('Add lesson');
        expect(actionButtons(wrapper)).not.toContain('Edit');
        expect(actionButtons(wrapper)).not.toContain('Delete');
        expect(wrapper.findAll('[aria-label="Move earlier"]')).toHaveLength(0);
        wrapper.unmount();
    });

    it('shows create, edit, delete and reorder after the refreshed course capabilities grant them', async () => {
        fetchAdminCourse.mockResolvedValue({ id: 3, title: 'Owned course', status: 'draft', capabilities: capabilities(true, true, true) });
        const wrapper = render();
        await flushPromises();

        expect(actionButtons(wrapper)).toContain('Add section');
        expect(actionButtons(wrapper)).toContain('Add lesson');
        expect(actionButtons(wrapper)).toContain('Edit');
        expect(actionButtons(wrapper)).toContain('Delete');
        expect(wrapper.findAll('[aria-label="Move earlier"]').length).toBeGreaterThan(0);
        wrapper.unmount();
    });

    it('removes create actions after a subsequent course refresh revokes only create', async () => {
        fetchAdminCourse.mockResolvedValue({ id: 3, title: 'Owned course', status: 'draft', capabilities: capabilities(false, true, true) });
        const wrapper = render();
        await flushPromises();

        expect(actionButtons(wrapper)).not.toContain('Add section');
        expect(actionButtons(wrapper)).not.toContain('Add lesson');
        expect(actionButtons(wrapper)).toContain('Edit');
        expect(actionButtons(wrapper)).toContain('Delete');
        wrapper.unmount();
    });
});

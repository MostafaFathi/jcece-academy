import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import i18n, { setLocale } from '../i18n';
import * as api from '../api/admin';
import * as instructorApi from '../api/instructor-options';
import AdminDashboardPage from '../pages/AdminDashboardPage.vue';
import AdminCategoriesPage from '../pages/AdminCategoriesPage.vue';
import AdminCategoryFormPage from '../pages/AdminCategoryFormPage.vue';
import AdminCoursesPage from '../pages/AdminCoursesPage.vue';
import AdminCourseFormPage from '../pages/AdminCourseFormPage.vue';
import AppShellLayout from '../layouts/AppShellLayout.vue';

const state = vi.hoisted(() => ({ route: { params: {}, query: {} }, push: vi.fn(), permissions: [], roles: [], user: { id: 8 } }));
vi.mock('vue-router', async (original) => ({ ...(await original()), useRoute: () => state.route, useRouter: () => ({ push: state.push }) }));
vi.mock('../stores/auth', () => ({ useAuthStore: () => ({ user: state.user, can: (permission) => state.permissions.includes(permission), hasRole: (role) => state.roles.includes(role), hasAnyRole: (roles) => roles.some((role) => state.roles.includes(role)) }) }));
vi.mock('../api/admin', () => Object.fromEntries(['fetchAdminCategories', 'fetchAdminCategory', 'createAdminCategory', 'updateAdminCategory', 'deleteAdminCategory', 'fetchAdminCourses', 'fetchAdminCourse', 'createAdminCourse', 'updateAdminCourse', 'deleteAdminCourse', 'fetchAdminDashboardSummary'].map((name) => [name, vi.fn()])));
vi.mock('../api/instructor-options', () => ({ fetchInstructorOptions: vi.fn() }));

const category = { id: 2, parent_id: null, name: 'Safety', slug: 'safety', description: '', image: null, icon: null, sort_order: 1, is_active: true };
const course = { id: 7, title: 'Safety course', slug: 'safety-course', category: { ...category }, instructor: { id: 8, name: 'Teacher' }, price: '123.45', compare_price: null, currency: 'ILS', access_duration_days: null, status: 'draft', level: 'beginner', language: 'ar', duration_minutes: 60, certificate_enabled: false, discussion_enabled: false, is_featured: false, thumbnail: null, promo_video_url: null, published_at: null, discount_starts_at: null, discount_ends_at: null, learning_outcomes: [], requirements: [], target_audiences: [], required_tools: [] };
function render(component, props = {}) { return mount(component, { props, global: { plugins: [i18n], stubs: { RouterLink: { props: ['to'], template: '<a><slot /></a>' } } } }); }
function deferred() { let resolve; const promise = new Promise((yes) => { resolve = yes; }); return { promise, resolve }; }
beforeEach(() => {
    vi.resetAllMocks(); setLocale('en'); state.route = { params: {}, query: {} }; state.roles = []; state.user = { id: 8 }; state.permissions = ['categories.view', 'categories.create', 'categories.update', 'categories.delete', 'courses.view', 'courses.update', 'courses.delete', 'courses.publish'];
    api.fetchAdminCategories.mockResolvedValue({ items: [category], meta: { current_page: 1, last_page: 1, total: 1 } });
    api.fetchAdminCategory.mockResolvedValue(category);
    api.fetchAdminCourses.mockResolvedValue({ items: [course], meta: { current_page: 1, last_page: 1, total: 1 } });
    api.fetchAdminCourse.mockResolvedValue(course);
    instructorApi.fetchInstructorOptions.mockResolvedValue({ items: [{ id: 8, name: 'Teacher' }], meta: { current_page: 1, last_page: 1 } });
    api.fetchAdminDashboardSummary.mockResolvedValue({ total_categories: 1, total_courses: 8, published_courses: 4, draft_courses: 2 });
    api.createAdminCategory.mockResolvedValue(category); api.updateAdminCategory.mockResolvedValue(category); api.updateAdminCourse.mockResolvedValue(course);
    vi.spyOn(window, 'confirm').mockReturnValue(true);
});

describe('admin dashboard and lists', () => {
    it('shows only permission-safe summary metrics returned by the server', async () => {
        state.permissions = ['courses.view'];
        api.fetchAdminDashboardSummary.mockResolvedValue({ total_courses: 8, published_courses: 4, draft_courses: 2 });
        const wrapper = render(AdminDashboardPage); await flushPromises();
        expect(wrapper.text()).toContain('Total courses'); expect(wrapper.text()).toContain('Published courses'); expect(wrapper.text()).not.toContain('Total categories'); expect(wrapper.text()).not.toContain('Total users');
        expect(api.fetchAdminCourses).not.toHaveBeenCalled(); wrapper.unmount();
    });
    it('surfaces summary errors without inventing metrics', async () => {
        api.fetchAdminDashboardSummary.mockRejectedValue({ status: 500 });
        const wrapper = render(AdminDashboardPage); await flushPromises();
        expect(wrapper.text()).toContain('Unable to load some summary metrics'); expect(wrapper.text()).not.toContain('Total courses'); wrapper.unmount();
    });
    it('renders category records, gates actions, and keeps blocked deletion visible', async () => {
        state.permissions = ['categories.view', 'categories.delete'];
        api.deleteAdminCategory.mockRejectedValue({ status: 422 });
        const wrapper = render(AdminCategoriesPage); await flushPromises();
        expect(wrapper.text()).toContain('Safety'); expect(wrapper.text()).not.toContain('New category');
        await wrapper.find('button').trigger('click'); await flushPromises();
        expect(wrapper.text()).toContain('cannot be deleted'); expect(api.fetchAdminCategories).toHaveBeenCalledTimes(1); wrapper.unmount();
    });
    it('keeps course filters in URL and displays server currency', async () => {
        const wrapper = render(AdminCoursesPage); await flushPromises();
        expect(wrapper.text()).toContain('ILS'); expect(wrapper.text()).toContain('Lifetime access');
        await wrapper.get('#course-search').setValue('safety'); await wrapper.get('#course-status-filter').setValue('draft');
        await wrapper.get('form').trigger('submit');
        expect(state.push).toHaveBeenCalledWith({ name: 'admin.courses.index', query: { search: 'safety', status: 'draft' } }); wrapper.unmount();
    });
    it('shows course list empty and retry states without client-side totals', async () => {
        api.fetchAdminCourses.mockResolvedValueOnce({ items: [], meta: { total: 0 } });
        const empty = render(AdminCoursesPage); await flushPromises(); expect(empty.text()).toContain('No courses match'); empty.unmount();
        api.fetchAdminCourses.mockRejectedValueOnce({ status: 500 });
        const failed = render(AdminCoursesPage); await flushPromises(); expect(failed.text()).toContain('Unable to load management data'); failed.unmount();
    });
    it('reuses responsive admin navigation, breadcrumbs and document direction', async () => {
        state.route.matched = [{ path: '/admin', meta: { title: 'admin.dashboard' } }, { path: '/admin/categories', meta: { title: 'admin.categories' } }];
        state.route.fullPath = '/admin/categories';
        const wrapper = render(AppShellLayout, { area: 'admin' });
        expect(wrapper.text()).toContain('Categories');
        expect(wrapper.find('nav[aria-label="Breadcrumbs"]').exists()).toBe(true);
        await wrapper.get('button[aria-label="Open menu"]').trigger('click');
        expect(wrapper.find('[role="dialog"]').exists()).toBe(true);
        setLocale('ar'); await flushPromises();
        expect(document.documentElement.dir).toBe('rtl');
        wrapper.unmount();
    });
});

describe('admin forms', () => {
    it('creates an editable slug category and maps uniqueness validation', async () => {
        api.createAdminCategory.mockRejectedValue({ status: 422, errors: { slug: ['Slug already taken.'] } });
        const wrapper = render(AdminCategoryFormPage); await flushPromises();
        await wrapper.get('#category-name').setValue('New category'); await wrapper.get('#category-slug').setValue('new-category');
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(api.createAdminCategory).toHaveBeenCalledWith(expect.objectContaining({ name: 'New category', slug: 'new-category', parent_id: null }));
        expect(wrapper.text()).toContain('Slug already taken.'); wrapper.unmount();
    });
    it('loads and updates the existing category without a duplicate save', async () => {
        state.route.params.id = 2;
        const pending = deferred(); api.updateAdminCategory.mockReturnValue(pending.promise);
        const wrapper = render(AdminCategoryFormPage); await flushPromises();
        const form = wrapper.get('form'); await form.trigger('submit'); await form.trigger('submit');
        expect(api.updateAdminCategory).toHaveBeenCalledTimes(1);
        pending.resolve(category); await flushPromises(); expect(state.push).toHaveBeenCalledWith({ name: 'admin.categories.index' }); wrapper.unmount();
    });
    it('offers server-validated reparenting on category edit', async () => {
        state.route.params.id = 2;
        api.fetchAdminCategory.mockImplementation((id) => Promise.resolve(id === 2 ? { ...category, parent_id: 1 } : { ...category, id: 1, name: 'Parent' }));
        const wrapper = render(AdminCategoryFormPage); await flushPromises();
        expect(wrapper.get('#category-parent').findAll('option').map((item) => item.text())).toEqual(['Top-level category', 'Parent']);
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(api.updateAdminCategory.mock.calls[0][1]).toHaveProperty('parent_id', 1); wrapper.unmount();
    });
    it('shows a server cycle validation error for category parent', async () => {
        state.route.params.id = 2;
        api.updateAdminCategory.mockRejectedValue({ status: 422, errors: { parent_id: ['This parent would create a cycle.'] } });
        const wrapper = render(AdminCategoryFormPage); await flushPromises();
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(wrapper.text()).toContain('This parent would create a cycle.'); wrapper.unmount();
    });
    it('creates a draft course with a selected instructor from the directory', async () => {
        state.permissions.push('courses.create'); api.createAdminCourse.mockResolvedValue(course);
        const wrapper = render(AdminCourseFormPage); await flushPromises();
        expect(instructorApi.fetchInstructorOptions).toHaveBeenCalledWith({ page: 1 });
        await wrapper.get('#course-title').setValue('New course'); await wrapper.get('#course-slug').setValue('new-course');
        await wrapper.get('#course-category').setValue('2'); await wrapper.get('#course-instructor').setValue('8');
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(api.createAdminCourse).toHaveBeenCalledWith(expect.objectContaining({ category_id: 2, instructor_id: 8, status: 'draft' })); wrapper.unmount();
    });
    it('preserves the current and newly selected instructor across search result changes', async () => {
        state.route.params.id = 7;
        instructorApi.fetchInstructorOptions.mockResolvedValueOnce({ items: [{ id: 9, name: 'Other' }], meta: { current_page: 1, last_page: 1 } })
            .mockResolvedValueOnce({ items: [], meta: { current_page: 1, last_page: 1 } });
        const wrapper = render(AdminCourseFormPage); await flushPromises();
        expect(wrapper.get('#course-instructor').findAll('option').some((option) => option.text().includes('Teacher'))).toBe(true);
        await wrapper.get('#course-instructor').setValue('9');
        await wrapper.get('#course-instructor-search').setValue('nobody');
        await wrapper.findAll('button').find((button) => button.text() === 'Search').trigger('click'); await flushPromises();
        expect(wrapper.get('#course-instructor').element.value).toBe('9');
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(api.updateAdminCourse.mock.calls[0][1].instructor_id).toBe(9); wrapper.unmount();
    });
    it('reports instructor directory errors and keeps the assigned instructor', async () => {
        state.route.params.id = 7; instructorApi.fetchInstructorOptions.mockRejectedValue({ status: 500 });
        const wrapper = render(AdminCourseFormPage); await flushPromises();
        expect(wrapper.text()).toContain('Unable to load instructors'); expect(wrapper.get('#course-instructor').element.value).toBe('8'); wrapper.unmount();
    });
    it('loads another instructor options page without replacing earlier choices', async () => {
        state.route.params.id = 7;
        instructorApi.fetchInstructorOptions.mockResolvedValueOnce({ items: [{ id: 9, name: 'Other' }], meta: { current_page: 1, last_page: 2 } })
            .mockResolvedValueOnce({ items: [{ id: 10, name: 'Third' }], meta: { current_page: 2, last_page: 2 } });
        const wrapper = render(AdminCourseFormPage); await flushPromises();
        await wrapper.findAll('button').find((button) => button.text() === 'Load more instructors').trigger('click'); await flushPromises();
        expect(instructorApi.fetchInstructorOptions).toHaveBeenLastCalledWith({ page: 2 });
        expect(wrapper.get('#course-instructor').findAll('option').map((option) => option.text()).join(' ')).toContain('Third'); wrapper.unmount();
    });
    it('keeps course price as a decimal string, omits instructor, and sends null lifetime', async () => {
        state.route.params.id = 7;
        const wrapper = render(AdminCourseFormPage); await flushPromises();
        await wrapper.get('#course-price').setValue('129.95'); await wrapper.get('form').trigger('submit'); await flushPromises();
        const payload = api.updateAdminCourse.mock.calls[0][1];
        expect(payload.price).toBe('129.95'); expect(payload.access_duration_days).toBeNull(); expect(payload).not.toHaveProperty('instructor_id'); expect(payload).not.toHaveProperty('currency'); wrapper.unmount();
    });
    it('sends positive days and never zero for limited course access', async () => {
        state.route.params.id = 7;
        const wrapper = render(AdminCourseFormPage); await flushPromises();
        await wrapper.findAll('input[type="radio"]')[1].setValue(true);
        await wrapper.get('#course-days').setValue('90'); await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(api.updateAdminCourse.mock.calls[0][1].access_duration_days).toBe(90); wrapper.unmount();
    });
    it('does not serialize blank limited access as zero', async () => {
        state.route.params.id = 7;
        const wrapper = render(AdminCourseFormPage); await flushPromises();
        await wrapper.findAll('input[type="radio"]')[1].setValue(true);
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(api.updateAdminCourse).not.toHaveBeenCalled(); wrapper.unmount();
    });
    it('does not allow a content editor to publish without the publish permission', async () => {
        state.route.params.id = 7; state.permissions = ['courses.view', 'courses.update'];
        const wrapper = render(AdminCourseFormPage); await flushPromises();
        expect(wrapper.get('#course-status').find('option[value="published"]').attributes('disabled')).toBeDefined();
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(api.updateAdminCourse.mock.calls[0][1]).not.toHaveProperty('status'); wrapper.unmount();
    });
    it('submits an authorized publish transition and maps course slug 422 errors', async () => {
        state.route.params.id = 7;
        api.updateAdminCourse.mockRejectedValue({ status: 422, errors: { slug: ['Slug already taken.'] } });
        const wrapper = render(AdminCourseFormPage); await flushPromises();
        await wrapper.get('#course-status').setValue('published');
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(api.updateAdminCourse.mock.calls[0][1].status).toBe('published');
        expect(wrapper.text()).toContain('Slug already taken.'); wrapper.unmount();
    });
    it('prevents duplicate course saves while an update is pending', async () => {
        state.route.params.id = 7;
        const pending = deferred(); api.updateAdminCourse.mockReturnValue(pending.promise);
        const wrapper = render(AdminCourseFormPage); await flushPromises();
        await wrapper.get('form').trigger('submit'); await wrapper.get('form').trigger('submit');
        expect(api.updateAdminCourse).toHaveBeenCalledTimes(1);
        pending.resolve(course); await flushPromises(); wrapper.unmount();
    });
    it('does not render a foreign instructor course edit form from a direct URL', async () => {
        state.route.params.id = 7; state.roles = ['instructor']; state.user = { id: 99 };
        const wrapper = render(AdminCourseFormPage); await flushPromises();
        expect(wrapper.text()).toContain('do not have permission');
        expect(wrapper.find('#course-title').exists()).toBe(false);
        expect(api.updateAdminCourse).not.toHaveBeenCalled(); wrapper.unmount();
    });
});

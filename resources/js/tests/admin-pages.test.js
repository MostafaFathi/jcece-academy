import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import i18n, { setLocale } from '../i18n';
import * as api from '../api/admin';
import AdminDashboardPage from '../pages/AdminDashboardPage.vue';
import AdminCategoriesPage from '../pages/AdminCategoriesPage.vue';
import AdminCategoryFormPage from '../pages/AdminCategoryFormPage.vue';
import AdminCoursesPage from '../pages/AdminCoursesPage.vue';
import AdminCourseFormPage from '../pages/AdminCourseFormPage.vue';
import AppShellLayout from '../layouts/AppShellLayout.vue';

const state = vi.hoisted(() => ({ route: { params: {}, query: {} }, push: vi.fn(), permissions: [] }));
vi.mock('vue-router', async (original) => ({ ...(await original()), useRoute: () => state.route, useRouter: () => ({ push: state.push }) }));
vi.mock('../stores/auth', () => ({ useAuthStore: () => ({ can: (permission) => state.permissions.includes(permission) }) }));
vi.mock('../api/admin', () => Object.fromEntries(['fetchAdminCategories', 'fetchAdminCategory', 'createAdminCategory', 'updateAdminCategory', 'deleteAdminCategory', 'fetchAdminCourses', 'fetchAdminCourse', 'updateAdminCourse', 'deleteAdminCourse'].map((name) => [name, vi.fn()])));

const category = { id: 2, parent_id: null, name: 'Safety', slug: 'safety', description: '', image: null, icon: null, sort_order: 1, is_active: true };
const course = { id: 7, title: 'Safety course', slug: 'safety-course', category: { ...category }, instructor: { id: 8, name: 'Teacher' }, price: '123.45', compare_price: null, currency: 'ILS', access_duration_days: null, status: 'draft', level: 'beginner', language: 'ar', duration_minutes: 60, certificate_enabled: false, discussion_enabled: false, is_featured: false, thumbnail: null, promo_video_url: null, published_at: null, discount_starts_at: null, discount_ends_at: null, learning_outcomes: [], requirements: [], target_audiences: [], required_tools: [] };
function render(component, props = {}) { return mount(component, { props, global: { plugins: [i18n], stubs: { RouterLink: { props: ['to'], template: '<a><slot /></a>' } } } }); }
function deferred() { let resolve; const promise = new Promise((yes) => { resolve = yes; }); return { promise, resolve }; }
beforeEach(() => {
    vi.resetAllMocks(); setLocale('en'); state.route = { params: {}, query: {} }; state.permissions = ['categories.view', 'categories.create', 'categories.update', 'categories.delete', 'courses.view', 'courses.update', 'courses.delete', 'courses.publish'];
    api.fetchAdminCategories.mockResolvedValue({ items: [category], meta: { current_page: 1, last_page: 1, total: 1 } });
    api.fetchAdminCategory.mockResolvedValue(category);
    api.fetchAdminCourses.mockResolvedValue({ items: [course], meta: { current_page: 1, last_page: 1, total: 1 } });
    api.fetchAdminCourse.mockResolvedValue(course);
    api.createAdminCategory.mockResolvedValue(category); api.updateAdminCategory.mockResolvedValue(category); api.updateAdminCourse.mockResolvedValue(course);
    vi.spyOn(window, 'confirm').mockReturnValue(true);
});

describe('admin dashboard and lists', () => {
    it('shows only server pagination totals permitted to this user', async () => {
        state.permissions = ['courses.view'];
        api.fetchAdminCourses.mockImplementation(({ status } = {}) => Promise.resolve({ items: [], meta: { total: status === 'published' ? 4 : status === 'draft' ? 2 : 8 } }));
        const wrapper = render(AdminDashboardPage); await flushPromises();
        expect(wrapper.text()).toContain('Total courses'); expect(wrapper.text()).toContain('Published courses'); expect(wrapper.text()).not.toContain('Total categories'); expect(wrapper.text()).not.toContain('Total users');
        expect(api.fetchAdminCategories).not.toHaveBeenCalled(); wrapper.unmount();
    });
    it('surfaces partial dashboard errors without inventing metrics', async () => {
        api.fetchAdminCategories.mockRejectedValue({ status: 500 });
        const wrapper = render(AdminDashboardPage); await flushPromises();
        expect(wrapper.text()).toContain('Unable to load some summary metrics'); expect(wrapper.text()).toContain('Total courses'); wrapper.unmount();
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
    it('does not offer arbitrary reparenting on category edit', async () => {
        state.route.params.id = 2;
        api.fetchAdminCategory.mockImplementation((id) => Promise.resolve(id === 2 ? { ...category, parent_id: 1 } : { ...category, id: 1, name: 'Parent' }));
        const wrapper = render(AdminCategoryFormPage); await flushPromises();
        expect(wrapper.get('#category-parent').findAll('option').map((item) => item.text())).toEqual(['Top-level category', 'Parent']);
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(api.updateAdminCategory.mock.calls[0][1]).not.toHaveProperty('parent_id'); wrapper.unmount();
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
    it('does not allow a content editor to publish without the publish permission', async () => {
        state.route.params.id = 7; state.permissions = ['courses.view', 'courses.update'];
        const wrapper = render(AdminCourseFormPage); await flushPromises();
        expect(wrapper.get('#course-status').find('option[value="published"]').attributes('disabled')).toBeDefined();
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(api.updateAdminCourse.mock.calls[0][1]).not.toHaveProperty('status'); wrapper.unmount();
    });
});

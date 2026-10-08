import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import i18n, { setLocale } from '../i18n';
import * as packagesApi from '../api/admin-packages';
import * as admin from '../api/admin';
import AdminPackagesPage from '../pages/AdminPackagesPage.vue';
import AdminPackageFormPage from '../pages/AdminPackageFormPage.vue';
import AdminPackageCoursesPage from '../pages/AdminPackageCoursesPage.vue';

const state = vi.hoisted(() => ({ route: { params: {}, query: {} }, push: vi.fn(), permissions: [] }));
vi.mock('vue-router', async (original) => ({ ...(await original()), useRoute: () => state.route, useRouter: () => ({ push: state.push }) }));
vi.mock('../stores/auth', () => ({ useAuthStore: () => ({ can: (permission) => state.permissions.includes(permission) }) }));
vi.mock('../api/admin-packages', () => Object.fromEntries(['fetchAdminPackages', 'fetchAdminPackage', 'createAdminPackage', 'updateAdminPackage', 'deleteAdminPackage', 'fetchPackageCourses', 'addPackageCourse', 'updatePackageCourse', 'removePackageCourse', 'reorderPackageCourses'].map((name) => [name, vi.fn()])));
vi.mock('../api/admin', () => ({ fetchAdminCourses: vi.fn() }));

const item = { id: 4, title: 'BIM Path', slug: 'bim-path', type: 'learning_path', status: 'draft', price: '199.50', compare_price: null, currency: 'ILS', description: '', thumbnail: null, access_duration_days: null, is_sequential: true, sequential_completion_percentage: '80.00', published_at: null, course_count: 2, updated_at: '2026-09-30T10:00:00Z' };
const members = [{ id: 31, course_id: 9, course: { id: 9, title: 'BIM 1', status: 'published' }, is_required: true }, { id: 32, course_id: 10, course: { id: 10, title: 'BIM 2', status: 'draft' }, is_required: false }];
function render(component) { return mount(component, { global: { plugins: [i18n], stubs: { RouterLink: { props: ['to'], template: '<a><slot /></a>' } } } }); }
function deferred() { let resolve; const promise = new Promise((done) => { resolve = done; }); return { promise, resolve }; }
beforeEach(() => {
    vi.resetAllMocks(); setLocale('en'); state.route = { params: {}, query: {} }; state.permissions = ['packages.view', 'packages.create', 'packages.update', 'packages.delete', 'packages.publish', 'courses.view'];
    packagesApi.fetchAdminPackages.mockResolvedValue({ items: [item], meta: { current_page: 1, last_page: 2, total: 3 } });
    packagesApi.fetchAdminPackage.mockResolvedValue(item); packagesApi.fetchPackageCourses.mockImplementation(async () => structuredClone(members));
    admin.fetchAdminCourses.mockResolvedValue({ items: [{ id: 9, title: 'BIM 1', status: 'published' }, { id: 10, title: 'BIM 2', status: 'draft' }, { id: 11, title: 'BIM 3', status: 'draft' }], meta: { current_page: 1, last_page: 2 } });
    vi.spyOn(window, 'confirm').mockReturnValue(true);
});

describe('admin packages', () => {
    it('lists server counts, decimal price/currency and URL-backed filters', async () => {
        const wrapper = render(AdminPackagesPage); await flushPromises();
        expect(wrapper.text()).toContain('BIM Path'); expect(wrapper.text()).toContain('199.50 ILS'); expect(wrapper.text()).toContain('Lifetime access'); expect(wrapper.find('table').exists()).toBe(true);
        await wrapper.get('#package-search').setValue('BIM'); await wrapper.get('#package-type').setValue('learning_path'); await wrapper.get('form').trigger('submit');
        expect(state.push).toHaveBeenCalledWith({ name: 'admin.packages.index', query: { search: 'BIM', type: 'learning_path' } }); wrapper.unmount();
    });
    it('shows empty and read-only states by permission', async () => {
        state.permissions = ['packages.view']; packagesApi.fetchAdminPackages.mockResolvedValue({ items: [], meta: { current_page: 1, last_page: 1 } });
        const wrapper = render(AdminPackagesPage); await flushPromises(); expect(wrapper.text()).toContain('No packages'); expect(wrapper.text()).not.toContain('New package'); wrapper.unmount();
    });
    it('creates a lifetime draft with string money and server-owned currency', async () => {
        packagesApi.createAdminPackage.mockResolvedValue(item);
        const wrapper = render(AdminPackageFormPage); await flushPromises();
        await wrapper.get('#package-title').setValue('BIM Path'); await wrapper.get('#package-slug').setValue('bim-path'); await wrapper.get('#package-price').setValue('199.50');
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(packagesApi.createAdminPackage).toHaveBeenCalledWith(expect.objectContaining({ price: '199.50', access_duration_days: null, status: 'draft' }));
        expect(packagesApi.createAdminPackage.mock.calls[0][0]).not.toHaveProperty('currency'); expect(state.push).toHaveBeenCalledWith({ name: 'admin.packages.courses', params: { id: 4 } }); wrapper.unmount();
    });
    it('edits limited access and reports validation', async () => {
        state.route.params.id = 4; packagesApi.fetchAdminPackage.mockResolvedValue({ ...item, access_duration_days: 90 }); packagesApi.updateAdminPackage.mockRejectedValue({ status: 422, errors: { price: ['Invalid price'] } });
        const wrapper = render(AdminPackageFormPage); await flushPromises(); expect(wrapper.get('#package-days').element.value).toBe('90');
        await wrapper.get('#package-days').setValue('30'); await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(packagesApi.updateAdminPackage).toHaveBeenCalledWith(4, expect.objectContaining({ access_duration_days: 30, price: '199.50' })); expect(wrapper.text()).toContain('Invalid price'); wrapper.unmount();
    });
    it('loads current composition and a paginated selector without duplicates', async () => {
        state.route.params.id = 4;
        const wrapper = render(AdminPackageCoursesPage); await flushPromises();
        expect(wrapper.text()).toContain('future purchases only'); expect(wrapper.text()).toContain('BIM 1'); expect(wrapper.get('#package-course-select').findAll('option').find((option) => option.attributes('value') === '9').element.disabled).toBe(true);
        expect(admin.fetchAdminCourses).toHaveBeenCalledWith({ page: 1 });
        await wrapper.get('#package-course-select').setValue('11'); await wrapper.findAll('button').find((button) => button.text() === 'Add course').trigger('click'); await flushPromises();
        expect(packagesApi.addPackageCourse).toHaveBeenCalledWith(4, { course_id: 11, is_required: true }); wrapper.unmount();
    });
    it('removes and toggles membership without changing historical orders', async () => {
        state.route.params.id = 4; const wrapper = render(AdminPackageCoursesPage); await flushPromises();
        await wrapper.findAll('button').find((button) => button.text() === 'Make optional').trigger('click'); await flushPromises();
        expect(packagesApi.updatePackageCourse).toHaveBeenCalledWith(4, 31, { is_required: false });
        await wrapper.findAll('button').find((button) => button.text() === 'Delete').trigger('click'); await flushPromises();
        expect(packagesApi.removePackageCourse).toHaveBeenCalledWith(4, 31); expect(window.confirm).toHaveBeenCalled(); wrapper.unmount();
    });
    it('reorders by membership IDs, blocks duplicates and refetches on failure', async () => {
        state.route.params.id = 4; const pending = deferred(); packagesApi.reorderPackageCourses.mockReturnValue(pending.promise);
        const wrapper = render(AdminPackageCoursesPage); await flushPromises();
        const move = wrapper.findAll('button[aria-label="Move earlier"]').find((button) => !button.element.disabled);
        await move.trigger('click'); await move.trigger('click'); expect(packagesApi.reorderPackageCourses).toHaveBeenCalledTimes(1); expect(packagesApi.reorderPackageCourses).toHaveBeenCalledWith(4, [32, 31]);
        pending.resolve([]); await flushPromises(); expect(packagesApi.fetchPackageCourses).toHaveBeenCalledTimes(2);
        packagesApi.reorderPackageCourses.mockRejectedValue({ status: 422 }); await move.trigger('click'); await flushPromises();
        expect(packagesApi.fetchPackageCourses).toHaveBeenCalledTimes(3); expect(wrapper.text()).toContain('Review the highlighted fields'); wrapper.unmount();
    });
    it('hides course selector without course-read permission and honors RTL', async () => {
        state.route.params.id = 4; state.permissions = ['packages.view']; setLocale('ar');
        const wrapper = render(AdminPackageCoursesPage); await flushPromises();
        expect(wrapper.attributes('dir')).toBe('rtl'); expect(wrapper.find('#package-course-select').exists()).toBe(false); expect(admin.fetchAdminCourses).not.toHaveBeenCalled(); wrapper.unmount();
    });
});

import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import i18n, { setLocale } from '../i18n';
import * as usersApi from '../api/admin-users';
import * as instructorsApi from '../api/admin-instructors';
import AdminUsersPage from '../pages/AdminUsersPage.vue';
import AdminUserFormPage from '../pages/AdminUserFormPage.vue';
import AdminInstructorsPage from '../pages/AdminInstructorsPage.vue';
import AdminInstructorFormPage from '../pages/AdminInstructorFormPage.vue';

const state = vi.hoisted(() => ({ route: { params: {}, query: {} }, push: vi.fn(), permissions: [], roles: ['admin'] }));
vi.mock('vue-router', async (original) => ({ ...(await original()), useRoute: () => state.route, useRouter: () => ({ push: state.push }) }));
vi.mock('../stores/auth', () => ({ useAuthStore: () => ({ can: (permission) => state.permissions.includes(permission), hasRole: (role) => state.roles.includes(role) }) }));
vi.mock('../api/admin-users', () => Object.fromEntries(['fetchAdminUsers', 'fetchAdminUser', 'createAdminUser', 'updateAdminUser'].map((name) => [name, vi.fn()])));
vi.mock('../api/admin-instructors', () => Object.fromEntries(['fetchAdminInstructors', 'fetchAdminInstructor', 'createAdminInstructor', 'updateAdminInstructor'].map((name) => [name, vi.fn()])));

const user = { id: 9, name: 'Learner', email: 'learner@example.test', status: 'active', roles: ['student'], created_at: '2026-01-01T00:00:00Z' };
const instructor = { id: 10, name: 'Trainer', email: 'trainer@example.test', status: 'active', profile: { job_title: 'Engineer', specialties: ['Safety'], is_featured: false } };
function render(component) { return mount(component, { global: { plugins: [i18n], stubs: { RouterLink: { props: ['to'], template: '<a><slot /></a>' } } } }); }
function deferred() { let resolve; const promise = new Promise((yes) => { resolve = yes; }); return { promise, resolve }; }
beforeEach(() => {
    vi.resetAllMocks(); setLocale('en'); state.route = { params: {}, query: {} }; state.roles = ['admin']; state.permissions = ['users.view', 'users.update', 'users.manage', 'roles.manage', 'instructors.view', 'instructors.update', 'instructors.manage'];
    usersApi.fetchAdminUsers.mockResolvedValue({ items: [user], meta: { current_page: 1, last_page: 2 } });
    usersApi.fetchAdminUser.mockResolvedValue(user);
    usersApi.createAdminUser.mockResolvedValue(user); usersApi.updateAdminUser.mockResolvedValue(user);
    instructorsApi.fetchAdminInstructors.mockResolvedValue({ items: [instructor], meta: { current_page: 1, last_page: 2 } });
    instructorsApi.fetchAdminInstructor.mockResolvedValue(instructor);
    instructorsApi.createAdminInstructor.mockResolvedValue(instructor); instructorsApi.updateAdminInstructor.mockResolvedValue(instructor);
    vi.spyOn(window, 'confirm').mockReturnValue(true);
});

describe('admin user management', () => {
    it('renders desktop/mobile records and URL-synced search, role, status and pagination', async () => {
        const wrapper = render(AdminUsersPage); await flushPromises();
        expect(wrapper.findAll('article')).toHaveLength(1); expect(wrapper.text()).toContain('learner@example.test');
        await wrapper.get('#user-search').setValue('Learner'); await wrapper.get('#user-role').setValue('student'); await wrapper.get('#user-status').setValue('active'); await wrapper.get('form').trigger('submit');
        expect(state.push).toHaveBeenCalledWith({ name: 'admin.users.index', query: { search: 'Learner', role: 'student', status: 'active' } });
        wrapper.findComponent({ name: 'PaginationNav' }).vm.$emit('change', 2);
        expect(state.push).toHaveBeenCalledWith({ name: 'admin.users.index', query: { page: 2 } });
        wrapper.unmount();
    });
    it('shows loading, empty and retry states', async () => {
        const pending = deferred(); usersApi.fetchAdminUsers.mockReturnValueOnce(pending.promise);
        const loading = render(AdminUsersPage); expect(loading.text()).toContain('Loading'); pending.resolve({ items: [], meta: {} }); await flushPromises(); expect(loading.text()).toContain('No users'); loading.unmount();
        usersApi.fetchAdminUsers.mockRejectedValueOnce({ status: 500 }); const failed = render(AdminUsersPage); await flushPromises(); expect(failed.text()).toContain('Unable to load'); failed.unmount();
    });
    it('creates a validated user with controlled roles and no instructor role', async () => {
        usersApi.createAdminUser.mockRejectedValue({ status: 422, errors: { email: ['Already used.'] } });
        const wrapper = render(AdminUserFormPage); await flushPromises();
        expect(wrapper.find('#user-add-role option[value="instructor"]').exists()).toBe(false);
        await wrapper.get('#user-name').setValue('New learner'); await wrapper.get('#user-email').setValue('new@example.test'); await wrapper.get('#user-password').setValue('StrongPass123'); await wrapper.get('#user-confirm').setValue('StrongPass123');
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(usersApi.createAdminUser).toHaveBeenCalledWith(expect.objectContaining({ roles: ['student'], password: 'StrongPass123' })); expect(wrapper.text()).toContain('Already used.'); wrapper.unmount();
    });
    it('edits a user without sending a blank password and confirms role changes', async () => {
        state.route.params.id = 9; const wrapper = render(AdminUserFormPage); await flushPromises();
        expect(wrapper.get('#user-password').element.value).toBe('');
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(usersApi.updateAdminUser.mock.calls[0][1]).not.toHaveProperty('password');
        await wrapper.get('#user-add-role').setValue('content_manager'); await wrapper.findAll('button').find((button) => button.text() === 'Add role').trigger('click'); await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(window.confirm).toHaveBeenCalled(); expect(usersApi.updateAdminUser.mock.calls[1][1].roles).toContain('content_manager'); wrapper.unmount();
    });
    it('distinguishes inherited and direct permissions on an authorized user detail', async () => {
        state.route.params.id = 9;
        usersApi.fetchAdminUser.mockResolvedValueOnce({ ...user, permission_sources: { inherited: ['courses.view'], direct: ['support_tickets.reply'] } });
        const wrapper = render(AdminUserFormPage);
        await flushPromises();
        expect(wrapper.text()).toContain('Inherited from roles');
        expect(wrapper.text()).toContain('View courses');
        expect(wrapper.text()).toContain('Reply to support tickets');
        expect(wrapper.text()).not.toContain('support_tickets.reply');
        wrapper.unmount();
    });
    it('keeps sensitive controls hidden from an account editor without manage permission', async () => {
        state.route.params.id = 9; state.permissions = ['users.view', 'users.update']; const wrapper = render(AdminUserFormPage); await flushPromises();
        expect(wrapper.find('#user-password').exists()).toBe(false); expect(wrapper.find('#user-status').exists()).toBe(false);
        await wrapper.get('form').trigger('submit'); await flushPromises(); expect(usersApi.updateAdminUser.mock.calls[0][1]).toEqual({ name: 'Learner', email: 'learner@example.test' }); wrapper.unmount();
    });
    it('hides user creation from a reader and uses Arabic RTL', async () => {
        state.permissions = ['users.view']; setLocale('ar');
        const wrapper = render(AdminUsersPage); await flushPromises();
        expect(wrapper.attributes('dir')).toBe('rtl'); expect(wrapper.text()).not.toContain('مستخدم جديد'); wrapper.unmount();
    });
    it('prevents duplicate user saves', async () => {
        const pending = deferred(); usersApi.createAdminUser.mockReturnValue(pending.promise); const wrapper = render(AdminUserFormPage); await flushPromises();
        await wrapper.get('form').trigger('submit'); await wrapper.get('form').trigger('submit'); expect(usersApi.createAdminUser).toHaveBeenCalledTimes(1);
        pending.resolve(user); await flushPromises(); wrapper.unmount();
    });
});

describe('admin instructor management', () => {
    it('lists and searches paginated instructors with mobile cards', async () => {
        const wrapper = render(AdminInstructorsPage); await flushPromises(); expect(wrapper.findAll('article')).toHaveLength(1); expect(wrapper.text()).toContain('Engineer');
        await wrapper.get('#instructor-search').setValue('Trainer'); await wrapper.get('form').trigger('submit');
        expect(state.push).toHaveBeenCalledWith({ name: 'admin.instructors.index', query: { search: 'Trainer' } }); wrapper.unmount();
    });
    it('advances the instructor list through server pagination', async () => {
        const wrapper = render(AdminInstructorsPage); await flushPromises();
        wrapper.findComponent({ name: 'PaginationNav' }).vm.$emit('change', 2);
        expect(state.push).toHaveBeenCalledWith({ name: 'admin.instructors.index', query: { page: 2 } }); wrapper.unmount();
    });
    it('shows empty and error states', async () => {
        instructorsApi.fetchAdminInstructors.mockResolvedValueOnce({ items: [], meta: {} }); const empty = render(AdminInstructorsPage); await flushPromises(); expect(empty.text()).toContain('No instructors'); empty.unmount();
        instructorsApi.fetchAdminInstructors.mockRejectedValueOnce({ status: 500 }); const failed = render(AdminInstructorsPage); await flushPromises(); expect(failed.text()).toContain('Unable to load'); failed.unmount();
    });
    it('creates account and profile atomically through one API call and maps validation', async () => {
        instructorsApi.createAdminInstructor.mockRejectedValue({ status: 422, errors: { job_title: ['Invalid job.'] } });
        const wrapper = render(AdminInstructorFormPage); await flushPromises();
        await wrapper.get('#instructor-name').setValue('New Trainer'); await wrapper.get('#instructor-email').setValue('new@example.test'); await wrapper.get('#instructor-password').setValue('StrongPass123'); await wrapper.get('#instructor-confirm').setValue('StrongPass123'); await wrapper.get('#specialties').setValue('Safety\nTraining');
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(instructorsApi.createAdminInstructor).toHaveBeenCalledWith(expect.objectContaining({ name: 'New Trainer', specialties: ['Safety', 'Training'] })); expect(wrapper.text()).toContain('Invalid job.'); wrapper.unmount();
    });
    it('limits content managers to profile edits', async () => {
        state.route.params.id = 10; state.permissions = ['instructors.view', 'instructors.update']; const wrapper = render(AdminInstructorFormPage); await flushPromises();
        expect(wrapper.find('#instructor-email').exists()).toBe(false); await wrapper.get('#job-title').setValue('Senior Engineer'); await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(instructorsApi.updateAdminInstructor.mock.calls[0][1]).not.toHaveProperty('name'); expect(instructorsApi.updateAdminInstructor.mock.calls[0][1].job_title).toBe('Senior Engineer'); wrapper.unmount();
    });
    it('edits account and profile without prefilled password and prevents duplicate saves', async () => {
        state.route.params.id = 10; const pending = deferred(); instructorsApi.updateAdminInstructor.mockReturnValue(pending.promise);
        const wrapper = render(AdminInstructorFormPage); await flushPromises(); expect(wrapper.get('#instructor-password').element.value).toBe('');
        await wrapper.get('form').trigger('submit'); await wrapper.get('form').trigger('submit');
        expect(instructorsApi.updateAdminInstructor).toHaveBeenCalledTimes(1); expect(instructorsApi.updateAdminInstructor.mock.calls[0][1]).not.toHaveProperty('password'); pending.resolve(instructor); await flushPromises(); wrapper.unmount();
    });
    it('sends an explicit instructor password change with confirmation', async () => {
        state.route.params.id = 10; const wrapper = render(AdminInstructorFormPage); await flushPromises();
        await wrapper.get('#instructor-password').setValue('ChangedPass123'); await wrapper.get('#instructor-confirm').setValue('ChangedPass123');
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(instructorsApi.updateAdminInstructor.mock.calls[0][1]).toMatchObject({ password: 'ChangedPass123', password_confirmation: 'ChangedPass123' }); wrapper.unmount();
    });
    it('sets Arabic document direction when locale changes', async () => {
        setLocale('ar'); const wrapper = render(AdminInstructorsPage); await flushPromises(); expect(wrapper.attributes('dir')).toBe('rtl'); wrapper.unmount();
    });
});

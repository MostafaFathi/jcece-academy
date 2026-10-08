import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import i18n, { setLocale } from '../i18n';
import AdminRolesPage from '../pages/AdminRolesPage.vue';
import * as roleApi from '../api/role-permissions';

const initialize = vi.hoisted(() => vi.fn());
vi.mock('../stores/auth', () => ({ useAuthStore: () => ({ initialize }) }));
vi.mock('../api/role-permissions', () => ({ fetchRolePermissionMatrix: vi.fn(), fetchRolePermissions: vi.fn(), saveRolePermissions: vi.fn() }));

const permissions = [
    { key: 'courses.view', group: 'courses', sensitive: false },
    { key: 'courses.create', group: 'courses', sensitive: false },
    { key: 'users.manage', group: 'access', sensitive: true },
    { key: 'roles.manage', group: 'access', sensitive: true },
];
const roles = [
    { role: 'admin', permissions: ['courses.view', 'users.manage', 'roles.manage'], version: 'a'.repeat(64), unmanaged_count: 0 },
    { role: 'content_manager', permissions: ['courses.view'], version: 'b'.repeat(64), unmanaged_count: 0 },
    { role: 'instructor', permissions: ['courses.view'], version: 'c'.repeat(64), unmanaged_count: 0 },
    { role: 'sales_support', permissions: [], version: 'd'.repeat(64), unmanaged_count: 0 },
    { role: 'student', permissions: [], version: 'e'.repeat(64), unmanaged_count: 0 },
];
function render() { return mount(AdminRolesPage, { global: { plugins: [i18n], stubs: { PageHeading: { template: '<h1><slot /></h1>' } } } }); }
function button(wrapper, label, occurrence = 0) { return wrapper.findAll('button').filter((item) => item.text().includes(label))[occurrence]; }

beforeEach(() => {
    vi.resetAllMocks();
    setLocale('en');
    roleApi.fetchRolePermissionMatrix.mockResolvedValue({ roles, permissions });
    roleApi.fetchRolePermissions.mockImplementation(async (role) => roles.find((item) => item.role === role));
    roleApi.saveRolePermissions.mockImplementation(async (role, selected) => ({ role, permissions: selected, version: 'f'.repeat(64), unmanaged_count: 0 }));
    initialize.mockResolvedValue(null);
    vi.spyOn(window, 'confirm').mockReturnValue(true);
});

describe('Admin roles and permissions page', () => {
    it('shows a loading state until the role matrix arrives', async () => {
        let resolve;
        roleApi.fetchRolePermissionMatrix.mockReturnValueOnce(new Promise((done) => { resolve = done; }));
        const wrapper = render();
        expect(wrapper.text()).toContain('Loading');
        resolve({ roles, permissions });
        await flushPromises();
        expect(wrapper.findAll('[role="tab"]')).toHaveLength(5);
        wrapper.unmount();
    });

    it('loads five roles, grouped permissions, a matrix and LTR/RTL translations', async () => {
        const wrapper = render();
        await flushPromises();
        expect(roleApi.fetchRolePermissionMatrix).toHaveBeenCalledOnce();
        expect(wrapper.findAll('[role="tab"]')).toHaveLength(5);
        expect(wrapper.text()).toContain('Users & access');
        expect(wrapper.text()).toContain('View courses');
        expect(wrapper.findAll('[aria-label="Role matrix"]')).toHaveLength(4);
        expect(wrapper.attributes('dir')).toBe('ltr');
        setLocale('ar');
        await flushPromises();
        expect(wrapper.attributes('dir')).toBe('rtl');
        expect(wrapper.text()).toContain('عرض الدورات');
        wrapper.unmount();
    });

    it('switches roles, searches permissions and blocks adding role management to non-admin roles', async () => {
        const wrapper = render();
        await flushPromises();
        await button(wrapper, 'Instructor').trigger('click');
        await flushPromises();
        expect(roleApi.fetchRolePermissions).toHaveBeenCalledWith('instructor');
        await wrapper.get('#permission-search').setValue('manage');
        expect(wrapper.text()).toContain('Manage users');
        expect(wrapper.text()).not.toContain('View courses');
        expect(wrapper.findAll('input[type="checkbox"]').find((input) => input.element.disabled)).toBeTruthy();
        wrapper.unmount();
    });

    it('supports group selection, review, save and reset without automatic writes', async () => {
        const wrapper = render();
        await flushPromises();
        await button(wrapper, 'Select shown', 1).trigger('click');
        expect(wrapper.text()).toContain('Unsaved changes');
        expect(roleApi.saveRolePermissions).not.toHaveBeenCalled();
        await button(wrapper, 'Discard changes').trigger('click');
        expect(wrapper.text()).toContain('No changes to save');
        await button(wrapper, 'Select shown', 1).trigger('click');
        await button(wrapper, 'Review changes').trigger('click');
        expect(wrapper.text()).toContain('Enable: Create courses');
        await button(wrapper, 'Save permissions').trigger('click');
        await flushPromises();
        expect(roleApi.saveRolePermissions).toHaveBeenCalledWith('admin', expect.arrayContaining(['courses.create']), 'a'.repeat(64));
        expect(initialize).toHaveBeenCalledWith({ force: true });
        wrapper.unmount();
    });

    it('clears only the currently shown permissions in a group', async () => {
        const wrapper = render();
        await flushPromises();
        await wrapper.get('#permission-search').setValue('View courses');
        await button(wrapper, 'Clear shown').trigger('click');
        expect(wrapper.text()).toContain('Unsaved changes');
        await button(wrapper, 'Review changes').trigger('click');
        expect(wrapper.text()).toContain('Disable: View courses');
        expect(wrapper.text()).not.toContain('Disable: Manage users');
        wrapper.unmount();
    });

    it('requires explicit acknowledgment for sensitive removals and shows a consequence summary', async () => {
        const wrapper = render();
        await flushPromises();
        const roleManage = wrapper.findAll('label').find((label) => label.text().includes('Manage roles and permissions'));
        await roleManage.get('input[type="checkbox"]').setValue(false);
        await button(wrapper, 'Review changes').trigger('click');
        expect(wrapper.text()).toContain('Removing role management from Administrator can lock out administrators');
        expect(button(wrapper, 'Save permissions').element.disabled).toBe(true);
        await wrapper.findAll('input[type="checkbox"]').at(-1).setValue(true);
        expect(button(wrapper, 'Save permissions').element.disabled).toBe(false);
        wrapper.unmount();
    });

    it('surfaces stale write and forbidden responses without overwriting the draft', async () => {
        const wrapper = render();
        await flushPromises();
        await button(wrapper, 'Select shown', 1).trigger('click');
        await button(wrapper, 'Review changes').trigger('click');
        roleApi.saveRolePermissions.mockRejectedValueOnce({ status: 409 });
        await button(wrapper, 'Save permissions').trigger('click');
        await flushPromises();
        expect(wrapper.text()).toContain('Another administrator changed this role');
        expect(wrapper.text()).toContain('Unsaved changes');
        wrapper.unmount();

        roleApi.fetchRolePermissionMatrix.mockRejectedValueOnce({ status: 403 });
        const forbidden = render();
        await flushPromises();
        expect(forbidden.text()).toContain('Only an authorized administrator');
        forbidden.unmount();
    });
});

import { describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import { routes } from '../router';
import { canAccessRoute, createAccessGuard } from '../router/access';
import { navigationByArea, visibleNavigation } from '../composables/navigation';

const router = createRouter({ history: createMemoryHistory(), routes });
const auth = (permissions, authenticated = true) => ({ isAuthenticated: authenticated, initialize: vi.fn().mockResolvedValue(null), can: (permission) => permissions.includes(permission), canAny: (required) => required.some((permission) => permissions.includes(permission)), hasAnyRole: () => false });

describe('Phase 13A admin boundaries', () => {
    it('allows an instructor with an explicit backoffice capability through the admin route guard', async () => {
        const permissions = ['courses.view', 'curriculum.view'];
        const instructor = {
            ...auth(permissions), permissions,
            hasRole: (role) => role === 'instructor',
            hasAnyRole: (roles) => roles.includes('instructor'),
        };

        expect(await createAccessGuard(instructor)(router.resolve('/admin/courses/5/curriculum'))).toBe(true);
        expect(await createAccessGuard(instructor)(router.resolve('/admin/packages'))).toEqual({ name: 'forbidden' });
        expect(await createAccessGuard(instructor)(router.resolve('/admin'))).toEqual({ name: 'forbidden' });
        expect(await createAccessGuard(instructor)(router.resolve('/admin/courses'))).toBe(true);
    });
    it('rejects a student with shared catalog view permissions from direct admin URLs', async () => {
        const student = { ...auth(['courses.view', 'categories.view']), hasAnyRole: () => false, hasRole: () => false };
        expect(await createAccessGuard(student)(router.resolve('/admin'))).toEqual({ name: 'forbidden' });
        expect(await createAccessGuard(student)(router.resolve('/admin/courses'))).toEqual({ name: 'forbidden' });
    });
    it('keeps permission-only site content reachable and admin-only audit free of unrelated permission requirements', async () => {
        const staff = { ...auth(['site_content.manage']), hasRole: (role) => role === 'content_manager', hasAnyRole: (roles) => roles.includes('content_manager') };
        const administrator = { ...auth([]), hasRole: (role) => role === 'admin', hasAnyRole: (roles) => roles.includes('admin') };
        expect(await createAccessGuard(staff)(router.resolve('/admin/site-content'))).toBe(true);
        expect(await createAccessGuard(administrator)(router.resolve('/admin/audit'))).toBe(true);
    });
    it.each(['/admin', '/admin/categories', '/admin/categories/new', '/admin/categories/3/edit', '/admin/courses', '/admin/courses/new', '/admin/courses/5/edit', '/admin/users', '/admin/users/new', '/admin/users/5/edit', '/admin/instructors', '/admin/instructors/new', '/admin/instructors/5/edit'])('requires authentication for %s', async (path) => {
        const route = router.resolve(path);
        expect(route.meta.requiresAuth).toBe(true);
        expect(await createAccessGuard(auth([], false))(route)).toEqual({ name: 'login', query: { redirect: path } });
    });
    it('matches actual category and course permissions, including publish separate from edit', () => {
        expect(canAccessRoute(auth(['categories.view']), router.resolve('/admin/categories').meta)).toBe(true);
        expect(canAccessRoute(auth(['categories.view']), router.resolve('/admin/categories/new').meta)).toBe(false);
        expect(canAccessRoute(auth(['courses.view']), router.resolve('/admin/courses').meta)).toBe(true);
        expect(canAccessRoute(auth(['courses.view']), router.resolve('/admin/courses/5/edit').meta)).toBe(false);
        expect(canAccessRoute(auth(['courses.update']), router.resolve('/admin/courses/5/edit').meta)).toBe(false);
        expect(canAccessRoute(auth(['courses.view', 'courses.update']), router.resolve('/admin/courses/5/edit').meta)).toBe(true);
    });
    it('shows only permitted modules in the admin navigation', () => {
        expect(navigationByArea.admin.map((item) => item.route)).toEqual(['admin.reports', 'admin.dashboard', 'admin.users.index', 'admin.instructors.index', 'admin.roles.index', 'admin.categories.index', 'admin.courses.index', 'admin.packages.index', 'admin.audit.index', 'admin.orders.index', 'admin.coupons.index', 'admin.payments.index', 'admin.reviews.index', 'admin.certificates.index', 'admin.tickets.index', 'admin.policy-pages', 'admin.site-content']);
        expect(visibleNavigation(navigationByArea.admin, auth(['coupons.view'])).map((item) => item.route)).toEqual(['admin.coupons.index']);
        expect(canAccessRoute(auth(['orders.view']), router.resolve('/admin/coupons').meta)).toBe(false);
        expect(canAccessRoute(auth(['coupons.view']), router.resolve('/admin/coupons').meta)).toBe(true);
        expect(visibleNavigation(navigationByArea.admin, auth(['courses.view'])).map((item) => item.route)).toEqual(['admin.courses.index']);
        expect(visibleNavigation(navigationByArea.admin, auth(['categories.view'])).map((item) => item.route)).toEqual(['admin.categories.index']);
        expect(visibleNavigation(navigationByArea.admin, auth(['instructors.view'])).map((item) => item.route)).toEqual(['admin.instructors.index']);
        expect(visibleNavigation(navigationByArea.admin, auth(['users.view'])).map((item) => item.route)).toEqual(['admin.users.index']);
        expect(visibleNavigation(navigationByArea.admin, auth(['packages.view'])).map((item) => item.route)).toEqual(['admin.packages.index']);
        expect(visibleNavigation(navigationByArea.admin, { ...auth(['courses.view']), hasAnyRole: (roles) => roles.includes('admin') }).map((item) => item.route)).toEqual(['admin.dashboard', 'admin.courses.index', 'admin.audit.index']);
        expect(router.resolve('/admin/content').matched.at(-1).redirect).toEqual({ name: 'admin.courses.index' });
    });
    it('separates user/instructor read, update and privileged create routes', () => {
        expect(canAccessRoute(auth(['users.view']), router.resolve('/admin/users').meta)).toBe(true);
        expect(canAccessRoute(auth(['users.view']), router.resolve('/admin/users/new').meta)).toBe(false);
        expect(canAccessRoute(auth(['roles.manage']), router.resolve('/admin/roles').meta)).toBe(false);
        expect(canAccessRoute(auth(['users.view', 'users.update']), router.resolve('/admin/users/5/edit').meta)).toBe(true);
        expect(canAccessRoute(auth(['instructors.view', 'instructors.update']), router.resolve('/admin/instructors/5/edit').meta)).toBe(true);
        expect(canAccessRoute(auth(['instructors.view', 'instructors.update']), router.resolve('/admin/instructors/new').meta)).toBe(false);
        expect(canAccessRoute(auth(['courses.view', 'courses.create']), router.resolve('/admin/courses/new').meta)).toBe(true);
    });
});

describe('Phase 13B curriculum and package boundaries', () => {
    it.each(['/admin/courses/5/curriculum', '/admin/packages', '/admin/packages/new', '/admin/packages/4/edit', '/admin/packages/4/courses'])('requires authentication for %s', async (path) => {
        const route = router.resolve(path);
        expect(route.meta.requiresAuth).toBe(true);
        expect(await createAccessGuard(auth([], false))(route)).toEqual({ name: 'login', query: { redirect: path } });
    });
    it('gates metadata, composition and curriculum by their own permissions', () => {
        expect(canAccessRoute(auth(['courses.view']), router.resolve('/admin/courses/5/curriculum').meta)).toBe(false);
        expect(canAccessRoute(auth(['curriculum.view']), router.resolve('/admin/courses/5/curriculum').meta)).toBe(false);
        expect(canAccessRoute(auth(['courses.view', 'curriculum.view']), router.resolve('/admin/courses/5/curriculum').meta)).toBe(true);
        expect(canAccessRoute(auth(['packages.view']), router.resolve('/admin/packages').meta)).toBe(true);
        expect(canAccessRoute(auth(['packages.view']), router.resolve('/admin/packages/new').meta)).toBe(false);
        expect(canAccessRoute(auth(['packages.view']), router.resolve('/admin/packages/4/courses').meta)).toBe(true);
        expect(canAccessRoute(auth(['packages.view', 'packages.update']), router.resolve('/admin/packages/4/edit').meta)).toBe(true);
    });
});

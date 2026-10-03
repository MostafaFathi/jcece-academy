import { describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import { routes } from '../router';
import { canAccessRoute, createAccessGuard } from '../router/access';
import { navigationByArea, visibleNavigation } from '../composables/navigation';

const router = createRouter({ history: createMemoryHistory(), routes });
const auth = (permissions, authenticated = true) => ({ isAuthenticated: authenticated, initialize: vi.fn().mockResolvedValue(null), can: (permission) => permissions.includes(permission), canAny: (required) => required.some((permission) => permissions.includes(permission)), hasAnyRole: () => false });

describe('Phase 13A admin boundaries', () => {
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
        expect(navigationByArea.admin.map((item) => item.route)).toEqual(['admin.dashboard', 'admin.categories.index', 'admin.courses.index', 'admin.packages.index', 'admin.users.index', 'admin.instructors.index', 'admin.orders.index', 'admin.payments.index', 'admin.reviews.index', 'admin.certificates.index', 'admin.tickets.index', 'admin.policy-pages']);
        expect(visibleNavigation(navigationByArea.admin, auth(['courses.view'])).map((item) => item.route)).toEqual(['admin.dashboard', 'admin.courses.index']);
        expect(visibleNavigation(navigationByArea.admin, auth(['categories.view'])).map((item) => item.route)).toEqual(['admin.dashboard', 'admin.categories.index']);
        expect(visibleNavigation(navigationByArea.admin, auth(['instructors.view'])).map((item) => item.route)).toEqual(['admin.dashboard', 'admin.instructors.index']);
        expect(visibleNavigation(navigationByArea.admin, auth(['users.view'])).map((item) => item.route)).toEqual(['admin.dashboard', 'admin.users.index']);
        expect(visibleNavigation(navigationByArea.admin, auth(['packages.view'])).map((item) => item.route)).toEqual(['admin.dashboard', 'admin.packages.index']);
        expect(router.resolve('/admin/content').matched.at(-1).redirect).toEqual({ name: 'admin.courses.index' });
    });
    it('separates user/instructor read, update and privileged create routes', () => {
        expect(canAccessRoute(auth(['users.view']), router.resolve('/admin/users').meta)).toBe(true);
        expect(canAccessRoute(auth(['users.view']), router.resolve('/admin/users/new').meta)).toBe(false);
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

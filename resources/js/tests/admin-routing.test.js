import { describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import { routes } from '../router';
import { canAccessRoute, createAccessGuard } from '../router/access';
import { navigationByArea, visibleNavigation } from '../composables/navigation';

const router = createRouter({ history: createMemoryHistory(), routes });
const auth = (permissions, authenticated = true) => ({ isAuthenticated: authenticated, initialize: vi.fn().mockResolvedValue(null), can: (permission) => permissions.includes(permission), canAny: (required) => required.some((permission) => permissions.includes(permission)), hasAnyRole: () => false });

describe('Phase 13A admin boundaries', () => {
    it.each(['/admin', '/admin/categories', '/admin/categories/new', '/admin/categories/3/edit', '/admin/courses', '/admin/courses/5/edit'])('requires authentication for %s', async (path) => {
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
    it('shows only implemented modules in the admin navigation', () => {
        expect(navigationByArea.admin.map((item) => item.route)).toEqual(['admin.dashboard', 'admin.categories.index', 'admin.courses.index']);
        expect(visibleNavigation(navigationByArea.admin, auth(['courses.view'])).map((item) => item.route)).toEqual(['admin.dashboard', 'admin.courses.index']);
        expect(visibleNavigation(navigationByArea.admin, auth(['categories.view'])).map((item) => item.route)).toEqual(['admin.dashboard', 'admin.categories.index']);
        expect(router.resolve('/admin/content').matched.at(-1).redirect).toEqual({ name: 'admin.courses.index' });
    });
});

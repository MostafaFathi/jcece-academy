import { describe, expect, it, vi } from 'vitest';
import { canAccessRoute, createAccessGuard } from '../router/access';
import { visibleNavigation } from '../composables/navigation';

function authStub({ authenticated = true, roles = [], permissions = [] } = {}) {
    return {
        isAuthenticated: authenticated,
        initialize: vi.fn().mockResolvedValue(null),
        hasRole: (role) => roles.includes(role),
        hasAnyRole: (required) => required.some((role) => roles.includes(role)),
        can: (permission) => permissions.includes(permission),
        canAny: (required) => required.some((permission) => permissions.includes(permission)),
    };
}

describe('access control', () => {
    it('redirects guests to login with their intended URL', async () => {
        const result = await createAccessGuard(authStub({ authenticated: false }))({ name: 'student.dashboard', fullPath: '/student', meta: { requiresAuth: true } });
        expect(result).toEqual({ name: 'login', query: { redirect: '/student' } });
    });

    it('redirects authenticated users away from guest routes', async () => {
        const result = await createAccessGuard(authStub({ roles: ['instructor'] }))({ name: 'login', fullPath: '/login', meta: { guestOnly: true } });
        expect(result).toEqual({ name: 'instructor.dashboard' });
    });

    it('denies protected routes when permissions are missing', async () => {
        const result = await createAccessGuard(authStub())({ name: 'admin.dashboard', fullPath: '/admin', meta: { requiresAuth: true, permissions: ['courses.view'] } });
        expect(result).toEqual({ name: 'forbidden' });
    });

    it('supports all and any permission route rules', () => {
        const auth = authStub({ permissions: ['courses.view', 'categories.view'] });
        expect(canAccessRoute(auth, { permissions: ['courses.view', 'categories.view'] })).toBe(true);
        expect(canAccessRoute(auth, { anyPermission: ['users.view', 'courses.view'] })).toBe(true);
    });

    it('hides navigation entries the user cannot access', () => {
        const items = [{ route: 'open' }, { route: 'courses', permissions: ['courses.view'] }, { route: 'users', permissions: ['users.view'] }];
        expect(visibleNavigation(items, authStub({ permissions: ['courses.view'] })).map((item) => item.route)).toEqual(['open', 'courses']);
    });
});

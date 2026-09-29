import { describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import { routes } from '../router';
import { createAccessGuard } from '../router/access';
import { navigationByArea } from '../composables/navigation';

describe('learning route boundaries', () => {
    const router = createRouter({ history: createMemoryHistory(), routes });
    it.each(['/student/courses', '/learn/courses/bim'])('protects %s without an invented API role constraint', async (path) => {
        const route = router.resolve(path);
        expect(route.meta.requiresAuth).toBe(true); expect(route.meta.roles).toEqual([]);
        const auth = { initialize: vi.fn().mockResolvedValue(null), isAuthenticated: false, hasAnyRole: () => false };
        expect(await createAccessGuard(auth)(route)).toEqual({ name: 'login', query: { redirect: path } });
        auth.isAuthenticated = true; expect(await createAccessGuard(auth)(route)).toBe(true);
    });
    it('keeps public details separate and preserves the learning placeholder URL as a redirect', () => {
        expect(router.resolve('/courses/bim').meta.requiresAuth).not.toBe(true);
        expect(router.resolve('/learn/courses/bim').params.slug).toBe('bim');
        expect(router.resolve('/student/learning').matched.at(-1).redirect).toEqual({ name: 'student.courses.index' });
        expect(navigationByArea.student.map((item) => item.route)).toEqual(expect.arrayContaining(['student.courses.index', 'student.cart', 'student.orders.index']));
        expect(navigationByArea.student.map((item) => item.route).join(' ')).not.toMatch(/quiz|assignment|certificate/);
    });
});

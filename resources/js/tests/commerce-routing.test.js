import { describe, expect, it, vi } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import { routes } from '../router';
import { createAccessGuard } from '../router/access';
import { navigationByArea } from '../composables/navigation';

describe('commerce route boundaries', () => {
    const router = createRouter({ history: createMemoryHistory(), routes });
    it.each(['/student/cart', '/student/checkout', '/student/orders', '/student/orders/12'])('requires authentication for %s without adding an unsupported role rule', async (path) => {
        const route = router.resolve(path);
        expect(route.meta.requiresAuth).toBe(true);
        expect(route.meta.roles).toEqual([]);
        const auth = { initialize: vi.fn().mockResolvedValue(null), isAuthenticated: false, hasAnyRole: () => false };
        expect(await createAccessGuard(auth)(route)).toEqual({ name: 'login', query: { redirect: path } });
        auth.isAuthenticated = true;
        expect(await createAccessGuard(auth)(route)).toBe(true);
    });
    it('exposes the implemented cart and orders in student navigation', () => {
        expect(navigationByArea.student.map((item) => item.route)).toEqual(expect.arrayContaining(['student.cart', 'student.orders.index']));
    });
});

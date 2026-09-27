import { beforeEach, describe, expect, it, vi } from 'vitest';
import { fetchAuthenticatedUser, login, logout } from '../api/auth';
import { api } from '../api/client';

vi.mock('../api/client', () => ({ api: { get: vi.fn(), post: vi.fn() } }));

describe('Sanctum auth API', () => {
    beforeEach(() => {
        api.get.mockReset();
        api.post.mockReset();
    });

    it('initializes CSRF before login and then fetches the canonical user', async () => {
        const user = { id: 1, roles: ['student'], permissions: [] };
        api.get.mockResolvedValueOnce({ status: 204 }).mockResolvedValueOnce({ data: { data: user } });
        api.post.mockResolvedValue({ data: { data: user } });

        await expect(login({ email: 'student@example.com', password: 'secret' })).resolves.toEqual(user);
        expect(api.get.mock.invocationCallOrder[0]).toBeLessThan(api.post.mock.invocationCallOrder[0]);
        expect(api.get).toHaveBeenNthCalledWith(1, 'sanctum/csrf-cookie');
        expect(api.post).toHaveBeenCalledWith('api/v1/auth/login', expect.any(Object));
        expect(api.get).toHaveBeenNthCalledWith(2, 'api/v1/auth/user');
    });

    it('reads the wrapped current user response', async () => {
        api.get.mockResolvedValue({ data: { data: { id: 5 } } });
        await expect(fetchAuthenticatedUser()).resolves.toEqual({ id: 5 });
    });

    it('posts logout to the session endpoint', async () => {
        api.post.mockResolvedValue({ status: 204 });
        await logout();
        expect(api.post).toHaveBeenCalledWith('api/v1/auth/logout');
    });
});

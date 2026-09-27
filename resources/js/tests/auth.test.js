import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { ApiError } from '../api/errors';
import { resetAuthInitializationForTests, useAuthStore } from '../stores/auth';
import * as authApi from '../api/auth';

vi.mock('../api/auth', () => ({ fetchAuthenticatedUser: vi.fn(), login: vi.fn(), logout: vi.fn() }));

const user = { id: 1, name: 'Student', email: 'student@example.com', roles: ['student'], permissions: ['courses.view'], instructor_profile: null };

describe('auth store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        resetAuthInitializationForTests();
    });

    it('initializes once even when callers overlap', async () => {
        authApi.fetchAuthenticatedUser.mockResolvedValue(user);
        const auth = useAuthStore();
        const [first, second] = await Promise.all([auth.initialize(), auth.initialize()]);

        expect(first).toEqual(user);
        expect(second).toEqual(user);
        expect(authApi.fetchAuthenticatedUser).toHaveBeenCalledTimes(1);
        expect(auth.isAuthenticated).toBe(true);
    });

    it('treats an expired session as a guest', async () => {
        authApi.fetchAuthenticatedUser.mockRejectedValue(new ApiError({ status: 401, message: 'Unauthenticated.' }));
        const auth = useAuthStore();

        await expect(auth.initialize()).resolves.toBeNull();
        expect(auth.initialized).toBe(true);
        expect(auth.isAuthenticated).toBe(false);
    });

    it('stores the user after login and exposes permissions', async () => {
        authApi.login.mockResolvedValue(user);
        const auth = useAuthStore();

        await auth.signIn({ email: user.email, password: 'secret', remember: false });
        expect(auth.user).toEqual(user);
        expect(auth.can('courses.view')).toBe(true);
        expect(auth.hasRole('student')).toBe(true);
    });

    it('preserves validation errors from an invalid login', async () => {
        const error = new ApiError({ status: 422, message: 'Invalid.', errors: { email: ['Invalid credentials.'] } });
        authApi.login.mockRejectedValue(error);

        await expect(useAuthStore().signIn({ email: 'bad@example.com', password: 'bad' })).rejects.toBe(error);
    });

    it('clears local auth state even when logout request fails', async () => {
        authApi.logout.mockRejectedValue(new Error('offline'));
        const auth = useAuthStore();
        auth.setUser(user);

        await expect(auth.signOut()).rejects.toThrow('offline');
        expect(auth.isAuthenticated).toBe(false);
    });
});

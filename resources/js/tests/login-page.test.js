import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';
import LoginPage from '../pages/LoginPage.vue';
import { ApiError } from '../api/errors';
import * as authApi from '../api/auth';
import i18n, { setLocale } from '../i18n';

const routerMocks = vi.hoisted(() => ({ push: vi.fn(), route: { query: { redirect: '/courses/bim' } } }));
vi.mock('vue-router', () => ({ useRoute: () => routerMocks.route, useRouter: () => ({ push: routerMocks.push }) }));
vi.mock('../api/auth', () => ({ fetchAuthenticatedUser: vi.fn(), login: vi.fn(), logout: vi.fn() }));

function mountPage() {
    const pinia = createPinia();
    setActivePinia(pinia);
    return mount(LoginPage, { global: { plugins: [pinia, i18n] } });
}

describe('login page', () => {
    beforeEach(() => {
        setLocale('en');
        routerMocks.push.mockReset();
        authApi.login.mockReset();
    });

    it('shows client-side required validation before calling the API', async () => {
        const wrapper = mountPage();
        await wrapper.get('form').trigger('submit');
        expect(wrapper.text()).toContain('Email is required');
        expect(wrapper.text()).toContain('Password is required');
        expect(authApi.login).not.toHaveBeenCalled();
    });

    it('logs in and follows the intended local route', async () => {
        authApi.login.mockResolvedValue({ id: 1, name: 'Student', roles: ['student'], permissions: [] });
        const wrapper = mountPage();
        await wrapper.get('#email').setValue('student@example.com');
        await wrapper.get('#password').setValue('secret');
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(authApi.login).toHaveBeenCalledWith({ email: 'student@example.com', password: 'secret', remember: false });
        expect(routerMocks.push).toHaveBeenCalledWith('/courses/bim');
    });

    it('renders backend validation failures without redirecting', async () => {
        authApi.login.mockRejectedValue(new ApiError({ status: 422, code: 'validation', message: 'Invalid.', errors: { email: ['Invalid credentials.'] } }));
        const wrapper = mountPage();
        await wrapper.get('#email').setValue('student@example.com');
        await wrapper.get('#password').setValue('wrong');
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(wrapper.text()).toContain('Invalid credentials.');
        expect(wrapper.text()).toContain('Unable to sign in');
        expect(routerMocks.push).not.toHaveBeenCalled();
    });
});

import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import i18n, { setLocale } from '../i18n';
import RegisterPage from '../pages/RegisterPage.vue';
import ForgotPasswordPage from '../pages/ForgotPasswordPage.vue';
import ResetPasswordPage from '../pages/ResetPasswordPage.vue';
import PolicyPage from '../pages/PolicyPage.vue';
import AdminPolicyPagesPage from '../pages/AdminPolicyPagesPage.vue';
import * as auth from '../api/auth';
import * as policies from '../api/policy-pages';

const route = vi.hoisted(() => ({ query: { email: 'student@example.test' } }));
vi.mock('vue-router', async (original) => ({ ...(await original()), useRoute: () => route }));
vi.mock('../stores/auth', () => ({ useAuthStore: () => ({ can: (permission) => ['policy_pages.view', 'policy_pages.update', 'policy_pages.publish'].includes(permission) }) }));
vi.mock('../api/auth', () => ({ register: vi.fn(), requestPasswordReset: vi.fn(), resetPassword: vi.fn() }));
vi.mock('../api/policy-pages', () => ({ fetchPolicyPage: vi.fn(), fetchAdminPolicyPages: vi.fn(), savePolicyPage: vi.fn(), publishPolicyPage: vi.fn() }));

function render(component, props = {}) {
    return mount(component, { props, global: { plugins: [i18n], stubs: { RouterLink: { props: ['to'], template: '<a><slot /></a>' } } } });
}
function deferred() { let resolve; const promise = new Promise((done) => { resolve = done; }); return { promise, resolve }; }

beforeEach(() => { vi.resetAllMocks(); setLocale('en'); });

describe('public account lifecycle', () => {
    it('registers a learner with confirmation and prevents duplicate submits', async () => {
        const pending = deferred(); auth.register.mockReturnValue(pending.promise);
        const wrapper = render(RegisterPage);
        await wrapper.get('#register-name').setValue('Learner');
        await wrapper.get('#register-email').setValue('learner@example.test');
        await wrapper.get('#register-password').setValue('secure-password');
        await wrapper.get('#register-confirm').setValue('secure-password');
        await wrapper.get('form').trigger('submit'); await wrapper.get('form').trigger('submit');
        expect(auth.register).toHaveBeenCalledTimes(1);
        expect(auth.register.mock.calls[0][0]).toMatchObject({ email: 'learner@example.test', password_confirmation: 'secure-password' });
        pending.resolve({ id: 1 }); await flushPromises();
        expect(wrapper.text()).toContain('Your account is ready');
        wrapper.unmount();
    });

    it('shows validation, server errors and Arabic direction through the auth layout', async () => {
        setLocale('ar');
        auth.register.mockRejectedValue({ status: 422, errors: { email: ['البريد مستخدم'] } });
        const wrapper = render(RegisterPage);
        await wrapper.get('form').trigger('submit');
        expect(wrapper.text()).toContain('الاسم مطلوب');
        await wrapper.get('#register-name').setValue('طالب');
        await wrapper.get('#register-email').setValue('used@example.test');
        await wrapper.get('#register-password').setValue('secure-password');
        await wrapper.get('#register-confirm').setValue('secure-password');
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(wrapper.text()).toContain('البريد مستخدم');
        wrapper.unmount();
    });

    it('shows neutral forgot success and handles a network failure', async () => {
        auth.requestPasswordReset.mockRejectedValueOnce({ code: 'network' }).mockResolvedValueOnce({});
        const wrapper = render(ForgotPasswordPage);
        await wrapper.get('#forgot-email').setValue('student@example.test');
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(wrapper.text()).toContain('Unable to reach');
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(wrapper.text()).toContain('If the account exists');
        wrapper.unmount();
    });

    it('uses token and email for reset and explains an expired token', async () => {
        auth.resetPassword.mockRejectedValueOnce({ status: 422, errors: { token: ['Expired'] } }).mockResolvedValueOnce({});
        const wrapper = render(ResetPasswordPage, { token: 'opaque-token' });
        await wrapper.get('#reset-password').setValue('new-password');
        await wrapper.get('#reset-confirm').setValue('new-password');
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(auth.resetPassword.mock.calls[0][0]).toMatchObject({ token: 'opaque-token', email: 'student@example.test' });
        expect(wrapper.text()).toContain('invalid or expired');
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(wrapper.text()).toContain('Your password has changed');
        wrapper.unmount();
    });
});

describe('policy pages', () => {
    it('renders published content as text without executing HTML and responds to locale', async () => {
        policies.fetchPolicyPage.mockResolvedValue({ body: '<script>alert(1)</script>', version: 2 });
        const wrapper = render(PolicyPage, { slug: 'privacy' }); await flushPromises();
        expect(wrapper.text()).toContain('<script>alert(1)</script>');
        expect(wrapper.find('script').exists()).toBe(false);
        setLocale('ar'); await flushPromises();
        expect(policies.fetchPolicyPage).toHaveBeenLastCalledWith('privacy', 'ar');
        expect(wrapper.attributes('dir')).toBe('rtl');
        wrapper.unmount();
    });

    it('shows unpublished state and keeps admin draft separate from publication', async () => {
        policies.fetchPolicyPage.mockRejectedValue({ status: 404 });
        const publicPage = render(PolicyPage, { slug: 'refund' }); await flushPromises();
        expect(publicPage.text()).toContain('not been published'); publicPage.unmount();

        policies.fetchAdminPolicyPages.mockResolvedValue([{ slug: 'privacy', draft_ar: '', draft_en: '', published_at: null, version: 0 }]);
        policies.savePolicyPage.mockResolvedValue({ slug: 'privacy', draft_ar: 'مسودة', draft_en: 'Draft', published_at: null, version: 0 });
        const adminPage = render(AdminPolicyPagesPage); await flushPromises();
        await adminPage.get('#policy-ar').setValue('مسودة'); await adminPage.get('#policy-en').setValue('Draft');
        await adminPage.get('form').trigger('submit'); await flushPromises();
        expect(policies.savePolicyPage).toHaveBeenCalledWith('privacy', { draft_ar: 'مسودة', draft_en: 'Draft' });
        expect(policies.publishPolicyPage).not.toHaveBeenCalled();
        adminPage.unmount();
    });
});

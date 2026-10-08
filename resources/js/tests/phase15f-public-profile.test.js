import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';
import i18n, { setLocale } from '../i18n';
import { useAuthStore } from '../stores/auth';
import * as site from '../api/public-site';
import * as profile from '../api/profile';
import SitePage from '../pages/SitePage.vue';
import InstructorProfilePage from '../pages/InstructorProfilePage.vue';
import ProfilePage from '../pages/ProfilePage.vue';
import DashboardPage from '../pages/DashboardPage.vue';
import { countryOptions } from '../utils/countries';

vi.mock('../api/public-site', () => ({ fetchSitePage: vi.fn(), fetchSiteFaqs: vi.fn(), submitContact: vi.fn(), fetchInstructor: vi.fn() }));
vi.mock('../api/profile', () => ({ fetchProfile: vi.fn(), updateProfile: vi.fn(), uploadAvatar: vi.fn(), removeAvatar: vi.fn(), changePassword: vi.fn() }));

function render(component, props) {
    return mount(component, { props, global: { plugins: [createPinia(), i18n], stubs: { RouterLink: { template: '<a><slot /></a>' }, CourseCard: true } } });
}

describe('Phase 15F public and profile screens', () => {
    beforeEach(() => {
        vi.resetAllMocks();
        setActivePinia(createPinia());
        setLocale('en');
        site.fetchSitePage.mockResolvedValue(null);
        site.fetchSiteFaqs.mockResolvedValue([]);
        profile.fetchProfile.mockResolvedValue({ id: 1, name: 'Student', email: 'student@example.test', preferred_locale: 'en' });
    });

    it('renders unpublished editorial state without inserting unsafe HTML', async () => {
        const wrapper = render(SitePage, { slug: 'about' });
        await flushPromises();
        expect(wrapper.text()).toContain('not been published');
        site.fetchSitePage.mockResolvedValue({ body: '<script>alert(1)</script>' });
        const published = render(SitePage, { slug: 'about' });
        await flushPromises();
        expect(published.find('script').exists()).toBe(false);
        expect(published.text()).toContain('<script>');
    });

    it('sends validated contact fields and displays success', async () => {
        site.submitContact.mockResolvedValue({ status: 'received' });
        const wrapper = render(SitePage, { slug: 'contact' });
        await flushPromises();
        await wrapper.get('#contact-name').setValue('Visitor');
        await wrapper.get('#contact-email').setValue('visitor@example.test');
        await wrapper.get('#contact-subject').setValue('Question');
        await wrapper.get('#contact-message').setValue('A longer question');
        await wrapper.get('form').trigger('submit');
        await flushPromises();
        expect(site.submitContact).toHaveBeenCalledWith(expect.objectContaining({ email: 'visitor@example.test' }));
        expect(wrapper.text()).toContain('received');
    });

    it('shows published instructor courses without private fields', async () => {
        site.fetchInstructor.mockResolvedValue({ id: 2, name: 'Trainer', profile: { job_title: 'Engineer', bio: 'Public bio' }, courses: [{ id: 3, title: 'Public course' }] });
        const wrapper = render(InstructorProfilePage, { id: '2' });
        await flushPromises();
        expect(wrapper.text()).toContain('Trainer');
        expect(wrapper.text()).toContain('Engineer');
        expect(wrapper.text()).not.toContain('private@example.test');
    });

    it('updates allowlisted profile fields and changes password', async () => {
        const auth = useAuthStore();
        auth.setUser({ id: 1, name: 'Student', email: 'student@example.test', preferred_locale: 'en' });
        profile.updateProfile.mockResolvedValue({ id: 1, name: 'New Name', email: 'student@example.test', preferred_locale: 'en' });
        profile.changePassword.mockResolvedValue({ status: 'password_changed' });
        const wrapper = render(ProfilePage);
        await flushPromises();
        await wrapper.get('#profile-name').setValue('New Name');
        await wrapper.findAll('form')[0].trigger('submit');
        await flushPromises();
        expect(profile.updateProfile).toHaveBeenCalledWith(expect.objectContaining({ name: 'New Name' }));
        await wrapper.get('#profile-current_password').setValue('OldPassword123!');
        await wrapper.get('#profile-password').setValue('NewPassword123!');
        await wrapper.get('#profile-password_confirmation').setValue('NewPassword123!');
        await wrapper.findAll('form')[1].trigger('submit');
        await flushPromises();
        expect(profile.changePassword).toHaveBeenCalledOnce();
    });

    it('offers localized countries while keeping existing free-text values and city editable', async () => {
        profile.fetchProfile.mockResolvedValue({ id: 1, name: 'Student', email: 'student@example.test', country: 'Jordan', city: 'Amman', preferred_locale: 'en' });
        profile.updateProfile.mockResolvedValue({ id: 1, name: 'Student', email: 'student@example.test', country: 'JO', city: 'Nablus', preferred_locale: 'en' });
        const wrapper = render(ProfilePage);
        await flushPromises();

        expect(wrapper.get('#profile-country').element.tagName).toBe('SELECT');
        expect(wrapper.get('#profile-country').element.value).toBe('Jordan');
        expect(countryOptions('ar').find((country) => country.value === 'JO')?.label).toBe('الأردن');
        await wrapper.get('#profile-country').setValue('JO');
        await wrapper.get('#profile-city').setValue('Nablus');
        await wrapper.findAll('form')[0].trigger('submit');
        await flushPromises();

        expect(profile.updateProfile).toHaveBeenCalledWith(expect.objectContaining({ country: 'JO', city: 'Nablus' }));
    });

    it('opens the same file chooser from the avatar or initial and uploads the selected file', async () => {
        const wrapper = render(ProfilePage);
        await flushPromises();
        const input = wrapper.get('#profile-avatar');
        expect(wrapper.get('label.group').element.control).toBe(input.element);
        const file = new File(['image contents'], 'portrait.png', { type: 'image/png' });
        Object.defineProperty(input.element, 'files', { configurable: true, value: [file] });
        profile.uploadAvatar.mockResolvedValue({ id: 1, name: 'Student', email: 'student@example.test', avatar: '/storage/avatars/generated.png' });

        await input.trigger('change');
        await flushPromises();

        expect(profile.uploadAvatar).toHaveBeenCalledWith(file);
        expect(wrapper.get('label.group img').attributes('src')).toContain('generated.png');
    });

    it('does not expose effective permission badges on the student dashboard', () => {
        useAuthStore().setUser({ id: 1, name: 'Student', roles: ['student'], permissions: ['courses.view'] });
        const wrapper = render(DashboardPage);

        expect(wrapper.text()).not.toContain('Effective permissions');
        expect(wrapper.text()).not.toContain('courses.view');
    });
});

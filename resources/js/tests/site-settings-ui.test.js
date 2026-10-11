import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import AdminSiteContentPage from '../pages/AdminSiteContentPage.vue';
import * as siteApi from '../api/public-site';
import i18n, { setLocale } from '../i18n';

vi.mock('../api/public-site', () => ({
    fetchAdminSiteContent: vi.fn(), fetchContactMessages: vi.fn(), saveSiteSettings: vi.fn(),
    saveSitePage: vi.fn(), publishSitePage: vi.fn(), saveSiteFaq: vi.fn(), deleteSiteFaq: vi.fn(),
}));

describe('admin homepage settings', () => {
    beforeEach(() => {
        vi.resetAllMocks();
        setLocale('ar');
        siteApi.fetchAdminSiteContent.mockResolvedValue({ pages: [{ slug: 'about', draft_ar: '', draft_en: '' }], faqs: [], settings: { home_autoplay_enabled: true } });
        siteApi.fetchContactMessages.mockResolvedValue({ data: [] });
        siteApi.saveSiteSettings.mockResolvedValue({ home_autoplay_enabled: false });
    });

    it('loads the saved value and submits the autoplay toggle', async () => {
        const wrapper = mount(AdminSiteContentPage, { global: { plugins: [i18n] } });
        await flushPromises();
        const checkbox = wrapper.get('input[type="checkbox"]');
        expect(checkbox.element.checked).toBe(true);
        await checkbox.setValue(false);
        await wrapper.findAll('form')[0].trigger('submit');
        await flushPromises();
        expect(siteApi.saveSiteSettings).toHaveBeenCalledWith({ home_autoplay_enabled: false });
        expect(wrapper.text()).toContain('إعدادات الصفحة الرئيسية');
        wrapper.unmount();
    });
});

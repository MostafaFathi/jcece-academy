import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import LanguageSwitcher from '../components/navigation/LanguageSwitcher.vue';
import i18n, { setLocale } from '../i18n';
import { routes } from '../router';

describe('public routing and localization', () => {
    it('registers all Phase 11 public routes', () => {
        const publicRouteNames = routes[0].children.map((route) => route.name);
        expect(publicRouteNames).toEqual(['home', 'courses.index', 'courses.show', 'packages.index', 'packages.show', 'certificates.verify']);
    });

    it('switches the mounted interface direction', async () => {
        setLocale('ar');
        const wrapper = mount(LanguageSwitcher, { global: { plugins: [i18n] } });
        await wrapper.get('button').trigger('click');
        expect(document.documentElement.lang).toBe('en');
        expect(document.documentElement.dir).toBe('ltr');
        expect(wrapper.text()).toBe('العربية');
    });
});

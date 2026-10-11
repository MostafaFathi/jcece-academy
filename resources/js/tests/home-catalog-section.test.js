import { afterEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import HomeCatalogSection from '../components/public/HomeCatalogSection.vue';
import i18n, { setLocale } from '../i18n';

const render = (props = {}) => mount(HomeCatalogSection, { props: { title: 'Courses', itemCount: 2, ...props }, slots: { default: '<article>Published course</article>' }, global: { plugins: [i18n], stubs: { RouterLink: { template: '<a><slot /></a>' } } } });
afterEach(() => vi.unstubAllGlobals());

describe('homepage catalog navigation', () => {
    it.each([['en', 600, -600], ['ar', -600, 600]])('moves forward and backward in the %s reading direction', async (locale, next, previous) => {
        setLocale(locale);
        const wrapper = render();
        const rail = wrapper.get('[role="region"]').element;
        Object.defineProperty(rail, 'clientWidth', { value: 600 });
        rail.scrollBy = vi.fn();

        await wrapper.findAll('button')[1].trigger('click');
        expect(rail.scrollBy).toHaveBeenLastCalledWith({ left: next, behavior: 'smooth' });
        await wrapper.findAll('button')[0].trigger('click');
        expect(rail.scrollBy).toHaveBeenLastCalledWith({ left: previous, behavior: 'smooth' });
        wrapper.unmount();
    });

    it('respects reduced motion when navigating', async () => {
        setLocale('en');
        vi.stubGlobal('matchMedia', vi.fn().mockReturnValue({ matches: true }));
        const wrapper = render();
        const rail = wrapper.get('[role="region"]').element;
        Object.defineProperty(rail, 'clientWidth', { value: 600 });
        rail.scrollBy = vi.fn();

        await wrapper.findAll('button')[1].trigger('click');

        expect(rail.scrollBy).toHaveBeenCalledWith({ left: 600, behavior: 'auto' });
        wrapper.unmount();
    });

    it('offers a retry for a failed section without showing stale cards', async () => {
        const wrapper = render({ error: true });

        await wrapper.get('button').trigger('click');

        expect(wrapper.emitted('retry')).toHaveLength(1);
        expect(wrapper.text()).not.toContain('Published course');
        expect(wrapper.find('[role="region"]').exists()).toBe(false);
        wrapper.unmount();
    });
});

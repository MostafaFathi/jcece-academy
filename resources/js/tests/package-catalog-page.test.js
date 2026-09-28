import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import PackageCatalogPage from '../pages/PackageCatalogPage.vue';
import i18n, { setLocale } from '../i18n';
import { fetchPackages } from '../api/packages';

const routerMocks = vi.hoisted(() => ({ route: { query: {} }, push: vi.fn() }));
vi.mock('vue-router', () => ({ useRoute: () => routerMocks.route, useRouter: () => ({ push: routerMocks.push }) }));
vi.mock('../api/packages', () => ({ fetchPackages: vi.fn() }));

const RouterLinkStub = { props: ['to'], template: '<a><slot /></a>' };
const packageItem = { id: 1, title: 'BIM Career Path', slug: 'bim-path', description: 'A focused learning path', type: 'learning_path', price: '120.00', course_count: 3 };

function mountPage() {
    return mount(PackageCatalogPage, { global: { plugins: [i18n], stubs: { RouterLink: RouterLinkStub } } });
}

describe('package catalog page', () => {
    beforeEach(() => {
        setLocale('en');
        routerMocks.route.query = { search: 'BIM', type: 'learning_path', sort: 'price_desc', page: '2' };
        routerMocks.push.mockReset();
        fetchPackages.mockResolvedValue({ items: [packageItem], meta: { current_page: 2, last_page: 4, from: 13, to: 13, total: 37 } });
    });

    it('loads supported URL filters and renders the paginated API response', async () => {
        const wrapper = mountPage();
        await flushPromises();

        expect(fetchPackages).toHaveBeenCalledWith({ search: 'BIM', type: 'learning_path', sort: 'price_desc', page: 2, per_page: 12 });
        expect(wrapper.text()).toContain('BIM Career Path');
        expect(wrapper.text()).toContain('Showing 13–13 of 37 results');
    });

    it('writes supported filters back to a shareable route query', async () => {
        const wrapper = mountPage();
        await flushPromises();
        await wrapper.get('input[type="search"]').setValue('Management');
        const selects = wrapper.findAll('select');
        await selects[0].setValue('package');
        await selects[1].setValue('title');
        await wrapper.get('form').trigger('submit');

        expect(routerMocks.push).toHaveBeenCalledWith({ name: 'packages.index', query: { search: 'Management', type: 'package', sort: 'title' } });
    });

    it('renders package empty and retryable error states', async () => {
        fetchPackages.mockResolvedValueOnce({ items: [], meta: { current_page: 1, last_page: 1, total: 0 } });
        const emptyWrapper = mountPage();
        await flushPromises();
        expect(emptyWrapper.text()).toContain('No matching packages found');
        emptyWrapper.unmount();

        fetchPackages.mockRejectedValueOnce(new Error('offline'));
        const errorWrapper = mountPage();
        await flushPromises();
        expect(errorWrapper.text()).toContain('Content could not be loaded right now');
        expect(errorWrapper.text()).toContain('Try again');
    });
});

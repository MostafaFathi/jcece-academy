import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createPinia } from 'pinia';
import CourseCatalogPage from '../pages/CourseCatalogPage.vue';
import i18n, { setLocale } from '../i18n';
import { fetchCategories } from '../api/categories';
import { fetchCourses } from '../api/courses';

const routerMocks = vi.hoisted(() => ({ route: { query: {} }, push: vi.fn() }));
vi.mock('vue-router', () => ({ useRoute: () => routerMocks.route, useRouter: () => ({ push: routerMocks.push }) }));
vi.mock('../api/categories', () => ({ fetchCategories: vi.fn() }));
vi.mock('../api/courses', () => ({ fetchCourses: vi.fn() }));
vi.mock('../api/public-site', () => ({ fetchInstructors: vi.fn().mockResolvedValue({ items: [], meta: { last_page: 1 } }) }));

const RouterLinkStub = { template: '<a><slot /></a>' };
const course = { id: 1, title: 'BIM Essentials', slug: 'bim', short_description: 'Course description', price: '20.00', level: 'beginner', rating_summary: { average_rating: 5, review_count: 1 } };

function mountPage() {
    return mount(CourseCatalogPage, { global: { plugins: [i18n, createPinia()], stubs: { RouterLink: RouterLinkStub } } });
}

describe('course catalog page', () => {
    beforeEach(() => {
        setLocale('en');
        routerMocks.route.query = { search: 'BIM', category: 'engineering', level: 'beginner', sort: 'price_asc', page: '2' };
        routerMocks.push.mockReset();
        fetchCategories.mockResolvedValue([{ id: 1, name: 'Engineering', slug: 'engineering', children: [] }]);
        fetchCourses.mockResolvedValue({ items: [course], meta: { current_page: 2, last_page: 3, from: 13, to: 13, total: 25 } });
    });

    it('loads supported URL filters and renders pagination metadata', async () => {
        const wrapper = mountPage();
        await flushPromises();
        expect(fetchCourses).toHaveBeenCalledWith({ search: 'BIM', category: 'engineering', level: 'beginner', sort: 'price_asc', page: 2, per_page: 12 });
        expect(wrapper.text()).toContain('BIM Essentials');
        expect(wrapper.text()).toContain('Showing 13–13 of 25 results');
    });

    it('writes supported filters back to a shareable route query', async () => {
        const wrapper = mountPage();
        await flushPromises();
        await wrapper.get('input[type="search"]').setValue('Project');
        const selects = wrapper.findAll('select');
        await selects[0].setValue('engineering');
        await selects[2].setValue('advanced');
        await selects[7].setValue('title');
        await wrapper.get('form').trigger('submit');
        expect(routerMocks.push).toHaveBeenCalledWith({ name: 'courses.index', query: { search: 'Project', category: 'engineering', level: 'advanced', sort: 'title' } });
    });

    it('shows the empty state for a successful empty response', async () => {
        fetchCourses.mockResolvedValue({ items: [], meta: { current_page: 1, last_page: 1, total: 0 } });
        const wrapper = mountPage();
        await flushPromises();
        expect(wrapper.text()).toContain('No matching courses found');
    });

    it('shows a retryable error state', async () => {
        fetchCourses.mockRejectedValue(new Error('offline'));
        const wrapper = mountPage();
        await flushPromises();
        expect(wrapper.text()).toContain('Content could not be loaded right now');
        expect(wrapper.text()).toContain('Try again');
    });
});

import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import HomePage from '../pages/HomePage.vue';
import i18n, { setLocale } from '../i18n';
import { fetchCategories } from '../api/categories';
import { fetchCourses } from '../api/courses';
import { fetchPackages } from '../api/packages';

vi.mock('vue-router', () => ({ useRoute: () => ({ query: {} }) }));
vi.mock('../api/categories', () => ({ fetchCategories: vi.fn() }));
vi.mock('../api/courses', () => ({ fetchCourses: vi.fn() }));
vi.mock('../api/packages', () => ({ fetchPackages: vi.fn() }));

const RouterLinkStub = { props: ['to'], template: '<a><slot /></a>' };

function mountPage() {
    return mount(HomePage, { global: { plugins: [i18n], stubs: { RouterLink: RouterLinkStub } } });
}

describe('homepage', () => {
    beforeEach(() => {
        setLocale('ar');
        fetchCategories.mockResolvedValue([{ id: 1, name: 'الهندسة', slug: 'engineering', children: [] }]);
        fetchCourses.mockResolvedValue({ items: [{ id: 1, title: 'دورة BIM', slug: 'bim', short_description: 'وصف حقيقي', price: '50.00', level: 'beginner', rating_summary: { average_rating: 4.5, review_count: 2 } }], meta: {} });
        fetchPackages.mockResolvedValue({ items: [{ id: 1, title: 'مسار BIM', slug: 'bim-path', type: 'learning_path', price: '100.00', course_count: 1 }], meta: {} });
    });

    it('renders mapped real API content', async () => {
        const wrapper = mountPage();
        await flushPromises();
        expect(wrapper.text()).toContain('دورة BIM');
        expect(wrapper.text()).toContain('الهندسة');
        expect(wrapper.text()).toContain('مسار BIM');
        expect(fetchCourses).toHaveBeenCalledWith({ sort: 'latest', per_page: 6 });
    });

    it('keeps successful sections when one request fails', async () => {
        fetchCourses.mockRejectedValue(new Error('offline'));
        const wrapper = mountPage();
        await flushPromises();
        expect(wrapper.text()).toContain('الهندسة');
        expect(wrapper.text()).toContain('مسار BIM');
        expect(wrapper.text()).toContain('تعذر تحميل المحتوى الآن');
    });
});

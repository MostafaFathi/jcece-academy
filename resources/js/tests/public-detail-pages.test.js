import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { flushPromises, mount } from '@vue/test-utils';
import CourseDetailPage from '../pages/CourseDetailPage.vue';
import PackageDetailPage from '../pages/PackageDetailPage.vue';
import i18n, { setLocale } from '../i18n';
import { fetchCourse } from '../api/courses';
import { fetchPackage } from '../api/packages';
import { fetchCourseReviews } from '../api/reviews';

vi.mock('vue-router', () => ({ useRoute: () => ({ fullPath: '/courses/bim' }), useRouter: () => ({ push: vi.fn() }) }));
vi.mock('../api/courses', () => ({ fetchCourse: vi.fn() }));
vi.mock('../api/packages', () => ({ fetchPackage: vi.fn() }));
vi.mock('../api/reviews', () => ({ fetchCourseReviews: vi.fn() }));

const RouterLinkStub = { template: '<a><slot /></a>' };
const global = () => ({ plugins: [i18n, createPinia()], stubs: { RouterLink: RouterLinkStub } });

describe('public detail pages', () => {
    beforeEach(() => {
        setLocale('en');
        setActivePinia(createPinia());
    });

    it('renders public course curriculum and published reviews without exposing file paths', async () => {
        fetchCourse.mockResolvedValue({
            id: 1, slug: 'bim', title: 'BIM Essentials', short_description: 'Learn BIM', description: 'Safe description', level: 'beginner', language: 'en', price: '20.00', access_duration_days: 90, certificate_enabled: true,
            rating_summary: { average_rating: 5, review_count: 1 },
            curriculum: [{ id: 1, title: 'Introduction', lessons: [{ id: 1, title: 'Welcome lesson', type: 'video', duration_seconds: 300, is_preview: true, sort_order: 0, file_path: 'private/lesson.pdf' }] }],
        });
        fetchCourseReviews.mockResolvedValue({ items: [{ rating: 5, title: 'Excellent', body: 'Published feedback', reviewer_name: 'Learner', published_at: '2026-09-20T00:00:00Z' }], meta: { current_page: 1, last_page: 1 } });
        const wrapper = mount(CourseDetailPage, { props: { slug: 'bim' }, global: global() });
        await flushPromises();
        expect(wrapper.text()).toContain('Welcome lesson');
        expect(wrapper.text()).toContain('Published feedback');
        expect(wrapper.html()).not.toContain('private/lesson.pdf');
    });

    it('uses the backend aggregate when a pending review is absent from public results', async () => {
        fetchCourse.mockResolvedValue({ id: 1, slug: 'bim', title: 'BIM Essentials', price: '20.00', rating_summary: { average_rating: null, review_count: 0 }, curriculum: [] });
        fetchCourseReviews.mockResolvedValue({ items: [], meta: { current_page: 1, last_page: 1 } });
        const wrapper = mount(CourseDetailPage, { props: { slug: 'bim' }, global: global() });
        await flushPromises();
        expect(wrapper.text()).toContain('No published reviews yet.');
        expect(wrapper.text()).toContain('0 published reviews');
        expect(wrapper.text()).not.toContain('Pending approval');
        expect(fetchCourseReviews).toHaveBeenCalledWith('bim', { page: 1, per_page: 6 });
        wrapper.unmount();
    });

    it('renders package details and included published courses', async () => {
        fetchPackage.mockResolvedValue({ id: 1, slug: 'career-path', title: 'Career Path', description: 'A focused path', type: 'learning_path', price: '100.00', is_lifetime: false, access_duration_days: 180, is_sequential: true, course_count: 1, courses: [{ id: 10, is_required: true, course: { id: 2, title: 'Core Course', slug: 'core', level: 'beginner', price: '50.00' } }] });
        const wrapper = mount(PackageDetailPage, { props: { slug: 'career-path' }, global: global() });
        await flushPromises();
        expect(wrapper.text()).toContain('Career Path');
        expect(wrapper.text()).toContain('Core Course');
        expect(wrapper.text()).toContain('Package access for 180 days');
        expect(wrapper.find('article .relative').classes()).toContain('w-full');
    });

    it('renders a not-found state for a missing package', async () => {
        fetchPackage.mockRejectedValue({ status: 404 });
        const wrapper = mount(PackageDetailPage, { props: { slug: 'missing' }, global: global() });
        await flushPromises();
        expect(wrapper.text()).toContain('The requested package was not found');
    });
});

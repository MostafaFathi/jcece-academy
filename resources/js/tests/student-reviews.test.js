import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import i18n, { setLocale } from '../i18n';
import * as reviews from '../api/reviews';
import CourseReviewPanel from '../components/reviews/CourseReviewPanel.vue';
import CourseReviewForm from '../components/reviews/CourseReviewForm.vue';

vi.mock('../api/reviews', () => ({ fetchMyReview: vi.fn(), createReview: vi.fn(), updateReview: vi.fn(), fetchCourseReviews: vi.fn() }));
const existing = (extra = {}) => ({ id: 6, rating: 4, title: 'Useful', body: 'Practical', status: 'published', moderation_reason: null, ...extra });
const render = (props = {}) => mount(CourseReviewPanel, { props: { slug: 'safety', hasAccess: true, ...props }, global: { plugins: [i18n] } });
const button = (wrapper, text) => wrapper.findAll('button').find((item) => item.text().includes(text));
function deferred() { let resolve; const promise = new Promise((yes) => { resolve = yes; }); return { promise, resolve }; }
beforeEach(() => { vi.resetAllMocks(); setLocale('en'); reviews.fetchMyReview.mockRejectedValue({ status: 404 }); reviews.createReview.mockResolvedValue(existing({ status: 'pending' })); reviews.updateReview.mockResolvedValue(existing({ status: 'pending' })); });

describe('course review form and access', () => {
    it('creates an eligible review with keyboard-operable radios and shows pending state', async () => {
        const wrapper = render(); await flushPromises(); await button(wrapper, 'Write a review').trigger('click');
        expect(wrapper.findAll('input[type="radio"]')).toHaveLength(5);
        await wrapper.find('input[value="5"]').setValue(); await wrapper.get('#review-title').setValue('Great'); await wrapper.get('#review-body').setValue('Helpful');
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(reviews.createReview).toHaveBeenCalledWith('safety', { rating: 5, title: 'Great', body: 'Helpful' });
        expect(wrapper.text()).toContain('Pending approval'); expect(wrapper.text()).toContain('not public yet'); wrapper.unmount();
    });
    it('validates rating and guards duplicate submissions', async () => {
        const pending = deferred(); reviews.createReview.mockReturnValue(pending.promise);
        const wrapper = render(); await flushPromises(); await button(wrapper, 'Write a review').trigger('click');
        await wrapper.get('form').trigger('submit'); expect(wrapper.text()).toContain('Choose a rating'); expect(reviews.createReview).not.toHaveBeenCalled();
        await wrapper.find('input[value="3"]').setValue(); await wrapper.get('form').trigger('submit'); await wrapper.get('form').trigger('submit');
        expect(reviews.createReview).toHaveBeenCalledTimes(1); expect(button(wrapper, 'Submit review').attributes('disabled')).toBeDefined();
        pending.resolve(existing({ status: 'pending' })); await flushPromises(); wrapper.unmount();
    });
    it('shows published review, warns about removal, and synchronizes pending after edit', async () => {
        reviews.fetchMyReview.mockResolvedValue(existing());
        const wrapper = render(); await flushPromises(); expect(wrapper.text()).toContain('Useful'); expect(wrapper.text()).toContain('Published');
        await button(wrapper, 'Edit review').trigger('click'); expect(wrapper.text()).toContain('removes it from public reviews and ratings');
        expect(wrapper.get('#review-title').element.value).toBe('Useful'); await wrapper.find('input[value="5"]').setValue();
        await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(reviews.updateReview).toHaveBeenCalledWith(6, { rating: 5, title: 'Useful', body: 'Practical' });
        expect(wrapper.text()).toContain('Pending approval'); expect(wrapper.text()).not.toContain('Published'); wrapper.unmount();
    });
    it.each(['rejected', 'hidden'])('shows only student-safe moderation fields for %s', async (status) => {
        reviews.fetchMyReview.mockResolvedValue(existing({ status, moderation_reason: 'Please revise' }));
        const wrapper = render(); await flushPromises();
        expect(wrapper.text()).toContain('Please revise'); expect(wrapper.text()).not.toContain('audit'); expect(wrapper.text()).not.toContain('moderator'); wrapper.unmount();
    });
    it.each(['expired', 'suspended'])('keeps historical published review visible but disables editing for %s access', async () => {
        reviews.fetchMyReview.mockResolvedValue(existing());
        const wrapper = render({ hasAccess: false }); await flushPromises();
        expect(wrapper.text()).toContain('Useful'); expect(wrapper.text()).toContain('Published'); expect(wrapper.text()).toContain('requires active course access');
        expect(button(wrapper, 'Edit review')).toBeUndefined(); wrapper.unmount();
    });
    it('handles backend denial even if the displayed access state was stale', async () => {
        reviews.createReview.mockRejectedValue({ status: 403 });
        const wrapper = render(); await flushPromises(); await button(wrapper, 'Write a review').trigger('click'); await wrapper.find('input[value="4"]').setValue(); await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(wrapper.text()).toContain('An active course enrollment'); expect(button(wrapper, 'Write a review')).toBeUndefined(); wrapper.unmount();
    });
    it('keeps the radio selector meaningful in Arabic RTL and English LTR', async () => {
        const wrapper = mount(CourseReviewForm, { global: { plugins: [i18n] } });
        expect(wrapper.get('input[value="4"]').attributes('aria-label')).toContain('4 out of 5');
        setLocale('ar'); await flushPromises(); expect(wrapper.get('input[value="4"]').attributes('aria-label')).toContain('4 من 5');
        expect(wrapper.findAll('input[type="radio"]')).toHaveLength(5); wrapper.unmount();
    });
});

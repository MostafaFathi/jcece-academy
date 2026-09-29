import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import i18n, { setLocale } from '../i18n';
import * as api from '../api/learning';
import MyCoursesPage from '../pages/MyCoursesPage.vue';
import CourseLearningPage from '../pages/CourseLearningPage.vue';
import LearningCurriculum from '../components/learning/LearningCurriculum.vue';
import LessonResources from '../components/learning/LessonResources.vue';
import VideoLesson from '../components/learning/VideoLesson.vue';
import TextLesson from '../components/learning/TextLesson.vue';
import LinkLesson from '../components/learning/LinkLesson.vue';
import ProgressSummary from '../components/learning/ProgressSummary.vue';
import { safeLearningUrl } from '../utils/learning';

const route = vi.hoisted(() => ({ query: {} }));
vi.mock('vue-router', () => ({ useRoute: () => route }));
vi.mock('../api/learning', () => ({ fetchMyCourses: vi.fn(), fetchLearningCourse: vi.fn(), fetchCourseProgress: vi.fn(), completeLesson: vi.fn(), saveLessonProgress: vi.fn(), downloadLessonResource: vi.fn() }));
const enrollment = (extra = {}) => ({ id: 1, status: 'active', has_access: true, access_state: 'active', access_expires_at: null, is_lifetime: false, completed_lessons: 1, total_lessons: 4, progress_percentage: 25, course: { slug: 'bim', title: 'BIM Course', thumbnail: null, instructor: { name: 'Trainer' } }, resume: { lesson: { id: 2, title: 'Video lesson' } }, ...extra });
const lesson = (id, type) => ({ id, title: `${type} lesson`, type, content: 'Protected reading', description: 'Learning notes', video_url: 'https://example.test/video.mp4', video_id: 'do-not-build-url', duration_seconds: 300, resources: [], progress: null });
const details = () => ({ ...enrollment(), course: enrollment().course, curriculum: [{ id: 1, title: 'First section', lessons: [lesson(1, 'text'), lesson(2, 'video'), lesson(3, 'file'), lesson(4, 'link')] }] });
const progress = (extra = {}, records = []) => ({ enrollment: enrollment(extra), lesson_progress: records });
function render(component, props = {}) { return mount(component, { props, global: { plugins: [i18n], stubs: { RouterLink: { name: 'RouterLink', props: ['to'], template: '<a><slot /></a>' } } } }); }
function deferred() { let resolve, reject; const promise = new Promise((yes, no) => { resolve = yes; reject = no; }); return { promise, resolve, reject }; }
async function player(data = details()) { api.fetchLearningCourse.mockResolvedValue(data); const wrapper = render(CourseLearningPage, { slug: 'bim' }); await flushPromises(); return wrapper; }
function button(wrapper, text) { return wrapper.findAll('button').find((item) => item.text() === text); }

beforeEach(() => {
    vi.resetAllMocks(); setLocale('en'); route.query = {};
    vi.spyOn(HTMLMediaElement.prototype, 'pause').mockImplementation(() => {});
    api.fetchMyCourses.mockResolvedValue({ items: [], meta: null });
    api.fetchLearningCourse.mockImplementation(async () => details());
    api.fetchCourseProgress.mockImplementation(async () => progress());
});

describe('My Courses', () => {
    it('shows loading, then empty state', async () => {
        const pending = deferred(); api.fetchMyCourses.mockReturnValue(pending.promise);
        const wrapper = render(MyCoursesPage); await nextTick();
        expect(wrapper.text()).toContain('Loading');
        pending.resolve({ items: [], meta: null }); await flushPromises();
        expect(wrapper.text()).toContain('No courses yet');
    });
    it.each([
        ['active', true, 'Active access'], ['expired', false, 'Access expired'], ['suspended', false, 'Enrollment suspended'], ['scheduled', false, 'Access not started'], ['revoked', false, 'Access revoked'], ['unavailable', false, 'No current access'],
    ])('renders authoritative %s access and gates continuation', async (state, hasAccess, label) => {
        api.fetchMyCourses.mockResolvedValue({ items: [enrollment({ access_state: state, has_access: hasAccess })] });
        const wrapper = render(MyCoursesPage); await flushPromises();
        expect(wrapper.text()).toContain(label); expect(wrapper.text().includes('Continue learning')).toBe(hasAccess);
        expect(wrapper.text()).toContain('Trainer');
        if (hasAccess) expect(wrapper.findAllComponents({ name: 'RouterLink' }).length || wrapper.findAll('a').length).toBeGreaterThan(0);
    });
    it('shows lifetime access and server expiration without catalog calculations', async () => {
        api.fetchMyCourses.mockResolvedValue({ items: [enrollment({ is_lifetime: true }), enrollment({ id: 2, access_expires_at: '2026-10-03T12:00:00Z' })] });
        const wrapper = render(MyCoursesPage); await flushPromises();
        expect(wrapper.text()).toContain('Lifetime access'); expect(wrapper.text()).toContain('Oct 3, 2026');
    });
    it('continues at the server resume lesson and reloads on re-entry', async () => {
        api.fetchMyCourses.mockResolvedValue({ items: [enrollment()] });
        let wrapper = render(MyCoursesPage); await flushPromises();
        expect(wrapper.getComponent({ name: 'RouterLink' }).props('to')).toEqual({ name: 'student.courses.learn', params: { slug: 'bim' }, query: { lesson: 2 } });
        wrapper.unmount();
        api.fetchMyCourses.mockResolvedValue({ items: [enrollment({ progress_percentage: 50 })] });
        wrapper = render(MyCoursesPage); await flushPromises(); expect(wrapper.text()).toContain('50%');
        expect(api.fetchMyCourses).toHaveBeenCalledTimes(2);
    });
    it('retries errors and uses server pagination', async () => {
        api.fetchMyCourses.mockRejectedValueOnce({ status: 500 });
        const wrapper = render(MyCoursesPage); await flushPromises();
        expect(wrapper.text()).toContain('Unable to load or save');
        api.fetchMyCourses.mockResolvedValue({ items: [enrollment()], meta: { current_page: 1, last_page: 2 } });
        await button(wrapper, 'Try again').trigger('click'); await flushPromises();
        await button(wrapper, 'Next→').trigger('click'); await flushPromises();
        expect(api.fetchMyCourses).toHaveBeenLastCalledWith(2);
    });
});

describe('Learning player', () => {
    it('does not render protected content until both authorized responses resolve', async () => {
        const pending = deferred(); api.fetchCourseProgress.mockReturnValue(pending.promise);
        const wrapper = render(CourseLearningPage, { slug: 'bim' }); await flushPromises();
        expect(wrapper.text()).toContain('Loading'); expect(wrapper.text()).not.toContain('Protected reading');
        pending.resolve(progress()); await flushPromises(); expect(wrapper.text()).toContain('BIM Course');
    });
    it.each([401, 403, 404])('handles %s without a protected content flash', async (status) => {
        api.fetchLearningCourse.mockRejectedValue({ status });
        const wrapper = render(CourseLearningPage, { slug: 'bim' }); await flushPromises();
        expect(wrapper.find('article').exists()).toBe(false); expect(wrapper.text()).not.toContain('Protected reading'); expect(wrapper.text()).toContain('Back to My Courses');
    });
    it('does not trust a false has_access response', async () => {
        const wrapper = await player({ ...details(), has_access: false });
        expect(wrapper.text()).toContain('no longer have access'); expect(wrapper.find('article').exists()).toBe(false);
    });
    it('uses server resume, published curriculum, and safe requested IDs', async () => {
        const wrapper = await player(); expect(wrapper.getComponent(VideoLesson).props('lesson').id).toBe(2);
        expect(wrapper.text()).toContain('First section'); expect(wrapper.text()).toContain('Not started');
        route.query.lesson = '3'; const next = await player(); expect(next.find('[data-testid="file-lesson"]').exists()).toBe(true);
        route.query.lesson = '999'; const invalid = await player(); expect(invalid.getComponent(VideoLesson).props('lesson').id).toBe(2);
    });
    it('falls back to the first incomplete lesson when resume is absent', async () => {
        api.fetchCourseProgress.mockResolvedValue(progress({ resume: null }, [{ lesson_id: 1, status: 'completed' }]));
        const wrapper = await player(); expect(wrapper.getComponent(VideoLesson).props('lesson').id).toBe(2);
    });
    it('navigates previous/next and rechecks access before selecting lessons', async () => {
        const wrapper = await player();
        await wrapper.get('[data-testid="previous-lesson"]').trigger('click'); await flushPromises();
        expect(wrapper.find('[data-testid="text-lesson"]').exists()).toBe(true);
        expect(wrapper.get('[data-testid="previous-lesson"]').attributes('disabled')).toBeDefined();
        await wrapper.get('[data-testid="next-lesson"]').trigger('click'); await flushPromises();
        expect(wrapper.find('video').exists()).toBe(true); expect(api.fetchCourseProgress).toHaveBeenCalledTimes(3);
    });
    it('supports mobile curriculum toggle, selection and escape', async () => {
        const wrapper = render(LearningCurriculum, { sections: details().curriculum, activeId: 1 });
        const toggle = wrapper.get('[aria-controls="learning-curriculum"]');
        expect(toggle.attributes('aria-expanded')).toBe('false');
        await toggle.trigger('click'); expect(toggle.attributes('aria-expanded')).toBe('true');
        await wrapper.get('[aria-current="step"]').trigger('click'); expect(wrapper.emitted('select')[0]).toEqual([1]); expect(toggle.attributes('aria-expanded')).toBe('false');
        await toggle.trigger('click'); await toggle.trigger('keydown', { key: 'Escape' }); expect(toggle.attributes('aria-expanded')).toBe('false');
    });
    it('shows the empty-course server behavior', async () => {
        api.fetchCourseProgress.mockResolvedValue(progress({ completed_lessons: 0, total_lessons: 0, progress_percentage: 0, resume: null }));
        const wrapper = await player({ ...details(), curriculum: [] });
        expect(wrapper.text()).toContain('0 of 0 lessons'); expect(wrapper.text()).toContain('No published lessons'); expect(wrapper.find('article').exists()).toBe(false);
    });
    it.each(['text', 'video', 'file', 'link'])('renders only the actual %s type', async (type) => {
        route.query.lesson = String(['text', 'video', 'file', 'link'].indexOf(type) + 1);
        const wrapper = await player(); expect(wrapper.find(`[data-testid="${type}-lesson"]`).exists()).toBe(true);
    });
    it('escapes stored markup instead of executing raw HTML', () => {
        const wrapper = render(TextLesson, { lesson: { content: '<img src=x onerror="alert(1)"><script>alert(1)</script>' } });
        expect(wrapper.find('img').exists()).toBe(false); expect(wrapper.find('script').exists()).toBe(false); expect(wrapper.text()).toContain('<script>');
    });
    it('does not invent video URLs from provider IDs', () => {
        const wrapper = render(VideoLesson, { lesson: { video_id: 'abc', video_provider: 'youtube', video_url: null } });
        expect(wrapper.find('video').exists()).toBe(false); expect(wrapper.find('iframe').exists()).toBe(false); expect(wrapper.text()).toContain('No playable video URL');
    });
    it('restores the server video position and emits an explicit save', async () => {
        const wrapper = render(VideoLesson, { lesson: { video_url: 'https://example.test/a.mp4', progress: { last_position_seconds: 17 } } });
        const video = wrapper.get('video'); Object.defineProperty(video.element, 'duration', { value: 60 });
        await video.trigger('loadedmetadata'); expect(video.element.currentTime).toBe(17);
        video.element.currentTime = 21;
        await wrapper.get('button').trigger('click'); expect(wrapper.emitted('save-position')[0]).toEqual([21]);
    });
    it.each(['javascript:alert(1)', 'file:///C:/private.pdf', 'https://example.test/storage/a.pdf', 'https://example.test/%70rivate/a.pdf', 'https://example.test/%2570rivate/a.pdf', 'https://example.test/a?X-Amz-Signature=secret', 'https://u:p@example.test/a', '//example.test/a', 'https://example.test/%zz'])('rejects unsafe lesson URL %s', (url) => {
        expect(safeLearningUrl(url)).toBeNull();
        expect(render(LinkLesson, { lesson: { video_url: url } }).find('a').exists()).toBe(false);
    });
});

describe('Progress and resources', () => {
    it('shows server percentages rather than deriving them from curriculum', async () => {
        api.fetchCourseProgress.mockResolvedValue(progress({ completed_lessons: 5, total_lessons: 8, progress_percentage: 62.5 }));
        const wrapper = await player(); expect(wrapper.text()).toContain('62.5%'); expect(wrapper.text()).toContain('5 of 8 lessons');
    });
    it('confirms completion, prevents duplicates and synchronizes lesson and course', async () => {
        route.query.lesson = '1'; const wrapper = await player(); const pending = deferred(); api.completeLesson.mockReturnValue(pending.promise);
        api.fetchCourseProgress.mockResolvedValue(progress({ completed_lessons: 2, progress_percentage: 50 }, [{ lesson_id: 1, status: 'completed' }]));
        const complete = wrapper.get('[data-testid="complete-lesson"]'); await complete.trigger('click'); await complete.trigger('click');
        expect(api.completeLesson).toHaveBeenCalledTimes(1); expect(api.completeLesson).toHaveBeenCalledWith('bim', 1); expect(wrapper.text()).not.toContain('50%');
        pending.resolve({ status: 'completed' }); await flushPromises(); expect(wrapper.text()).toContain('50%'); expect(complete.text()).toContain('Lesson complete'); expect(complete.attributes('disabled')).toBeDefined();
    });
    it('does not optimistically complete after a failed mutation and can retry', async () => {
        const wrapper = await player(); api.completeLesson.mockRejectedValueOnce({ status: 500 });
        await wrapper.get('[data-testid="complete-lesson"]').trigger('click'); await flushPromises();
        expect(wrapper.text()).toContain('Unable to load or save'); expect(wrapper.get('[data-testid="complete-lesson"]').text()).toContain('Mark lesson complete');
        await wrapper.get('[data-testid="complete-lesson"]').trigger('click'); await flushPromises(); expect(api.completeLesson).toHaveBeenCalledTimes(2);
    });
    it('saves clamped video position without fabricating watched time', async () => {
        const wrapper = await player(); wrapper.getComponent(VideoLesson).vm.$emit('save-position', 999); await flushPromises();
        expect(api.saveLessonProgress).toHaveBeenCalledWith('bim', 2, { last_position_seconds: 300 }); expect(api.fetchCourseProgress).toHaveBeenCalledTimes(2);
    });
    it.each(['complete', 'select', 'download', 'synchronize'])('clears protected content after lost access during %s', async (operation) => {
        const data = details(); data.curriculum[0].lessons[1].resources = [{ id: 8, title: 'Worksheet', is_downloadable: true, download_available: true }];
        const wrapper = await player(data);
        if (operation === 'complete') { api.completeLesson.mockRejectedValue({ status: 403 }); await wrapper.get('[data-testid="complete-lesson"]').trigger('click'); }
        if (operation === 'select') { api.fetchCourseProgress.mockRejectedValue({ status: 403 }); await wrapper.get('[data-testid="next-lesson"]').trigger('click'); }
        if (operation === 'download') { api.downloadLessonResource.mockRejectedValue({ status: 403 }); await button(wrapper, 'Download').trigger('click'); }
        if (operation === 'synchronize') { api.fetchCourseProgress.mockRejectedValue({ status: 403 }); await wrapper.get('[data-testid="complete-lesson"]').trigger('click'); }
        await flushPromises(); expect(wrapper.find('article').exists()).toBe(false); expect(wrapper.find('video').exists()).toBe(false); expect(wrapper.text()).not.toContain('Protected reading'); expect(wrapper.text()).toContain('no longer have access');
    });
    it('downloads protected resources with duplicate guard, no path links, and retry state', async () => {
        const data = details(); data.curriculum[0].lessons[1].resources = [{ id: 8, title: 'Worksheet', is_downloadable: true, download_available: true, file_path: 'private/do-not-link.pdf' }];
        const wrapper = await player(data); const pending = deferred(); api.downloadLessonResource.mockReturnValueOnce(pending.promise);
        const download = button(wrapper, 'Download'); await download.trigger('click'); await download.trigger('click');
        expect(api.downloadLessonResource).toHaveBeenCalledTimes(1); expect(api.downloadLessonResource).toHaveBeenCalledWith('bim', 2, 8); expect(wrapper.text()).toContain('Downloading'); expect(wrapper.html()).not.toContain('do-not-link');
        pending.reject({ status: 500 }); await flushPromises(); expect(wrapper.text()).toContain('could not be downloaded');
        await button(wrapper, 'Download').trigger('click'); await flushPromises(); expect(api.downloadLessonResource).toHaveBeenCalledTimes(2);
    });
    it('does not offer disabled files or unsafe external resources', () => {
        const wrapper = render(LessonResources, { resources: [{ id: 1, title: 'Disabled', is_downloadable: false, download_available: true, file_path: 'private/secret.pdf' }, { id: 2, title: 'Unsafe', external_url: 'javascript:alert(1)' }, { id: 3, title: 'Reference', external_url: 'https://example.test/reference' }] });
        expect(wrapper.find('button').exists()).toBe(false); expect(wrapper.findAll('a')).toHaveLength(1); expect(wrapper.html()).not.toContain('private/secret');
    });
    it('ignores a late response after navigating to a different course', async () => {
        const pending = deferred(); api.fetchLearningCourse.mockReturnValueOnce(pending.promise);
        const wrapper = render(CourseLearningPage, { slug: 'old' }); await wrapper.setProps({ slug: 'new' }); await flushPromises();
        pending.resolve({ ...details(), course: { title: 'Old course' } }); await flushPromises();
        expect(wrapper.text()).not.toContain('Old course'); expect(wrapper.text()).toContain('BIM Course');
    });
});

describe('Learning localization', () => {
    it.each([['ar', 'rtl', 'دوراتي', 'وصول دائم', 'الدرس التالي'], ['en', 'ltr', 'My Courses', 'Lifetime access', 'Next lesson']])('localizes %s layout and progress/access states', async (locale, dir, title, lifetime, next) => {
        setLocale(locale); api.fetchMyCourses.mockResolvedValue({ items: [enrollment({ is_lifetime: true })] });
        const cards = render(MyCoursesPage); await flushPromises(); expect(cards.attributes('dir')).toBe(dir); expect(cards.text()).toContain(title); expect(cards.text()).toContain(lifetime);
        const wrapper = await player(); expect(wrapper.attributes('dir')).toBe(dir); expect(wrapper.text()).toContain(next); expect(document.documentElement.dir).toBe(dir);
        expect(wrapper.get('[data-testid="next-lesson"]').text()).toContain(locale === 'ar' ? '←' : '→');
        expect(wrapper.findComponent(ProgressSummary).exists()).toBe(true);
    });
});

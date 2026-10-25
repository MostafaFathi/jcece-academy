import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import i18n, { setLocale } from '../i18n';
import LessonDiscussion from '../components/learning/LessonDiscussion.vue';
import * as discussionApi from '../api/lesson-discussions';

vi.mock('../api/lesson-discussions', () => ({ fetchLessonComments: vi.fn(), fetchLessonReplies: vi.fn(), postLessonComment: vi.fn() }));

const reply = (id, body, parentId = 1) => ({ id, lesson_id: 11, parent_id: parentId, body, created_at: '2026-10-25T12:00:00Z', author: { id: 8, name: 'Instructor' } });
const comment = (id, body, replies = [], nextReplyAfter = null, repliesCount = replies.length) => ({ id, lesson_id: 11, parent_id: null, body, created_at: '2026-10-25T12:00:00Z', author: { id: 7, name: 'Student' }, replies, replies_count: repliesCount, next_reply_after: nextReplyAfter });
const render = (attachTo = null) => mount(LessonDiscussion, { props: { courseSlug: 'course', lessonId: 11, instructorId: 8 }, global: { plugins: [i18n] }, ...(attachTo ? { attachTo } : {}) });

beforeEach(() => {
    vi.resetAllMocks();
    setActivePinia(createPinia());
    setLocale('en');
    discussionApi.fetchLessonComments.mockResolvedValue({ data: [comment(1, 'A question')], next_before: null });
});

describe('Lesson discussion', () => {
    it('loads one lesson and posts a reply without reloading other lessons', async () => {
        discussionApi.postLessonComment.mockResolvedValue(reply(2, 'An answer'));
        const wrapper = render(document.body);
        await flushPromises();

        expect(discussionApi.fetchLessonComments).toHaveBeenCalledWith('course', 11);
        await wrapper.get('li button').trigger('click');
        expect(wrapper.text()).toContain('Replying to Student');
        expect(wrapper.get('textarea').element).toBe(document.activeElement);
        await wrapper.get('textarea').setValue('An answer');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(discussionApi.postLessonComment).toHaveBeenCalledWith('course', 11, { body: 'An answer', parent_id: 1 });
        expect(wrapper.get('button[aria-controls="lesson-replies-11-1"]').attributes('aria-expanded')).toBe('true');
        expect(wrapper.get('ol > li').text()).toContain('An answer');
        expect(wrapper.text()).toContain('Instructor');
        expect(wrapper.text().match(/A question/g)).toHaveLength(1);
        expect(wrapper.get('textarea').element.value).toBe('');
        wrapper.unmount();
    });

    it('loads older comments and resets when the lesson changes', async () => {
        discussionApi.fetchLessonComments.mockResolvedValueOnce({ data: [comment(3, 'Newest')], next_before: 3 })
            .mockResolvedValueOnce({ data: [comment(1, 'Oldest')], next_before: null })
            .mockResolvedValueOnce({ data: [], next_before: null });
        const wrapper = render();
        await flushPromises();
        await wrapper.findAll('button').find((button) => button.text() === 'Show older comments').trigger('click');
        await flushPromises();

        expect(discussionApi.fetchLessonComments).toHaveBeenCalledWith('course', 11, 3);
        expect(wrapper.text().indexOf('Oldest')).toBeLessThan(wrapper.text().indexOf('Newest'));
        await wrapper.setProps({ lessonId: 12 });
        await flushPromises();

        expect(discussionApi.fetchLessonComments).toHaveBeenCalledWith('course', 12);
        expect(wrapper.text()).not.toContain('Newest');
        expect(wrapper.text()).toContain('No comments yet');
    });

    it('preserves the draft on a failed post', async () => {
        discussionApi.postLessonComment.mockRejectedValue({ status: 500 });
        const wrapper = render();
        await flushPromises();
        await wrapper.get('textarea').setValue('Keep this draft');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.get('textarea').element.value).toBe('Keep this draft');
        expect(wrapper.get('[role="alert"]').text()).toContain('Unable to post');
    });

    it('renders user text as text rather than HTML', async () => {
        discussionApi.fetchLessonComments.mockResolvedValue({ data: [comment(1, '<script>alert(1)</script>')], next_before: null });
        const wrapper = render();
        await flushPromises();

        expect(wrapper.text()).toContain('<script>alert(1)</script>');
        expect(wrapper.find('script').exists()).toBe(false);
    });

    it('keeps an unfinished draft while refreshing new comments', async () => {
        const wrapper = render();
        await flushPromises();
        await wrapper.get('textarea').setValue('Still writing');
        discussionApi.fetchLessonComments.mockResolvedValue({ data: [comment(2, 'New answer'), comment(1, 'A question')], next_before: null });
        await wrapper.findAll('button').find((button) => button.text() === 'Refresh discussion').trigger('click');
        await flushPromises();

        expect(wrapper.get('textarea').element.value).toBe('Still writing');
        expect(wrapper.text()).toContain('New answer');
    });

    it('shows more replies beneath their parent without quote blocks', async () => {
        const firstReplies = [reply(2, 'First answer'), reply(3, 'Second answer')];
        discussionApi.fetchLessonComments.mockResolvedValue({ data: [comment(1, 'A question', firstReplies, 3, 3)], next_before: null });
        discussionApi.fetchLessonReplies.mockResolvedValue({ data: [reply(4, 'Third answer')], next_after: null });
        const wrapper = render();
        await flushPromises();
        await wrapper.findAll('button').find((button) => button.text().startsWith('Show replies')).trigger('click');
        await wrapper.findAll('button').find((button) => button.text().startsWith('Show more replies')).trigger('click');
        await flushPromises();

        expect(discussionApi.fetchLessonReplies).toHaveBeenCalledWith('course', 11, 1, 3);
        expect(wrapper.get('ol > li').text()).toContain('Third answer');
        expect(wrapper.text().match(/A question/g)).toHaveLength(1);
    });

    it('places a reply to another reply in the original thread', async () => {
        discussionApi.fetchLessonComments.mockResolvedValue({ data: [comment(1, 'A question', [reply(2, 'First answer')])], next_before: null });
        discussionApi.postLessonComment.mockResolvedValue(reply(3, 'Follow-up'));
        const wrapper = render();
        await flushPromises();
        await wrapper.get('button[aria-controls="lesson-replies-11-1"]').trigger('click');
        await wrapper.get('ol > li ol li button').trigger('click');
        await wrapper.get('textarea').setValue('Follow-up');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(discussionApi.postLessonComment).toHaveBeenCalledWith('course', 11, { body: 'Follow-up', parent_id: 1 });
        expect(wrapper.get('ol > li ol').findAll('li')).toHaveLength(2);
        expect(wrapper.text().match(/A question/g)).toHaveLength(1);
    });

    it('collapses replies by default and toggles each thread independently', async () => {
        discussionApi.fetchLessonComments.mockResolvedValue({ data: [
            comment(1, 'First question', [reply(2, 'First reply')]),
            comment(3, 'Second question', [reply(4, 'Second reply', 3)]),
        ], next_before: null });
        const wrapper = render();
        await flushPromises();

        const firstToggle = wrapper.get('button[aria-controls="lesson-replies-11-1"]');
        const secondToggle = wrapper.get('button[aria-controls="lesson-replies-11-3"]');
        expect(firstToggle.attributes('aria-expanded')).toBe('false');
        expect(secondToggle.attributes('aria-expanded')).toBe('false');
        expect(wrapper.find('#lesson-replies-11-1 ol').exists()).toBe(false);
        expect(wrapper.find('#lesson-replies-11-3 ol').exists()).toBe(false);

        await firstToggle.trigger('click');
        expect(firstToggle.attributes('aria-expanded')).toBe('true');
        expect(wrapper.get('#lesson-replies-11-1').text()).toContain('First reply');
        expect(wrapper.find('#lesson-replies-11-3 ol').exists()).toBe(false);

        await secondToggle.trigger('click');
        await firstToggle.trigger('click');
        expect(firstToggle.attributes('aria-expanded')).toBe('false');
        expect(secondToggle.attributes('aria-expanded')).toBe('true');
        expect(wrapper.find('#lesson-replies-11-1 ol').exists()).toBe(false);
        expect(wrapper.get('#lesson-replies-11-3').text()).toContain('Second reply');
    });

    it('posts an image-only comment as multipart data and displays its protected preview', async () => {
        const uploaded = { ...comment(5, null), attachment: { name: 'diagram.png', is_image: true, url: '/api/v1/me/protected-image' } };
        discussionApi.postLessonComment.mockResolvedValue(uploaded);
        const wrapper = render();
        await flushPromises();
        const file = new File(['image'], 'diagram.png', { type: 'image/png' });
        const input = wrapper.get('input[type="file"]');
        Object.defineProperty(input.element, 'files', { configurable: true, value: [file] });

        await input.trigger('change');
        expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeUndefined();
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        const payload = discussionApi.postLessonComment.mock.calls[0][2];
        expect(payload).toBeInstanceOf(FormData);
        expect(payload.get('attachment')).toBe(file);
        expect(payload.has('body')).toBe(false);
        expect(wrapper.get('img[alt="diagram.png"]').attributes('src')).toBe('/api/v1/me/protected-image');
        expect(wrapper.find('input[type="file"]').element.value).toBe('');
    });

    it('keeps an attachment selected after a failed reply and rejects files over 10 MB locally', async () => {
        discussionApi.postLessonComment.mockRejectedValue({ status: 500 });
        const wrapper = render();
        await flushPromises();
        await wrapper.get('li button').trigger('click');
        const input = wrapper.get('input[type="file"]');
        const oversized = new File(['x'], 'huge.pdf', { type: 'application/pdf' });
        Object.defineProperty(oversized, 'size', { value: 10 * 1024 * 1024 + 1 });
        Object.defineProperty(input.element, 'files', { configurable: true, value: [oversized] });

        await input.trigger('change');
        expect(wrapper.get('[role="alert"]').text()).toContain('no larger than 10 MB');
        expect(wrapper.get('button[type="submit"]').attributes('disabled')).toBeDefined();

        const valid = new File(['document'], 'handout.pdf', { type: 'application/pdf' });
        Object.defineProperty(input.element, 'files', { configurable: true, value: [valid] });
        await input.trigger('change');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        const payload = discussionApi.postLessonComment.mock.calls[0][2];
        expect(payload.get('parent_id')).toBe('1');
        expect(payload.get('attachment')).toBe(valid);
        expect(wrapper.text()).toContain('handout.pdf');
        expect(wrapper.get('[role="alert"]').text()).toContain('Unable to post');
    });
});

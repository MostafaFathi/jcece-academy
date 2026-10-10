import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { defineComponent, nextTick } from 'vue';
import i18n, { setLocale } from '../i18n';
import { useAuthStore } from '../stores/auth';
import { mergeMessages, useCourseMessaging } from '../composables/useCourseMessaging';
import { useVoiceRecorder } from '../composables/useVoiceRecorder';
import * as api from '../api/messaging';
import CourseMessenger from '../components/messaging/CourseMessenger.vue';
import MessageComposer from '../components/messaging/MessageComposer.vue';
import GroupWizard from '../components/messaging/GroupWizard.vue';
vi.mock('../api/messaging', () => ({ fetchMessagingCourses: vi.fn(), fetchMessagingStudents: vi.fn(), fetchConversations: vi.fn(), fetchConversation: vi.fn(), startPrivate: vi.fn(), createGroup: vi.fn(), updateMembers: vi.fn(), fetchMessages: vi.fn(), fetchMessage: vi.fn(), fetchEvents: vi.fn(), sendMessage: vi.fn(), markRead: vi.fn(), deleteMessage: vi.fn(), reactToMessage: vi.fn(), fetchMessageNotifications: vi.fn() }));
const conversation = { id: 3, title: 'Private', course: { id: 1, title: 'BIM' }, kind: 'private', can_send: true, can_manage: false, version: 1, unread_count: 1 };
const message = { id: 8, client_id: '123', body: '<script>alert(1)</script> مرحبا', user: { id: 2, name: 'Teacher' }, attachments: [], reactions: [], reader_count: 0, can_delete: false };
let wrappers = [];
function render(component, props = {}, options = {}) { const wrapper = mount(component, { props, ...options, global: { plugins: [i18n] } }); wrappers.push(wrapper); return wrapper; }
const button = (wrapper, label) => wrapper.findAll('button').find((item) => item.text().includes(label));
function deferred() { let resolve, reject; const promise = new Promise((yes, no) => { resolve = yes; reject = no; }); return { promise, resolve, reject }; }
beforeEach(() => {
    vi.resetAllMocks(); vi.useFakeTimers(); setLocale('en'); setActivePinia(createPinia());
    useAuthStore().setUser({ id: 1, name: 'Student', roles: ['student'], permissions: ['messaging.view', 'messaging.send'] });
    Object.defineProperty(navigator, 'onLine', { configurable: true, value: true });
    Object.defineProperty(document, 'hidden', { configurable: true, value: false });
    api.fetchMessagingCourses.mockResolvedValue([{ id: 1, title: 'BIM', is_instructor: false }]);
    api.fetchConversations.mockResolvedValue({ items: [conversation], meta: { current_page: 1, last_page: 1 } });
    api.fetchConversation.mockResolvedValue(conversation);
    api.fetchMessages.mockResolvedValue({ items: [message], meta: {} });
    api.fetchMessageNotifications.mockResolvedValue({ data: [], unread_count: 1 });
    api.fetchEvents.mockResolvedValue({ data: [], version: 1 });
    api.markRead.mockResolvedValue({});
    api.fetchMessagingStudents.mockResolvedValue({ items: [{ id: 7, name: 'Eligible student' }], meta: { last_page: 1 } });
    URL.createObjectURL = vi.fn(() => 'blob:test'); URL.revokeObjectURL = vi.fn();
});
afterEach(() => { wrappers.forEach((wrapper) => wrapper.unmount()); wrappers = []; vi.useRealTimers(); });

describe('message reconciliation', () => {
    it('replaces optimistic messages and ignores duplicate events without losing old history', () => {
        const pending = { ...message, id: null, pending: true };
        const result = mergeMessages([{ ...message, id: 2, client_id: 'old' }, pending], [message, { ...message, body: 'Server result' }]);
        expect(result).toHaveLength(2); expect(result[1].pending).toBeUndefined(); expect(result[1].body).toBe('Server result');
    });
    it('does not merge matching client UUIDs belonging to different senders', () => {
        expect(mergeMessages([message], [{ ...message, id: 9, user: { id: 3 } }])).toHaveLength(2);
    });
});

describe('shared messenger', () => {
    it.each(['ar', 'en'])('renders safe UTF-8 text with %s direction, real unread state and course scope', async (locale) => {
        setLocale(locale); const wrapper = render(CourseMessenger, { courseId: 1 }); await flushPromises();
        expect(wrapper.attributes('dir')).toBe(locale === 'ar' ? 'rtl' : 'ltr');
        await wrapper.find('nav button').trigger('click'); await flushPromises();
        expect(wrapper.text()).toContain(message.body); expect(wrapper.find('script').exists()).toBe(false);
        expect(api.markRead).toHaveBeenCalledWith(3, 8);
        expect(api.fetchConversations).toHaveBeenCalledWith(expect.objectContaining({ course_id: 1 }));
    });
    it('uses the canonical private conversation from course entry', async () => {
        api.startPrivate.mockResolvedValue(conversation);
        const wrapper = render(CourseMessenger, { courseId: 1 }); await flushPromises();
        await button(wrapper, 'Private conversation').trigger('click'); await flushPromises();
        expect(api.startPrivate).toHaveBeenCalledWith(1); expect(api.fetchConversation).toHaveBeenCalledWith(3);
    });
    it('renders the shared unified inbox and exposes unread filtering', async () => {
        const wrapper = render(CourseMessenger); await flushPromises();
        expect(wrapper.find('select').exists()).toBe(true);
        await wrapper.find('input[type=checkbox]').setValue(true); await flushPromises();
        expect(api.fetchConversations).toHaveBeenLastCalledWith(expect.objectContaining({ unread: 1 }));
    });
    it('keeps history search scoped during polling and does not advance read cursors through search', async () => {
        const wrapper = render(CourseMessenger, { courseId: 1 }); await flushPromises();
        await wrapper.find('nav button').trigger('click'); await flushPromises();
        api.markRead.mockClear();
        api.fetchMessages.mockResolvedValue({ items: [{ ...message, id: 4, body: 'Matched history' }], meta: {} });
        await wrapper.find('#message-search').setValue('Matched');
        await wrapper.find('#message-search').element.closest('form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })); await flushPromises();
        api.fetchEvents.mockResolvedValue({ data: [{ version: 2, kind: 'message', subject_id: 9 }], version: 2 });
        await vi.advanceTimersByTimeAsync(5000); await flushPromises();
        expect(api.fetchMessages).toHaveBeenLastCalledWith(3, { search: 'Matched' });
        expect(api.markRead).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain('Matched history');
    });
    it('makes the active search state clearable without leaving the conversation', async () => {
        const wrapper = render(CourseMessenger, { courseId: 1 }); await flushPromises();
        await wrapper.find('nav button').trigger('click'); await flushPromises();
        await wrapper.find('#message-search').setValue('Matched');
        await wrapper.find('#message-search').element.closest('form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })); await flushPromises();

        expect(wrapper.text()).toContain('Showing search results');
        await wrapper.find('button[aria-label="Clear message search"]').trigger('click'); await flushPromises();
        expect(api.fetchMessages).toHaveBeenLastCalledWith(3, { search: undefined });
        expect(wrapper.find('textarea').exists()).toBe(true);
    });
    it('clears private content when access is revoked during catch-up', async () => {
        const wrapper = render(CourseMessenger, { courseId: 1 }); await flushPromises(); await wrapper.find('nav button').trigger('click'); await flushPromises();
        api.fetchEvents.mockRejectedValue({ status: 404 }); await vi.advanceTimersByTimeAsync(5000); await flushPromises();
        expect(wrapper.text()).not.toContain(message.body); expect(wrapper.text()).toContain('no longer available');
    });
    it('hides actions until opening the message popup', async () => {
        api.fetchMessages.mockResolvedValue({ items: [{ ...message, can_delete: true }], meta: {} });
        const wrapper = render(CourseMessenger, { courseId: 1 }); await flushPromises();
        await wrapper.find('nav button').trigger('click'); await flushPromises();

        expect(wrapper.find('article button').exists()).toBe(false);
        await wrapper.find('article').trigger('click'); await flushPromises();
        const popover = document.querySelector('#reaction-popover');
        expect(popover.closest('article')).toBeNull();
        for (const label of ['Reply', 'React', 'Delete']) {
            const action = popover.querySelector(`button[aria-label="${label}"]`);
            expect(action).not.toBeNull();
            expect(action.textContent).toBe('');
            expect(action.querySelector('svg')).not.toBeNull();
        }
    });
    it('opens reactions in an overlay without expanding the message and closes on selection', async () => {
        const wrapper = render(CourseMessenger, { courseId: 1 }); await flushPromises();
        await wrapper.find('nav button').trigger('click'); await flushPromises();
        const messageText = wrapper.find('article').text();

        await wrapper.find('article').trigger('click'); await flushPromises();
        const actionPosition = document.querySelector('#reaction-popover').getAttribute('style');
        document.querySelector('#reaction-popover button[aria-label="React"]').click(); await flushPromises();
        const popover = document.querySelector('#reaction-popover');
        expect(popover).not.toBeNull();
        expect(popover.closest('article')).toBeNull();
        expect(popover.classList.contains('fixed')).toBe(true);
        expect(popover.getAttribute('style')).toContain(actionPosition.match(/top: [^;]+/)[0]);
        expect(popover.getAttribute('style')).toContain(actionPosition.match(/left: [^;]+/)[0]);
        expect(wrapper.find('article').text()).toBe(messageText);

        popover.querySelector('button').click(); await flushPromises();
        expect(api.reactToMessage).toHaveBeenCalledWith(8, '👍');
        expect(document.querySelector('#reaction-popover')).toBeNull();
    });
    it('dismisses the reaction overlay with Escape and restores focus', async () => {
        const wrapper = render(CourseMessenger, { courseId: 1 }, { attachTo: document.body }); await flushPromises();
        await wrapper.find('nav button').trigger('click'); await flushPromises();
        const trigger = wrapper.find('article');

        await trigger.trigger('click'); await flushPromises();
        document.querySelector('#reaction-popover button[aria-label="React"]').click(); await flushPromises();
        document.querySelector('#reaction-popover button').dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        await flushPromises();

        expect(document.querySelector('#reaction-popover')).toBeNull();
        expect(document.activeElement).toBe(trigger.element);
    });
    it('shows relative message time and exposes the full date on hover', async () => {
        vi.setSystemTime(new Date('2026-10-10T12:15:00Z'));
        api.fetchMessages.mockResolvedValue({ items: [{ ...message, created_at: '2026-10-10T12:00:00Z' }], meta: {} });
        const wrapper = render(CourseMessenger, { courseId: 1 }); await flushPromises();
        await wrapper.find('nav button').trigger('click'); await flushPromises();

        const timestamp = wrapper.get('article time');
        expect(timestamp.text()).toBe('15 minutes ago');
        expect(timestamp.attributes('title')).toContain('October 10, 2026');
        await vi.advanceTimersByTimeAsync(60000);
        expect(timestamp.text()).toBe('16 minutes ago');
    });
    it('enlarges emoji graphemes in messages without rendering HTML', async () => {
        api.fetchMessages.mockResolvedValue({ items: [{ ...message, body: 'Hello 👩‍💻 🇵🇸 <script>x</script>' }], meta: {} });
        const wrapper = render(CourseMessenger, { courseId: 1 }); await flushPromises();
        await wrapper.find('nav button').trigger('click'); await flushPromises();

        const body = wrapper.get('article p');
        expect(body.text()).toContain('👩‍💻 🇵🇸 <script>x</script>');
        expect(body.find('script').exists()).toBe(false);
        expect(body.findAll('span').filter((part) => part.classes().includes('text-[1.65rem]'))).toHaveLength(2);
    });
    it.each(['ar', 'en'])('aligns own messages right and others left in %s', async (language) => {
        setLocale(language);
        api.fetchMessages.mockResolvedValue({ items: [message, { ...message, id: 9, user: { id: 1, name: 'Student' } }], meta: {} });
        const wrapper = render(CourseMessenger, { courseId: 1 }); await flushPromises();
        await wrapper.find('nav button').trigger('click'); await flushPromises();

        const bubbles = wrapper.findAll('article');
        expect(bubbles[0].element.parentElement.parentElement.classList.contains('justify-start')).toBe(true);
        expect(bubbles[1].element.parentElement.parentElement.classList.contains('justify-end')).toBe(true);
        expect(bubbles[0].attributes('dir')).toBe(language === 'ar' ? 'rtl' : 'ltr');
    });
    it('shows a small protected image in a sent reply and a voice icon for audio replies', async () => {
        api.fetchMessages.mockResolvedValue({ items: [
            { ...message, id: 10, client_id: 'reply-image', reply: { id: 8, body: null, deleted: false, attachment: { kind: 'image', name: 'photo.png', url: '/private/photo' } } },
            { ...message, id: 11, client_id: 'reply-voice', reply: { id: 9, body: null, deleted: false, attachment: { kind: 'voice', name: 'note.webm', url: '/private/voice' } } },
        ], meta: {} });
        const wrapper = render(CourseMessenger, { courseId: 1 }); await flushPromises();
        await wrapper.find('nav button').trigger('click'); await flushPromises();

        expect(wrapper.find('blockquote img[src="/private/photo"]').classes()).toContain('size-12');
        expect(wrapper.findAll('blockquote').at(1).find('svg').exists()).toBe(true);
        expect(wrapper.findAll('blockquote').at(1).text()).toContain('note.webm');
    });
    it('shows applied reactions as compact pills below the message bubble', async () => {
        api.fetchMessages.mockResolvedValue({ items: [{ ...message, reactions: [{ emoji: '😂', count: 1, mine: true }] }], meta: {} });
        const wrapper = render(CourseMessenger, { courseId: 1 }); await flushPromises();
        await wrapper.find('nav button').trigger('click'); await flushPromises();

        const reaction = wrapper.findAll('button').find((button) => button.attributes('aria-label') === '😂 · 1');
        expect(reaction).toBeDefined();
        expect(reaction.classes()).toContain('rounded-full');
        expect(reaction.attributes('dir')).toBe('ltr');
        expect(reaction.element.closest('article')).toBeNull();
        await reaction.trigger('click'); await flushPromises();
        expect(api.reactToMessage).toHaveBeenCalledWith(8, '😂');
    });
    it('shows a new reaction before the server responds and keeps it after success', async () => {
        const pending = deferred();
        const wrapper = render(CourseMessenger, { courseId: 1 }); await flushPromises();
        await wrapper.find('nav button').trigger('click'); await flushPromises();
        api.reactToMessage.mockImplementation(() => {
            expect(wrapper.findAll('button').some((item) => item.attributes('aria-label') === '👍 · 1')).toBe(true);
            return pending.promise;
        });

        await wrapper.find('article').trigger('click'); await flushPromises();
        document.querySelector('#reaction-popover button[aria-label="React"]').click(); await flushPromises();
        document.querySelector('#reaction-popover button').click();
        await nextTick();
        expect(wrapper.findAll('button').find((item) => item.attributes('aria-label') === '👍 · 1').attributes('disabled')).toBeDefined();

        pending.resolve({}); await flushPromises();
        expect(wrapper.findAll('button').some((item) => item.attributes('aria-label') === '👍 · 1')).toBe(true);
    });
    it('restores the prior reaction when the server rejects an optimistic toggle', async () => {
        api.fetchMessages.mockResolvedValue({ items: [{ ...message, reactions: [{ emoji: '😂', count: 1, mine: true }] }], meta: {} });
        const pending = deferred(); api.reactToMessage.mockReturnValue(pending.promise);
        const wrapper = render(CourseMessenger, { courseId: 1 }); await flushPromises();
        await wrapper.find('nav button').trigger('click'); await flushPromises();
        const applied = () => wrapper.findAll('button').some((item) => item.attributes('aria-label') === '😂 · 1');

        expect(applied()).toBe(true);
        await wrapper.findAll('button').find((item) => item.attributes('aria-label') === '😂 · 1').trigger('click');
        await nextTick();
        expect(applied()).toBe(false);

        pending.reject({ status: 500 }); await flushPromises();
        expect(applied()).toBe(true);
        expect(wrapper.find('[role="alert"]').exists()).toBe(true);
    });
});

describe('composer', () => {
    it('keeps text and client UUID across retries, clears only after acknowledgment, supports emoji and replies', async () => {
        const wrapper = render(MessageComposer, { reply: message });
        await wrapper.find('textarea').setValue('Hello'); await wrapper.find('form').trigger('submit');
        await wrapper.find('form').trigger('submit');
        const events = wrapper.emitted('send'); expect(events[0][0].payload.client_id).toBe(events[1][0].payload.client_id);
        expect(events[0][0].payload.reply_to_id).toBe(8); expect(wrapper.find('textarea').element.value).toBe('Hello');
        events[0][0].acknowledge(); await flushPromises(); expect(wrapper.find('textarea').element.value).toBe('');
        await wrapper.find('button[aria-label="Emoji"]').trigger('click');
        [...document.querySelectorAll('#course-emoji-picker button')].find((item) => item.getAttribute('aria-label') === '😊').click(); await nextTick();
        expect(wrapper.find('textarea').element.value).toBe('😊');
    });
    it('shows a classified emoji overlay without changing composer layout', async () => {
        const wrapper = render(MessageComposer);
        const formHeight = wrapper.find('form').element.getBoundingClientRect().height;
        await wrapper.get('button[aria-label="Emoji"]').trigger('click'); await nextTick();

        const picker = document.querySelector('#course-emoji-picker');
        expect(picker).not.toBeNull();
        expect(picker.classList.contains('fixed')).toBe(true);
        expect(picker.closest('form')).toBeNull();
        expect(wrapper.find('form').element.getBoundingClientRect().height).toBe(formHeight);
        expect(picker.querySelectorAll('button[aria-pressed]')).toHaveLength(9);
        picker.querySelector('.overflow-y-auto').dispatchEvent(new Event('scroll', { bubbles: true }));
        expect(document.querySelector('#course-emoji-picker')).not.toBeNull();
        picker.querySelector('button[aria-label="Animals and nature"]').click(); await nextTick();
        const bear = [...picker.querySelectorAll('button')].find((item) => item.getAttribute('aria-label') === '🐻');
        expect(bear).toBeDefined();
        bear.click(); await nextTick();
        expect(wrapper.get('textarea').element.value).toBe('🐻');
    });
    it('sends on Enter but reserves Shift+Enter for a new line', async () => {
        const wrapper = render(MessageComposer);
        const textarea = wrapper.get('textarea');
        expect(textarea.classes()).toContain('text-xl');
        await textarea.setValue('Hello');
        await textarea.trigger('keydown', { key: 'Enter', shiftKey: true });
        expect(wrapper.emitted('send')).toBeUndefined();
        await textarea.trigger('keydown', { key: 'Enter' });
        expect(wrapper.emitted('send')).toHaveLength(1);
        expect(wrapper.emitted('send')[0][0].payload.body).toBe('Hello');
    });
    it('previews an image or voice note while composing a reply', async () => {
        const image = render(MessageComposer, { reply: { ...message, attachments: [{ kind: 'image', name: 'photo.png', url: '/private/photo' }] } });
        expect(image.get('img[src="/private/photo"]').classes()).toContain('size-12');
        const voice = render(MessageComposer, { reply: { ...message, body: null, attachments: [{ kind: 'voice', name: 'note.webm', url: '/private/voice' }] } });
        expect(voice.find('svg').exists()).toBe(true);
        expect(voice.text()).toContain('note.webm');
    });
    it('shows the server voice-processing reason beside a failed recording', () => {
        setLocale('ar');
        const wrapper = render(MessageComposer, { sendError: { status: 422, errors: { attachment: ['Voice must be valid audio of at most 120 seconds. Server media validation must be available.'] } } });
        expect(wrapper.get('[role="alert"]').text()).toContain('خدمة التحقق من الصوت غير متاحة');
    });
    it('prevents empty sends and displays unsupported microphone handling', async () => {
        const wrapper = render(MessageComposer); await wrapper.find('form').trigger('submit'); expect(wrapper.emitted('send')).toBeUndefined();
        await wrapper.find('button[aria-label="Record voice"]').trigger('click'); await flushPromises(); expect(wrapper.text()).toContain('unavailable in this browser');
    });
    it('labels icon-only composer controls for keyboard and screen-reader use', () => {
        const wrapper = render(MessageComposer);

        for (const label of ['Emoji', 'Record voice']) {
            const action = wrapper.find(`button[aria-label="${label}"]`);
            expect(action.exists()).toBe(true);
            expect(action.text()).toBe('');
            expect(action.find('svg').exists()).toBe(true);
        }
        expect(wrapper.find('input[type="file"][aria-label="Attach"]').exists()).toBe(true);
        expect(wrapper.find('label[for="course-message-file"]').find('svg').exists()).toBe(true);
    });
    it('creates a secure client id when randomUUID is unavailable', async () => {
        vi.stubGlobal('crypto', { getRandomValues: (bytes) => bytes.fill(7) });
        const wrapper = render(MessageComposer);
        await wrapper.find('textarea').setValue('Hello');
        await wrapper.find('form').trigger('submit');

        expect(wrapper.emitted('send')[0][0].payload.client_id).toMatch(/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/);
        vi.unstubAllGlobals();
    });
});

describe('group management', () => {
    it('creates a dynamic all-students group without materializing students', async () => {
        api.createGroup.mockResolvedValue({ ...conversation, kind: 'all' });
        const wrapper = render(GroupWizard, { course: { id: 1 }, mode: 'group' }); await flushPromises();
        await wrapper.find('input').setValue('Future students'); await wrapper.find('select').setValue('all'); await wrapper.find('form').trigger('submit');
        expect(wrapper.text()).toContain('future enrollments'); await wrapper.find('form').trigger('submit'); await flushPromises();
        expect(api.createGroup).toHaveBeenCalledWith(1, { title: 'Future students', kind: 'all', student_ids: [] });
    });
    it('retains selected members when editing and uses explicit membership API', async () => {
        api.updateMembers.mockResolvedValue(conversation);
        const wrapper = render(GroupWizard, { course: { id: 1 }, mode: 'members', conversation: { ...conversation, student_ids: [7] } }); await flushPromises();
        expect(wrapper.find('input[type=checkbox]').element.checked).toBe(true);
        await wrapper.find('form').trigger('submit'); await flushPromises(); expect(api.updateMembers).toHaveBeenCalledWith(3, [7]);
    });
});

describe('polling lifecycle', () => {
    it('pauses while hidden/offline and catches up after reconnect with bounded retry', async () => {
        let chat; const wrapper = render(defineComponent({ setup() { chat = useCourseMessaging(); return {}; }, template: '<div />' })); await flushPromises(); await chat.select(conversation);
        Object.defineProperty(document, 'hidden', { configurable: true, value: true });
        api.fetchEvents.mockClear(); await vi.advanceTimersByTimeAsync(10000); expect(api.fetchEvents).not.toHaveBeenCalled();
        Object.defineProperty(document, 'hidden', { configurable: true, value: false }); document.dispatchEvent(new Event('visibilitychange')); await flushPromises(); expect(api.fetchEvents).toHaveBeenCalled();
        api.fetchEvents.mockClear(); Object.defineProperty(navigator, 'onLine', { configurable: true, value: false }); window.dispatchEvent(new Event('offline')); await vi.advanceTimersByTimeAsync(10000); expect(api.fetchEvents).not.toHaveBeenCalled();
        Object.defineProperty(navigator, 'onLine', { configurable: true, value: true }); window.dispatchEvent(new Event('online')); await flushPromises(); expect(api.fetchEvents).toHaveBeenCalled();
        wrapper.unmount();
    });
});

describe('voice recorder', () => {
    it('handles microphone denial and always releases the pending capture state', async () => {
        vi.stubGlobal('MediaRecorder', class {});
        Object.defineProperty(navigator, 'mediaDevices', { configurable: true, value: { getUserMedia: vi.fn().mockRejectedValue({ name: 'NotAllowedError' }) } });
        let voice; render(defineComponent({ setup() { voice = useVoiceRecorder(); return {}; }, template: '<div />' })); await voice.start();
        expect(voice.error.value).toBe('micDenied'); expect(voice.starting.value).toBe(false);
        vi.unstubAllGlobals();
    });
});

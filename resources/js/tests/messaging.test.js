import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { defineComponent } from 'vue';
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
function render(component, props = {}) { const wrapper = mount(component, { props, global: { plugins: [i18n] } }); wrappers.push(wrapper); return wrapper; }
const button = (wrapper, label) => wrapper.findAll('button').find((item) => item.text().includes(label));
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
    it('clears private content when access is revoked during catch-up', async () => {
        const wrapper = render(CourseMessenger, { courseId: 1 }); await flushPromises(); await wrapper.find('nav button').trigger('click'); await flushPromises();
        api.fetchEvents.mockRejectedValue({ status: 404 }); await vi.advanceTimersByTimeAsync(5000); await flushPromises();
        expect(wrapper.text()).not.toContain(message.body); expect(wrapper.text()).toContain('no longer available');
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
        await button(wrapper, 'Emoji').trigger('click'); await wrapper.findAll('button').find((item) => item.attributes('aria-label') === '😊').trigger('click'); expect(wrapper.find('textarea').element.value).toBe('😊');
    });
    it('prevents empty sends and displays unsupported microphone handling', async () => {
        const wrapper = render(MessageComposer); await wrapper.find('form').trigger('submit'); expect(wrapper.emitted('send')).toBeUndefined();
        await button(wrapper, 'Record voice').trigger('click'); await flushPromises(); expect(wrapper.text()).toContain('unavailable in this browser');
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

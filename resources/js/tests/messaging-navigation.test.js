import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, shallowMount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { reactive } from 'vue';
import i18n, { setLocale } from '../i18n';
import { useAuthStore } from '../stores/auth';
import { fetchMessageNotifications } from '../api/messaging';
import MessageNotificationBadge from '../components/messaging/MessageNotificationBadge.vue';
import MessagingInboxPage from '../pages/MessagingInboxPage.vue';

const state = vi.hoisted(() => ({ route: null }));
vi.mock('vue-router', async (original) => ({ ...(await original()), useRoute: () => state.route }));
vi.mock('../api/messaging', () => ({ fetchMessageNotifications: vi.fn() }));

const routerLink = { name: 'RouterLink', props: ['to'], template: '<a><slot /></a>' };

beforeEach(() => {
    vi.resetAllMocks();
    setLocale('en');
    setActivePinia(createPinia());
    state.route = reactive({ name: 'student.dashboard', query: {} });
    fetchMessageNotifications.mockResolvedValue({ unread_count: 3 });
});

describe('course messaging navigation', () => {
    it('opens the dedicated inbox from a floating unread button only with permission', async () => {
        const auth = useAuthStore();
        const withoutAccess = mount(MessageNotificationBadge, { props: { area: 'student' }, global: { plugins: [i18n], stubs: { RouterLink: routerLink } } });
        expect(withoutAccess.find('a').exists()).toBe(false);
        withoutAccess.unmount();

        auth.setUser({ id: 1, roles: ['student'], permissions: ['messaging.view'] });
        const withAccess = mount(MessageNotificationBadge, { props: { area: 'student' }, global: { plugins: [i18n], stubs: { RouterLink: routerLink } } });
        await flushPromises();
        expect(withAccess.getComponent({ name: 'RouterLink' }).props('to')).toEqual({ name: 'student.messages' });
        expect(withAccess.get('a').classes()).toContain('fixed');
        expect(withAccess.get('a').attributes('aria-label')).toContain('3 unread');
        withAccess.unmount();
    });

    it('does not cover the message composer with the floating button', () => {
        state.route.name = 'instructor.messages';
        useAuthStore().setUser({ id: 2, roles: ['instructor'], permissions: ['messaging.view'] });
        const wrapper = mount(MessageNotificationBadge, { props: { area: 'instructor' }, global: { plugins: [i18n], stubs: { RouterLink: routerLink } } });

        expect(wrapper.find('a').exists()).toBe(false);
        wrapper.unmount();
    });

    it.each([
        ['17', '17'],
        ['0', null],
        ['abc', null],
    ])('scopes the inbox to a valid course query %s', (course, expected) => {
        state.route.query = { course };
        const wrapper = shallowMount(MessagingInboxPage);

        expect(wrapper.getComponent({ name: 'CourseMessenger' }).props('courseId')).toBe(expected);
        wrapper.unmount();
    });
});

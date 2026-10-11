import { beforeEach, afterEach, describe, it, expect, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { reactive } from 'vue';
import i18n, { setLocale } from '../i18n';
import { useAuthStore } from '../stores/auth';
import NotificationBell from '../components/notifications/NotificationBell.vue';
import NotificationCenter from '../pages/NotificationCenterPage.vue';
import Preferences from '../pages/NotificationPreferencesPage.vue';
import Announcement from '../pages/AnnouncementPage.vue';
import Management from '../pages/AnnouncementManagementPage.vue';
import DeliveryManagement from '../pages/NotificationManagementPage.vue';
import * as api from '../api/notifications';
const state = vi.hoisted(() => ({ push: vi.fn(), route: null }));
vi.mock('vue-router', async (original) => ({ ...(await original()), useRouter: () => ({ push: state.push }), useRoute: () => state.route }));
vi.mock('../api/admin', () => ({ fetchAdminCourses: vi.fn().mockResolvedValue({ items: [{ id: 3, title: 'Course A' }], meta: { current_page: 1, last_page: 1 } }) }));
vi.mock('../api/notifications', async (original) => ({ ...(await original()), fetchManagement: vi.fn(), savePolicy: vi.fn(), retryDelivery: vi.fn(), retryTransactionalDelivery: vi.fn(), fetchNotifications: vi.fn(), openNotification: vi.fn(), readNotification: vi.fn(), readAllNotifications: vi.fn(), fetchPreferences: vi.fn(), savePreferences: vi.fn(), fetchAnnouncement: vi.fn(), fetchStaffAnnouncements: vi.fn(), saveAnnouncement: vi.fn(), previewAudience: vi.fn(), publishAnnouncement: vi.fn(), archiveAnnouncement: vi.fn() }));
const item = { id: 'uuid-1', type: 'message.received', category: 'messaging', titles: { ar: 'رسائل جديدة', en: 'New messages' }, count: 3, read_at: null, created_at: '2026-10-10T12:00:00Z' };
const row = { type: 'security.changed', ar: 'إعدادات الأمان', en: 'Security settings', in_app: true, email: true, digest: false, mandatory: true, mandatory_email: true };
let wrappers = [];
const render = (component) => { const w = mount(component, { attachTo: document.body, global: { plugins: [i18n], stubs: { RouterLink: { props: ['to'], template: '<a><slot /></a>' } } } }); wrappers.push(w); return w; };
const button = (w, text) => w.findAll('button').find(b => b.text().includes(text));
beforeEach(() => {
    vi.resetAllMocks(); vi.useFakeTimers(); setActivePinia(createPinia()); setLocale('en'); state.route = reactive({ params: { id: '1' } });
    useAuthStore().setUser({ id: 1, roles: ['student'], permissions: [] });
    Object.defineProperty(document, 'hidden', { configurable: true, value: false }); Object.defineProperty(navigator, 'onLine', { configurable: true, value: true });
    api.fetchNotifications.mockResolvedValue({ data: [item], meta: { unread_count: 2, current_page: 1, last_page: 2 } });
    api.openNotification.mockResolvedValue({ path: '/student/messages?conversation=3' }); api.readNotification.mockResolvedValue({}); api.readAllNotifications.mockResolvedValue({});
    api.fetchPreferences.mockResolvedValue([{ ...row }]); api.savePreferences.mockResolvedValue([{ ...row }]);
    api.fetchManagement.mockResolvedValue({ catalog: [{ ...row }], statistics: { failed: 1 }, legacy_statistics: { failed: 1 }, can_retry_legacy: true, failures: { data: [{ id: 1, type: 'security.changed', error_code: 'RuntimeException', attempts: 1 }], current_page: 1, last_page: 2 }, legacy_failures: { data: [{ id: 2, message_type: 'payment.approved', error_code: 'TransportException', attempts: 3 }], current_page: 1, last_page: 1 } });
    api.retryDelivery.mockResolvedValue({}); api.retryTransactionalDelivery.mockResolvedValue({}); api.savePolicy.mockResolvedValue({});
    api.fetchStaffAnnouncements.mockResolvedValue({ data: [], current_page: 1, last_page: 1 }); api.previewAudience.mockResolvedValue({ count: 2 });
});
afterEach(() => { wrappers.forEach(w => w.unmount()); wrappers = []; document.body.innerHTML = ''; vi.useRealTimers(); vi.restoreAllMocks(); });
describe('unified notification UI', () => {
    it.each(['en', 'ar'])('renders localized bell, unread badge and a mobile-sized dropdown in %s', async language => {
        setLocale(language); const w = render(NotificationBell); await flushPromises();
        expect(w.attributes('dir')).toBe(language === 'ar' ? 'rtl' : 'ltr'); expect(w.get('button').attributes('aria-label')).toContain('2');
        await w.get('button').trigger('click'); await flushPromises();
        const panel = document.querySelector('#notification-dropdown'); expect(panel.textContent).toContain(item.titles[language]); expect(panel.classList.contains('max-h-[75dvh]')).toBe(true);
        panel.querySelector('button').click(); await flushPromises(); expect(w.get('button').attributes('aria-expanded')).toBe('false');
    });
    it('pauses polling while hidden and clears another user’s unread state at logout', async () => {
        const w = render(NotificationBell); await flushPromises(); api.fetchNotifications.mockClear();
        Object.defineProperty(document, 'hidden', { configurable: true, value: true }); await vi.advanceTimersByTimeAsync(30000); expect(api.fetchNotifications).not.toHaveBeenCalled();
        useAuthStore().clearSession(); await flushPromises(); expect(w.find('button').exists()).toBe(false);
        w.unmount(); wrappers = []; await vi.advanceTimersByTimeAsync(30000); expect(api.fetchNotifications).not.toHaveBeenCalled();
    });
    it('discards late responses from a previous account', async () => {
        let resolve; api.fetchNotifications.mockReturnValueOnce(new Promise(yes => { resolve = yes; }));
        const w = render(NotificationBell); useAuthStore().clearSession(); await flushPromises(); resolve({ data: [item], meta: { unread_count: 90 } }); await flushPromises(); expect(w.find('button').exists()).toBe(false);
    });
    it('authorizes navigation first and never follows an external URL', async () => {
        const w = render(NotificationCenter); await flushPromises(); await w.get('article button').trigger('click'); await flushPromises();
        expect(api.openNotification).toHaveBeenCalledWith(item.id); expect(state.push).toHaveBeenCalledWith('/student/messages?conversation=3');
        state.push.mockClear(); api.openNotification.mockResolvedValue({ path: '//evil.test/path' }); await w.get('article button').trigger('click'); await flushPromises(); expect(state.push).not.toHaveBeenCalled(); expect(w.find('[role=alert]').exists()).toBe(true);
    });
    it('shows inaccessible resource errors without navigating', async () => {
        api.openNotification.mockRejectedValue({ status: 404 }); const w = render(NotificationCenter); await flushPromises(); await w.get('article button').trigger('click'); await flushPromises(); expect(w.text()).toContain('no longer available'); expect(state.push).not.toHaveBeenCalled();
    });
    it('filters, paginates and refreshes counts after marking read', async () => {
        const w = render(NotificationCenter); await flushPromises(); await w.get('select').setValue('messaging'); await w.get('input[type=checkbox]').setValue(true); await flushPromises(); expect(api.fetchNotifications).toHaveBeenLastCalledWith(expect.objectContaining({ unread: 1, category: 'messaging' }));
        await button(w, 'Next').trigger('click'); await flushPromises(); expect(api.fetchNotifications).toHaveBeenLastCalledWith(expect.objectContaining({ page: 2 }));
        await button(w, 'Mark as read').trigger('click'); await flushPromises(); expect(api.readNotification).toHaveBeenCalledWith(item.id);
        await button(w, 'Mark all as read').trigger('click'); await flushPromises(); expect(api.readAllNotifications).toHaveBeenCalledTimes(1);
    });
    it('has empty, loading and recoverable error states', async () => {
        api.fetchNotifications.mockRejectedValueOnce(new Error('network')); const w = render(NotificationCenter); await w.vm.$nextTick(); expect(w.find('[role=status]').exists()).toBe(true); await flushPromises(); expect(w.find('[role=alert]').exists()).toBe(true);
        api.fetchNotifications.mockResolvedValue({ data: [], meta: { current_page: 1, last_page: 1, unread_count: 0 } }); await button(w, 'Retry').trigger('click'); await flushPromises(); expect(w.text()).toContain('no notifications');
    });
    it('keeps mandatory channels enabled and saves only preference fields', async () => {
        const w = render(Preferences); await flushPromises(); expect(w.findAll('input').every(input => input.element.disabled)).toBe(true);
        await w.get('form').trigger('submit'); await flushPromises(); expect(api.savePreferences).toHaveBeenCalledWith([{ type: row.type, in_app: true, email: true, digest: false }]); expect(w.text()).toContain('Preferences saved');
    });
    it.each(['en', 'ar'])('renders announcement content as escaped text and rechecks route changes in %s', async language => {
        setLocale(language); api.fetchAnnouncement.mockResolvedValue({ title: { ar: 'إعلان', en: 'Announcement' }, body: { ar: '<script>نص</script>', en: '<img src=x onerror=alert(1)>' } }); const w = render(Announcement); await flushPromises(); expect(w.text()).toContain(language === 'ar' ? 'نص' : '<img'); expect(w.find('img,script').exists()).toBe(false);
        state.route.params.id = '2'; await flushPromises(); expect(api.fetchAnnouncement).toHaveBeenLastCalledWith('2');
    });
    it('previews role audiences without exposing recipient identities and hides publish controls without permission', async () => {
        useAuthStore().setUser({ id: 1, roles: ['content_manager'], permissions: ['announcements.manage'] }); const w = render(Management); await flushPromises(); await button(w, 'New announcement').trigger('click'); await flushPromises();
        await w.get('select').setValue('roles'); await w.get('input[type=checkbox]').setValue(true); await w.findAll('button').find(b => b.text() === 'Preview audience').trigger('click'); await flushPromises(); expect(api.previewAudience).toHaveBeenCalledWith(expect.objectContaining({ audience: { kind: 'roles', roles: ['student'] } })); expect(w.text()).toContain('2 eligible recipients'); expect(w.get('input[type=datetime-local]').element.disabled).toBe(true); expect(button(w, 'Publish')).toBeUndefined();
    });
    it('edits reactive announcement records without copying proxies into structuredClone', async () => {
        api.fetchStaffAnnouncements.mockResolvedValue({ data: [{ id: 4, title: { ar: 'إعلان', en: 'Title' }, body: { ar: 'نص', en: 'Body' }, audience: { kind: 'all' }, status: 'draft', expires_at: null, publish_at: null }], current_page: 1, last_page: 1 }); const w = render(Management); await flushPromises(); await button(w, 'Edit').trigger('click'); expect(w.get('input').element.value).toBe('إعلان');
    });
    it('monitors and retries both outboxes without displaying recipient details', async () => {
        const w = render(DeliveryManagement); await flushPromises(); expect(w.text()).toContain('RuntimeException'); expect(w.text()).toContain('TransportException'); expect(w.findAll('input').every(i => i.element.disabled)).toBe(true);
        await w.findAll('article button')[0].trigger('click'); await flushPromises(); expect(api.retryDelivery).toHaveBeenCalledWith(1);
        await w.findAll('article button')[1].trigger('click'); await flushPromises(); expect(api.retryTransactionalDelivery).toHaveBeenCalledWith(2);
        await button(w, 'Next').trigger('click'); await flushPromises(); expect(api.fetchManagement).toHaveBeenLastCalledWith(2, 1);
    });
    it.each(['https://evil.test', '//evil.test', '/\\evil.test', null])('rejects unsafe path %s', value => { expect(api.safeNotificationPath(value)).toBe(false); });
});

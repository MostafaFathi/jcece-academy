import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import i18n, { setLocale } from '../i18n';
import { routes } from '../router';
import { canAccessRoute } from '../router/access';
import { visibleNavigation, navigationByArea } from '../composables/navigation';
import VideoLesson from '../components/learning/VideoLesson.vue';
import AdminAuditPage from '../pages/AdminAuditPage.vue';
import { api } from '../api/client';

const state = vi.hoisted(() => ({ route: { query: {} }, push: vi.fn() }));
vi.mock('vue-router', async (original) => ({ ...(await original()), useRoute: () => state.route, useRouter: () => ({ push: state.push }) }));
vi.mock('../api/client', () => ({ api: { post: vi.fn(), get: vi.fn() } }));
const render = (component, props = {}) => mount(component, { props, global: { plugins: [i18n] } });

beforeEach(() => { vi.resetAllMocks(); setLocale('en'); state.route = { query: {} }; });

describe('Phase 15C protected media and audit UI', () => {
    it('requests protected playback only for paid video and never persists the signed URL', async () => {
        api.post.mockResolvedValue({ data: { data: { url: 'https://media.example.test/short-token' } } });
        const storage = vi.spyOn(Storage.prototype, 'setItem');
        const wrapper = render(VideoLesson, { lesson: { id: 7, protected_playback_available: true, video_url: null }, courseSlug: 'first-course' });
        await flushPromises();
        expect(api.post).toHaveBeenCalledWith('/api/v1/me/courses/first-course/lessons/7/protected-playback');
        expect(wrapper.find('video').attributes('src')).toBe('https://media.example.test/short-token');
        expect(storage).not.toHaveBeenCalled();
        wrapper.unmount();

        api.post.mockClear();
        const preview = render(VideoLesson, { lesson: { id: 8, protected_playback_available: false, video_url: 'https://preview.example.test/video.mp4' }, courseSlug: 'first-course' });
        await flushPromises();
        expect(api.post).not.toHaveBeenCalled();
        expect(preview.find('video').attributes('src')).toBe('https://preview.example.test/video.mp4');
        preview.unmount();
        storage.mockRestore();
    });

    it('shows authorization error and allows a fresh request', async () => {
        api.post.mockRejectedValueOnce({ status: 403 }).mockResolvedValueOnce({ data: { data: { url: 'https://media.example.test/new' } } });
        const wrapper = render(VideoLesson, { lesson: { id: 7, protected_playback_available: true, video_url: null }, courseSlug: 'first-course' });
        await flushPromises();
        expect(wrapper.find('[role="alert"]').exists()).toBe(true);
        await wrapper.findAll('button').find((button) => button.text().includes('Refresh')).trigger('click');
        await flushPromises();
        expect(wrapper.find('video').attributes('src')).toBe('https://media.example.test/new');
        wrapper.unmount();
    });

    it('restricts audit navigation to Admin and displays paginated safe rows', async () => {
        const adminRoute = routes.find((route) => route.path === '/admin').children.find((route) => route.name === 'admin.audit.index');
        expect(canAccessRoute({ hasAnyRole: () => false }, adminRoute.meta)).toBe(false);
        expect(visibleNavigation(navigationByArea.admin, { can: () => true, canAny: () => true, hasAnyRole: () => false }).some((item) => item.route === 'admin.audit.index')).toBe(false);
        api.get.mockResolvedValue({ data: { data: [{ id: 1, event_type: 'payment.rejected', subject_type: 'Payment', subject_id: 4, actor: { name: 'Admin' }, metadata: { to_status: 'rejected' }, created_at: '2026-10-04T12:00:00Z' }], current_page: 1, last_page: 2 } });
        const wrapper = render(AdminAuditPage);
        await flushPromises();
        expect(wrapper.text()).toContain('payment.rejected');
        expect(wrapper.text()).toContain('Admin');
        expect(wrapper.text()).toContain('to_status');
        expect(api.get).toHaveBeenCalledWith('/api/v1/admin/audit-events', { params: { page: 1 } });
        wrapper.unmount();
    });
});

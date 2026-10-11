import { afterEach, describe, expect, it, vi } from 'vitest';
import { nextTick, reactive } from 'vue';
import { installRealtime } from '../realtime';
import { api } from '../api/client';

const state = vi.hoisted(() => ({ instances: [], signal: null }));
vi.mock('laravel-echo', () => ({
    default: class {
        constructor(options) { this.options = options; this.disconnect = vi.fn(); this.private = vi.fn(() => ({ listen: (name, handler) => { state.signal = handler; return this; } })); state.instances.push(this); }
    },
}));
vi.mock('pusher-js', () => ({ default: class {} }));
vi.mock('../api/client', () => ({ api: { post: vi.fn() } }));

afterEach(() => { delete globalThis.__JCEC_REALTIME__; delete globalThis.Echo; state.instances.length = 0; state.signal = null; vi.restoreAllMocks(); });

describe('Pusher connection', () => {
    it('stays disabled without a configured public key', () => {
        const auth = reactive({ user: { id: 1 } });
        expect(installRealtime(auth)).toBeUndefined();
        expect(state.instances).toHaveLength(0);
    });

    it('subscribes only the signed-in user and disconnects on account changes', async () => {
        globalThis.__JCEC_REALTIME__ = { key: 'public-key', cluster: 'eu' };
        const auth = reactive({ user: null });
        const changed = vi.fn();
        window.addEventListener('notifications:changed', changed);
        const stop = installRealtime(auth);

        auth.user = { id: 4 };
        await nextTick();
        expect(state.instances[0].private).toHaveBeenCalledWith('user-notifications.4');
        state.signal();
        expect(changed).toHaveBeenCalledTimes(1);

        api.post.mockResolvedValue({ data: { auth: 'signature' } });
        const authorized = vi.fn();
        state.instances[0].options.authorizer({ name: 'private-user-notifications.4' }).authorize('1.1', authorized);
        await Promise.resolve();
        await Promise.resolve();
        expect(api.post).toHaveBeenCalledWith('/api/v1/broadcasting/auth', { socket_id: '1.1', channel_name: 'private-user-notifications.4' });
        expect(authorized).toHaveBeenCalledWith(false, { auth: 'signature' });

        auth.user = null;
        await nextTick();
        expect(state.instances[0].disconnect).toHaveBeenCalledOnce();
        expect(globalThis.Echo).toBeNull();
        stop();
        window.removeEventListener('notifications:changed', changed);
    });
});

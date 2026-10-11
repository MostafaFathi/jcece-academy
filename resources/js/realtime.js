import { watch } from 'vue';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { api } from './api/client';

export function installRealtime(auth) {
    const settings = globalThis.__JCEC_REALTIME__;
    if (!settings?.key || !settings?.cluster) return;

    let connection = null;
    return watch(() => auth.user?.id, (userId) => {
        connection?.disconnect();
        connection = null;
        globalThis.Echo = null;
        if (!userId) return;

        connection = new Echo({
            broadcaster: 'pusher',
            Pusher,
            key: settings.key,
            cluster: settings.cluster,
            forceTLS: true,
            authorizer: (channel) => ({
                authorize: (socketId, callback) => {
                    api.post('/api/v1/broadcasting/auth', { socket_id: socketId, channel_name: channel.name })
                        .then(({ data }) => callback(false, data))
                        .catch((error) => callback(true, error));
                },
            }),
        });
        globalThis.Echo = connection;
        connection.private(`user-notifications.${userId}`)
            .listen('.notification.changed', () => window.dispatchEvent(new Event('notifications:changed')));
    }, { immediate: true });
}

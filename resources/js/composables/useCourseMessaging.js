import { onBeforeUnmount, onMounted, ref } from 'vue';
import * as api from '../api/messaging';
import { useAuthStore } from '../stores/auth';

export function mergeMessages(current, incoming) {
    const byId = new Map(current.map((message) => [message.id ?? message.client_id, message]));
    for (const message of incoming) {
        for (const [key, value] of byId) if (value.client_id === message.client_id && value.user?.id === message.user?.id) byId.delete(key);
        byId.set(message.id ?? message.client_id, message);
    }
    return [...byId.values()].sort((a, b) => (a.id ?? Infinity) - (b.id ?? Infinity));
}

export function useCourseMessaging() {
    const auth = useAuthStore();
    const conversations = ref([]), selected = ref(null), messages = ref([]), error = ref(null), sendError = ref(null), loading = ref(false), sending = ref(false);
    const typingUserIds = ref(new Set());
    const activeSearch = ref('');
    const online = ref(navigator.onLine), hasOlder = ref(false), notificationCount = ref(0), page = ref(1), lastPage = ref(1);
    let listGeneration = 0, generation = 0, echoChannel = null, echoConnection = null, activeChannel = null, channelReady = false, timer, typingIdleTimer, disposed = false, backoff = 5000, filters = {}, version = 0, syncing = false;
    let localTyping = false, lastTypingBroadcastAt = 0;
    const remoteTypingTimers = new Map();
    function clearRemoteTyping() {
        for (const timeout of remoteTypingTimers.values()) clearTimeout(timeout);
        remoteTypingTimers.clear();
        typingUserIds.value = new Set();
    }
    function broadcastTyping(typing) {
        if (!activeChannel || !channelReady || !selected.value?.id || !auth.user?.id || !online.value || document.hidden) return;
        void api.sendTyping(selected.value.id, typing).catch(() => {});
        if (typing) lastTypingBroadcastAt = Date.now();
    }
    function stopTyping() {
        clearTimeout(typingIdleTimer);
        if (localTyping) broadcastTyping(false);
        localTyping = false;
        lastTypingBroadcastAt = 0;
    }
    function setTyping(typing) {
        if (!typing || !selected.value?.can_send || !online.value || document.hidden) { stopTyping(); return; }
        const wasTyping = localTyping;
        localTyping = true;
        if (!wasTyping || Date.now() - lastTypingBroadcastAt >= 2000) broadcastTyping(true);
        clearTimeout(typingIdleTimer);
        typingIdleTimer = setTimeout(stopTyping, 2500);
    }
    function receiveTyping(payload) {
        const userId = Number(payload?.userId);
        if (!Number.isSafeInteger(userId) || userId <= 0 || userId === auth.user?.id || !selected.value || document.hidden || typeof payload.typing !== 'boolean') return;
        clearTimeout(remoteTypingTimers.get(userId));
        remoteTypingTimers.delete(userId);
        const next = new Set(typingUserIds.value);
        if (payload.typing === true) {
            next.add(userId);
            remoteTypingTimers.set(userId, setTimeout(() => {
                remoteTypingTimers.delete(userId);
                typingUserIds.value = new Set([...typingUserIds.value].filter((id) => id !== userId));
            }, 5000));
        } else next.delete(userId);
        typingUserIds.value = next;
    }
    function leaveChannel() {
        stopTyping();
        clearRemoteTyping();
        if (echoChannel && echoConnection) echoConnection.leave(echoChannel);
        echoChannel = null;
        echoConnection = null;
        activeChannel = null;
        channelReady = false;
    }
    async function list(params = filters) {
        const listToken = ++listGeneration;
        filters = params;
        const result = await api.fetchConversations(params);
        if (disposed || listToken !== listGeneration) return;
        conversations.value = result.items;
        page.value = result.meta.current_page ?? 1; lastPage.value = result.meta.last_page ?? 1;
        const notifications = await api.fetchMessageNotifications();
        if (!disposed && listToken === listGeneration) notificationCount.value = notifications.unread_count;
    }
    function releasePresence() { if (selected.value && api.clearNotificationPresence) void api.clearNotificationPresence(selected.value.id).catch(() => {}); }
    async function readVisible() {
        if (!selected.value || activeSearch.value || document.hidden || !online.value) return;
        if (api.setNotificationPresence) await api.setNotificationPresence(selected.value.id).catch(() => {});
        const last = messages.value.filter((message) => message.id).at(-1);
        if (last) await api.markRead(selected.value.id, last.id);
    }
    function close() {
        releasePresence();
        generation++;
        leaveChannel();
        selected.value = null; messages.value = []; loading.value = false;
        activeSearch.value = ''; hasOlder.value = false; sendError.value = null;
        version = 0;
    }
    async function select(conversation) {
        releasePresence();
        leaveChannel();
        const token = ++generation; selected.value = conversation; messages.value = []; error.value = null; sendError.value = null; loading.value = true;
        version = 0; activeSearch.value = '';
        try {
            const detail = await api.fetchConversation(conversation.id);
            const result = await api.fetchMessages(conversation.id);
            if (token !== generation || disposed) return;
            selected.value = detail; messages.value = mergeMessages([], result.items); hasOlder.value = result.items.length === 50;
            version = detail.version ?? 0;
            await readVisible();
            if (token !== generation || disposed) return;
            if (globalThis.Echo) {
                echoChannel = `course-conversation.${conversation.id}`;
                echoConnection = globalThis.Echo;
                activeChannel = echoConnection.private(echoChannel);
                const channel = activeChannel;
                channel.listen('.conversation.changed', () => { if (activeChannel === channel) void sync(); })
                    .listen('.conversation.typing', (payload) => { if (activeChannel === channel) receiveTyping(payload); })
                    .listenForWhisper('typing', (payload) => { if (activeChannel === channel) receiveTyping(payload); })
                    .subscribed(() => { if (activeChannel !== channel) return; channelReady = true; if (localTyping) broadcastTyping(true); })
                    .error(() => { if (activeChannel !== channel) return; channelReady = false; error.value = { status: 0 }; });
            }
        } catch (failure) {
            if (token === generation) { error.value = failure; if ([403, 404].includes(failure.status)) { selected.value = null; messages.value = []; } }
        } finally { if (token === generation) loading.value = false; }
    }
    async function searchMessages(search = '') {
        if (!selected.value) return;
        const id = selected.value.id, token = generation;
        activeSearch.value = search.trim();
        const query = activeSearch.value;
        const result = await api.fetchMessages(id, { search: query || undefined });
        if (token !== generation || query !== activeSearch.value || disposed) return;
        messages.value = mergeMessages([], result.items); hasOlder.value = result.items.length === 50;
    }
    async function older(search = '') {
        if (!selected.value || loading.value) return;
        const id = selected.value.id, token = generation;
        const first = messages.value.filter((message) => message.id).at(0);
        const result = await api.fetchMessages(id, { before: first?.id, search: search || undefined });
        if (token !== generation) return;
        messages.value = mergeMessages(messages.value, result.items); hasOlder.value = result.items.length === 50;
    }
    async function sync() {
        if (disposed || syncing || document.hidden || !online.value) return;
        syncing = true;
        const token = generation, id = selected.value?.id;
        try {
            await list();
            if (id && token === generation && !loading.value) {
                const result = await api.fetchEvents(id, version);
                if (token !== generation) return;
                if (result.version > version) {
                    const query = activeSearch.value;
                    const latest = await api.fetchMessages(id, { search: query || undefined });
                    const changedIds = [...new Set(result.data.filter((event) => ['message', 'deleted', 'reaction'].includes(event.kind)).map((event) => event.subject_id))];
                    const changed = activeSearch.value ? [] : await Promise.all(changedIds.map(api.fetchMessage));
                    const detail = await api.fetchConversation(id);
                    if (token !== generation || disposed || query !== activeSearch.value) return;
                    messages.value = mergeMessages(activeSearch.value ? [] : messages.value, [...latest.items, ...changed]); selected.value = detail;
                    version = result.data.at(-1)?.version ?? version;
                }
                await readVisible();
            }
            error.value = null; backoff = 5000;
        } catch (failure) {
            if (token === generation) {
                error.value = failure;
                if ([403, 404].includes(failure.status)) { leaveChannel(); selected.value = null; messages.value = []; conversations.value = []; notificationCount.value = 0; }
            }
            backoff = Math.min(backoff * 2, 30000);
        } finally { syncing = false; }
    }
    async function loop() { clearTimeout(timer); await sync(); if (!disposed) { clearTimeout(timer); timer = setTimeout(loop, backoff); } }
    function resume() { if (document.hidden || !navigator.onLine) { releasePresence(); stopTyping(); clearRemoteTyping(); } online.value = navigator.onLine; if (!document.hidden && online.value) { clearTimeout(timer); loop(); } }
    async function send(payload, attachment, user, quotedMessage = null) {
        if (!selected.value || sending.value) return false;
        stopTyping();
        const token = generation, id = selected.value.id;
        sending.value = true; error.value = null; sendError.value = null;
        messages.value = mergeMessages(messages.value, [{ ...payload, id: null, user, pending: true, created_at: new Date().toISOString(), reply: quotedMessage, attachments: [], reactions: [], attachment_name: attachment?.name ?? null, attachment_kind: attachment ? (attachment.type.startsWith('image/') ? 'image' : 'voice') : null }]);
        try {
            const posted = await api.sendMessage(id, payload, attachment);
            if (token === generation) {
                messages.value = mergeMessages(messages.value, [posted]);
                void readVisible().catch(() => {});
                void sync();
            }
            return true;
        } catch (failure) {
            if (token === generation) { error.value = failure; sendError.value = failure; messages.value = messages.value.map((message) => message.client_id === payload.client_id ? { ...message, pending: false, failed: true } : message); }
            return false;
        } finally { sending.value = false; }
    }
    onMounted(() => { document.addEventListener('visibilitychange', resume); window.addEventListener('online', resume); window.addEventListener('offline', resume); loop(); });
    onBeforeUnmount(() => { releasePresence(); disposed = true; generation++; leaveChannel(); clearTimeout(timer); document.removeEventListener('visibilitychange', resume); window.removeEventListener('online', resume); window.removeEventListener('offline', resume); });
    return { conversations, selected, messages, typingUserIds, error, sendError, loading, sending, online, hasOlder, notificationCount, page, lastPage, list, select, close, older, searchMessages, sync, send, setTyping };
}

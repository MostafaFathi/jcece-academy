import { onBeforeUnmount, onMounted, ref } from 'vue';
import * as api from '../api/messaging';

export function mergeMessages(current, incoming) {
    const byId = new Map(current.map((message) => [message.id ?? message.client_id, message]));
    for (const message of incoming) {
        for (const [key, value] of byId) if (value.client_id === message.client_id && value.user?.id === message.user?.id) byId.delete(key);
        byId.set(message.id ?? message.client_id, message);
    }
    return [...byId.values()].sort((a, b) => (a.id ?? Infinity) - (b.id ?? Infinity));
}

export function useCourseMessaging() {
    const conversations = ref([]), selected = ref(null), messages = ref([]), error = ref(null), loading = ref(false), sending = ref(false);
    const activeSearch = ref('');
    const online = ref(navigator.onLine), hasOlder = ref(false), notificationCount = ref(0), page = ref(1), lastPage = ref(1);
    let listGeneration = 0, generation = 0, echoChannel = null, timer, disposed = false, backoff = 5000, filters = {}, version = 0, syncing = false;
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
    async function readVisible() {
        if (!selected.value || activeSearch.value || document.hidden || !online.value) return;
        const last = messages.value.filter((message) => message.id).at(-1);
        if (last) await api.markRead(selected.value.id, last.id);
    }
    async function select(conversation) {
        if (echoChannel && globalThis.Echo) globalThis.Echo.leave(echoChannel);
        echoChannel = null;
        const token = ++generation; selected.value = conversation; messages.value = []; error.value = null; loading.value = true;
        version = 0; activeSearch.value = '';
        try {
            const detail = await api.fetchConversation(conversation.id);
            const result = await api.fetchMessages(conversation.id);
            if (token !== generation || disposed) return;
            selected.value = detail; messages.value = mergeMessages([], result.items); hasOlder.value = result.items.length === 50;
            version = detail.version ?? 0;
            await readVisible();
            if (globalThis.Echo) {
                echoChannel = `course-conversation.${conversation.id}`;
                globalThis.Echo.private(echoChannel).listen('.conversation.changed', () => sync()).error(() => { error.value = { status: 0 }; });
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
                if ([403, 404].includes(failure.status)) { selected.value = null; messages.value = []; conversations.value = []; notificationCount.value = 0; }
            }
            backoff = Math.min(backoff * 2, 30000);
        } finally { syncing = false; }
    }
    async function loop() { clearTimeout(timer); await sync(); if (!disposed) { clearTimeout(timer); timer = setTimeout(loop, backoff); } }
    function resume() { online.value = navigator.onLine; if (!document.hidden && online.value) { clearTimeout(timer); loop(); } }
    async function send(payload, attachment, user) {
        if (!selected.value || sending.value) return false;
        const token = generation, id = selected.value.id;
        sending.value = true; error.value = null;
        messages.value = mergeMessages(messages.value, [{ ...payload, id: null, user, pending: true, attachments: [], reactions: [] }]);
        try {
            const posted = await api.sendMessage(id, payload, attachment);
            if (token === generation) { messages.value = mergeMessages(messages.value, [posted]); await readVisible(); await sync(); }
            return true;
        } catch (failure) {
            if (token === generation) { error.value = failure; messages.value = messages.value.map((message) => message.client_id === payload.client_id ? { ...message, pending: false, failed: true } : message); }
            return false;
        } finally { sending.value = false; }
    }
    onMounted(() => { document.addEventListener('visibilitychange', resume); window.addEventListener('online', resume); window.addEventListener('offline', resume); loop(); });
    onBeforeUnmount(() => { disposed = true; generation++; if (echoChannel && globalThis.Echo) globalThis.Echo.leave(echoChannel); clearTimeout(timer); document.removeEventListener('visibilitychange', resume); window.removeEventListener('online', resume); window.removeEventListener('offline', resume); });
    return { conversations, selected, messages, error, loading, sending, online, hasOlder, notificationCount, page, lastPage, list, select, older, searchMessages, sync, send };
}

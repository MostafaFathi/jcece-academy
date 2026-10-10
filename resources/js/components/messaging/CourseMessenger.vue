<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../../stores/auth';
import { useCourseMessaging } from '../../composables/useCourseMessaging';
import * as api from '../../api/messaging';
import GroupWizard from './GroupWizard.vue';
import MessageComposer from './MessageComposer.vue';
import MessagingIcon from './MessagingIcon.vue';

const props = defineProps({ courseId: { type: [Number, String], default: null } });
const { t, locale } = useI18n();
const auth = useAuthStore();
const chat = useCourseMessaging();
const { conversations, selected, messages, error, sendError, loading, sending, online, hasOlder, notificationCount, page, lastPage } = chat;
const courses = ref([]);
const courseFilter = ref(props.courseId ?? '');
const search = ref('');
const unread = ref(false);
const wizard = ref(null);
const reply = ref(null);
const deleteId = ref(null);
const historySearch = ref('');
const historyMode = ref(false);
const listBusy = ref(false);
const historyBusy = ref(false);
const feed = ref(null);
const composer = ref(null);
const deleteDialog = ref(null);
const reactionMenu = ref(null);
const reactionMenuElement = ref(null);
const reactionOverrides = ref(new Map());
const pendingReactionIds = ref(new Set());
const clock = ref(Date.now());
let reactionTrigger = null;
let clockInterval = null;
const emoji = ['👍', '❤️', '😂', '🎉', '😮', '🙏'];
const selectedCourse = computed(() => courses.value.find((course) => String(course.id) === String(courseFilter.value)));

watch(deleteId, async (id) => {
    await nextTick();
    if (id) deleteDialog.value?.querySelector('button')?.focus();
    else composer.value?.focus();
});

async function beginReply(message) {
    reply.value = message;
    await nextTick();
    composer.value?.focus();
}

function closeReactionMenu(restoreFocus = false) {
    reactionMenu.value = null;
    if (restoreFocus && reactionTrigger?.isConnected) reactionTrigger.focus();
    reactionTrigger = null;
}

function dismissReactionMenu() { closeReactionMenu(); }

function canOpenMessageActions(message) {
    return message.id && !message.deleted && !historyMode.value && (selected.value?.can_send || message.can_delete);
}

async function showMessageMenu(message, mode, anchor) {
    const width = Math.min(mode === 'reactions' ? 288 : 160, window.innerWidth - 16);
    const columns = Math.max(1, Math.floor((width - 10) / 46));
    const height = mode === 'reactions' ? Math.ceil(emoji.length / columns) * 46 + 10 : 58;
    const bounds = anchor.getBoundingClientRect();
    const left = Math.min(Math.max(bounds.left + bounds.width / 2 - width / 2, 8), window.innerWidth - width - 8);
    const top = bounds.top >= height + 8 ? bounds.top - height - 8 : Math.max(8, Math.min(bounds.bottom + 8, window.innerHeight - height - 8));
    reactionMenu.value = { message, mode, top, left, width };
    await nextTick();
    reactionMenuElement.value?.querySelector('button')?.focus({ preventScroll: true });
}

async function openMessageActions(message, event) {
    if (!canOpenMessageActions(message) || event.target.closest('a, button, audio, video, input, textarea, select, summary')) return;
    if (reactionMenu.value?.message.id === message.id && reactionMenu.value.mode === 'actions') { closeReactionMenu(true); return; }
    reactionTrigger = event.currentTarget;
    await showMessageMenu(message, 'actions', reactionTrigger);
}

function onMessageKeydown(message, event) {
    if (event.target !== event.currentTarget || !['Enter', ' '].includes(event.key)) return;
    event.preventDefault();
    void openMessageActions(message, event);
}

async function openReactionChoices() {
    const currentMenu = reactionMenu.value;
    if (!currentMenu) return;
    const width = Math.min(288, window.innerWidth - 16);
    const columns = Math.max(1, Math.floor((width - 10) / 46));
    const height = Math.ceil(emoji.length / columns) * 46 + 10;
    reactionMenu.value = {
        ...currentMenu,
        mode: 'reactions',
        width,
        left: Math.min(currentMenu.left, window.innerWidth - width - 8),
        top: Math.min(currentMenu.top, window.innerHeight - height - 8),
    };
    await nextTick();
    reactionMenuElement.value?.querySelector('button')?.focus({ preventScroll: true });
}

function chooseReply() {
    const message = reactionMenu.value?.message;
    closeReactionMenu();
    if (message) void beginReply(message);
}

function chooseDelete() {
    const message = reactionMenu.value?.message;
    closeReactionMenu();
    if (message) deleteId.value = message.id;
}

function closeReactionMenuOnOutside(event) {
    if (reactionMenu.value && !reactionMenuElement.value?.contains(event.target) && !reactionTrigger?.contains(event.target)) closeReactionMenu();
}

async function chooseReaction(reaction) {
    const message = reactionMenu.value?.message;
    closeReactionMenu(true);
    if (message) await react(message, reaction);
}

function visibleReactions(message) { return reactionOverrides.value.get(message.id) ?? message.reactions ?? []; }

function toggledReactions(reactions, emoji) {
    const existing = reactions.find((reaction) => reaction.emoji === emoji);
    if (!existing) return [...reactions, { emoji, count: 1, mine: true }];
    if (!existing.mine) return reactions.map((reaction) => reaction.emoji === emoji ? { ...reaction, count: reaction.count + 1, mine: true } : reaction);
    return reactions.map((reaction) => reaction.emoji === emoji ? { ...reaction, count: reaction.count - 1, mine: false } : reaction).filter((reaction) => reaction.count > 0);
}

function relativeTime(value) {
    const createdAt = Date.parse(value);
    if (!Number.isFinite(createdAt)) return value;
    const elapsed = Math.round((createdAt - clock.value) / 1000);
    const units = [['year', 31536000], ['month', 2592000], ['week', 604800], ['day', 86400], ['hour', 3600], ['minute', 60]];
    const [unit, seconds] = units.find(([, duration]) => Math.abs(elapsed) >= duration) ?? ['second', 1];
    return new Intl.RelativeTimeFormat(locale.value, { numeric: 'auto' }).format(Math.round(elapsed / seconds), unit);
}

function fullTime(value) {
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? value : date.toLocaleString(locale.value, { dateStyle: 'full', timeStyle: 'short' });
}

function messageSegments(value) {
    if (typeof Intl.Segmenter !== 'function') return [{ text: value, emoji: false }];
    const graphemes = new Intl.Segmenter(locale.value, { granularity: 'grapheme' }).segment(value);
    const segments = [];
    for (const { segment } of graphemes) {
        const isEmoji = /[\p{Extended_Pictographic}\p{Regional_Indicator}]/u.test(segment);
        if (!isEmoji && segments.length && !segments.at(-1).emoji) segments.at(-1).text += segment;
        else segments.push({ text: segment, emoji: isEmoji });
    }
    return segments;
}

function replyAttachment(message) {
    return message?.attachment ?? message?.attachments?.[0] ?? null;
}

onMounted(() => {
    clockInterval = window.setInterval(() => { clock.value = Date.now(); }, 60000);
    document.addEventListener('pointerdown', closeReactionMenuOnOutside);
    window.addEventListener('scroll', dismissReactionMenu, true);
    window.addEventListener('resize', dismissReactionMenu);
});
onBeforeUnmount(() => {
    window.clearInterval(clockInterval);
    document.removeEventListener('pointerdown', closeReactionMenuOnOutside);
    window.removeEventListener('scroll', dismissReactionMenu, true);
    window.removeEventListener('resize', dismissReactionMenu);
});
watch(selected, dismissReactionMenu);

async function load(pageNumber = 1) {
    if (listBusy.value) return;
    listBusy.value = true;
    try { await chat.list({ course_id: courseFilter.value || undefined, search: search.value || undefined, unread: unread.value ? 1 : undefined, page: pageNumber }); }
    catch (failure) { error.value = failure; }
    finally { listBusy.value = false; }
}

watch(() => props.courseId, async (id) => {
    courseFilter.value = id ?? '';
    try { courses.value = await api.fetchMessagingCourses(); await load(); }
    catch (failure) { error.value = failure; }
}, { immediate: true });
watch(courseFilter, () => { wizard.value = null; load(); });

async function select(conversation) {
    if (sending.value) return;
    closeReactionMenu();
    reply.value = null;
    deleteId.value = null;
    historyMode.value = false;
    historySearch.value = '';
    await chat.select(conversation);
    await nextTick();
    if (feed.value) feed.value.scrollTop = feed.value.scrollHeight;
}

async function manage() {
    courseFilter.value = selected.value.course.id;
    await nextTick();
    wizard.value = 'members';
}

async function start() {
    if (!selectedCourse.value) return;
    if (selectedCourse.value.is_instructor) { wizard.value = 'private'; return; }
    try { const conversation = await api.startPrivate(selectedCourse.value.id); await load(); await select(conversation); }
    catch (failure) { error.value = failure; }
}

async function done(conversation) { wizard.value = null; await load(); await select(conversation); }
async function send({ payload, attachment, acknowledge }) {
    if (await chat.send(payload, attachment, auth.user)) {
        acknowledge();
        await nextTick();
        if (feed.value) feed.value.scrollTop = feed.value.scrollHeight;
    }
}
async function react(message, reaction) {
    if (!selected.value?.can_send || pendingReactionIds.value.has(message.id)) return;
    const conversationId = selected.value.id;
    const current = messages.value.find((item) => item.id === message.id) ?? message;
    const updated = toggledReactions(visibleReactions(current), reaction);
    reactionOverrides.value = new Map(reactionOverrides.value).set(message.id, updated);
    pendingReactionIds.value = new Set([...pendingReactionIds.value, message.id]);
    await nextTick();
    try {
        await api.reactToMessage(message.id, reaction);
        if (selected.value?.id === conversationId) {
            messages.value = messages.value.map((item) => item.id === message.id ? { ...item, reactions: updated } : item);
            void chat.sync();
        }
    } catch (failure) {
        if (selected.value?.id === conversationId) error.value = failure;
    } finally {
        const overrides = new Map(reactionOverrides.value);
        overrides.delete(message.id);
        reactionOverrides.value = overrides;
        const pending = new Set(pendingReactionIds.value);
        pending.delete(message.id);
        pendingReactionIds.value = pending;
    }
}
async function remove() { try { await api.deleteMessage(deleteId.value); deleteId.value = null; await chat.sync(); } catch (failure) { error.value = failure; } }
async function searchHistory() {
    if (!selected.value || historyBusy.value) return;
    closeReactionMenu();
    historyBusy.value = true;
    try { await chat.searchMessages(historySearch.value); historyMode.value = !!historySearch.value.trim(); }
    catch (failure) { error.value = failure; }
    finally { historyBusy.value = false; }
}
</script>

<template>
    <section class="min-w-0 space-y-5" :dir="locale === 'ar' ? 'rtl' : 'ltr'" :aria-label="t('messaging.title')">
        <header class="relative overflow-hidden rounded-[1.75rem] bg-[linear-gradient(120deg,#32131f,#6b1d32)] px-5 py-6 text-white shadow-[0_18px_45px_rgba(64,23,37,.14)] sm:px-7">
            <div class="pointer-events-none absolute -end-12 -top-20 size-56 rounded-full border border-white/10" aria-hidden="true" />
            <div class="relative flex flex-wrap items-center justify-between gap-4">
                <div class="flex min-w-0 items-center gap-4">
                    <span class="grid size-12 shrink-0 place-items-center rounded-2xl border border-white/15 bg-white/10 text-accent"><MessagingIcon name="messages" class="size-6" /></span>
                    <div class="min-w-0"><p class="text-xs font-bold tracking-wide text-white/60">{{ t('messaging.inboxLabel') }}</p><h2 class="text-2xl font-black tracking-tight sm:text-3xl">{{ t('messaging.title') }}</h2></div>
                </div>
                <div class="flex flex-wrap items-center gap-2 text-xs font-bold">
                    <span class="rounded-full bg-white/10 px-3 py-2 text-white">{{ t('messaging.unreadCount', { count: notificationCount }) }}</span>
                    <span class="inline-flex items-center gap-2 rounded-full border border-white/15 px-3 py-2 text-white/80"><span class="size-2 rounded-full" :class="online ? 'bg-emerald-400' : 'bg-amber-300'" />{{ online ? t('messaging.polling') : t('messaging.offlineStatus') }}</span>
                </div>
            </div>
        </header>

        <div v-if="!online" role="status" class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-950">{{ t('messaging.offline') }}</div>
        <div v-if="error" role="alert" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
            <p>{{ error.errors?.attachment?.[0] ?? error.errors?.body?.[0] ?? t([403, 404].includes(error.status) ? 'messaging.denied' : 'messaging.error') }}</p>
            <button type="button" class="min-h-11 rounded-xl border border-red-200 bg-white px-4 font-bold transition hover:bg-red-100 focus-visible:outline-3 focus-visible:outline-red-600" @click="chat.sync(); load()">{{ t('messaging.retry') }}</button>
        </div>

        <div class="grid min-w-0 gap-4 xl:grid-cols-[minmax(18rem,21rem)_minmax(0,1fr)]">
            <aside class="flex min-w-0 flex-col gap-4 overflow-hidden rounded-[1.5rem] border border-slate-200 bg-white shadow-[0_8px_28px_rgba(40,20,28,.04)]">
                <div class="space-y-4 border-b border-slate-100 p-4 sm:p-5">
                    <div class="flex items-center justify-between gap-3"><h3 class="text-base font-black text-slate-950">{{ t('messaging.conversations') }}</h3><span class="rounded-full bg-brand-soft px-2.5 py-1 text-xs font-black text-brand">{{ conversations.length }}</span></div>
                    <label v-if="!courseId" class="block text-xs font-bold text-slate-600">{{ t('messaging.course') }}<select v-model="courseFilter" class="mt-1.5 min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900"><option value="">{{ t('messaging.allCourses') }}</option><option v-for="course in courses" :key="course.id" :value="course.id">{{ course.title }}</option></select></label>
                    <form class="flex min-w-0 gap-2" @submit.prevent="load()"><label class="sr-only" for="conversation-search">{{ t('messaging.search') }}</label><div class="relative min-w-0 flex-1"><MessagingIcon name="search" class="pointer-events-none absolute start-3 top-3.5 size-4 text-slate-400" /><input id="conversation-search" v-model="search" :placeholder="t('messaging.search')" maxlength="120" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white pe-3 ps-10 text-sm"></div><button class="min-h-11 rounded-xl border border-slate-200 px-3 text-sm font-bold text-brand transition hover:bg-brand-soft focus-visible:outline-3 focus-visible:outline-brand" type="submit">{{ t('messaging.search') }}</button></form>
                    <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-xl px-2 text-sm font-semibold text-slate-600 hover:bg-slate-50"><input v-model="unread" type="checkbox" class="size-4 accent-brand" @change="load()">{{ t('messaging.unreadOnly') }}</label>
                    <div v-if="selectedCourse" class="flex flex-wrap gap-2"><button v-if="auth.can('messaging.send')" type="button" class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-brand px-3 text-xs font-extrabold text-white transition hover:bg-brand-dark focus-visible:outline-3 focus-visible:outline-brand disabled:opacity-50" :disabled="sending" @click="start"><MessagingIcon name="plus" class="size-4" />{{ t('messaging.startPrivate') }}</button><button v-if="selectedCourse.can_manage" type="button" class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-slate-200 px-3 text-xs font-bold text-brand transition hover:bg-brand-soft focus-visible:outline-3 focus-visible:outline-brand" @click="wizard = 'group'"><MessagingIcon name="users" class="size-4" />{{ t('messaging.newGroup') }}</button></div>
                    <p v-else-if="!courseId" class="text-xs leading-5 text-slate-500">{{ t('messaging.chooseCourse') }}</p>
                </div>
                <p v-if="listBusy" role="status" class="px-5 text-sm text-slate-500">{{ t('messaging.loading') }}</p>
                <div v-else-if="!conversations.length" class="grid place-items-center gap-2 px-5 py-12 text-center"><span class="grid size-12 place-items-center rounded-2xl bg-brand-soft text-brand"><MessagingIcon name="messages" class="size-6" /></span><p class="text-sm font-semibold text-slate-600">{{ t('messaging.empty') }}</p></div>
                <nav class="max-h-80 space-y-1.5 overflow-y-auto px-2 pb-3 xl:max-h-[42rem]" :aria-label="t('messaging.conversations')">
                    <button v-for="conversation in conversations" :key="conversation.id" type="button" class="group flex min-h-20 w-full items-center gap-3 rounded-2xl border px-3 py-3 text-start transition focus-visible:outline-3 focus-visible:outline-brand disabled:opacity-60" :class="selected?.id === conversation.id ? 'border-brand/25 bg-brand-soft shadow-[inset_3px_0_0_#6b1d32]' : 'border-transparent hover:border-slate-200 hover:bg-slate-50'" :aria-pressed="selected?.id === conversation.id" :disabled="sending" @click="select(conversation)">
                        <span class="grid size-11 shrink-0 place-items-center rounded-xl" :class="conversation.kind === 'private' ? 'bg-slate-100 text-brand' : 'bg-amber-50 text-amber-800'"><MessagingIcon :name="conversation.kind === 'private' ? 'user' : 'users'" class="size-5" /></span>
                        <span class="min-w-0 flex-1"><span class="block truncate text-sm font-extrabold text-slate-900">{{ conversation.title }}</span><span class="mt-0.5 block truncate text-xs text-slate-500">{{ conversation.course.title }}</span><span class="mt-1 block text-[11px] font-bold text-slate-500">{{ t(`messaging.kind.${conversation.kind}`) }}</span></span>
                        <span v-if="conversation.unread_count" class="grid min-w-6 shrink-0 place-items-center rounded-full bg-brand px-1.5 py-1 text-xs font-black text-white" :aria-label="t('messaging.unreadCount', { count: conversation.unread_count })">{{ conversation.unread_count }}</span>
                    </button>
                </nav>
                <div v-if="lastPage > 1" class="flex items-center justify-between gap-2 border-t border-slate-100 px-4 py-2 text-xs font-bold text-slate-600"><button type="button" class="min-h-11 px-2 text-brand disabled:text-slate-400" :disabled="page <= 1 || listBusy" @click="load(page - 1)">{{ t('messaging.previous') }}</button><span>{{ page }}/{{ lastPage }}</span><button type="button" class="min-h-11 px-2 text-brand disabled:text-slate-400" :disabled="page >= lastPage || listBusy" @click="load(page + 1)">{{ t('messaging.next') }}</button></div>
            </aside>

            <div class="min-w-0 space-y-4">
                <GroupWizard v-if="wizard && selectedCourse" :key="`${wizard}-${selected?.id ?? 0}`" :course="selectedCourse" :mode="wizard" :conversation="wizard === 'members' ? selected : null" @done="done" @cancel="wizard = null" />
                <section v-if="selected" class="flex min-w-0 flex-col overflow-hidden rounded-[1.5rem] border border-slate-200 bg-white shadow-[0_8px_28px_rgba(40,20,28,.04)]">
                    <header class="space-y-4 border-b border-slate-100 bg-white p-4 sm:p-5">
                        <div class="flex flex-wrap items-center justify-between gap-3"><div class="flex min-w-0 items-center gap-3"><span class="grid size-11 shrink-0 place-items-center rounded-xl bg-brand-soft text-brand"><MessagingIcon :name="selected.kind === 'private' ? 'user' : 'users'" class="size-5" /></span><div class="min-w-0"><h3 class="truncate text-base font-black text-slate-950">{{ selected.title }}</h3><p class="flex min-w-0 items-center gap-1.5 truncate text-xs font-semibold text-slate-500"><MessagingIcon name="book" class="size-3.5 shrink-0" />{{ selected.course.title }} · {{ t(`messaging.kind.${selected.kind}`) }}</p></div></div><button v-if="selected.can_manage && selected.kind === 'selected'" type="button" class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-slate-200 px-3 text-sm font-bold text-brand transition hover:bg-brand-soft focus-visible:outline-3 focus-visible:outline-brand" @click="manage"><MessagingIcon name="users" class="size-4" />{{ t('messaging.manageMembers') }}</button></div>
                        <form class="flex min-w-0 gap-2" @submit.prevent="searchHistory"><label class="sr-only" for="message-search">{{ t('messaging.searchMessages') }}</label><div class="relative min-w-0 flex-1"><MessagingIcon name="search" class="pointer-events-none absolute start-3 top-3.5 size-4 text-slate-400" /><input id="message-search" v-model="historySearch" maxlength="120" :placeholder="t('messaging.searchMessages')" class="min-h-11 w-full rounded-xl border border-slate-300 pe-3 ps-10 text-sm"></div><button type="submit" class="min-h-11 rounded-xl border border-slate-200 px-3 text-sm font-bold text-brand transition hover:bg-brand-soft focus-visible:outline-3 focus-visible:outline-brand disabled:opacity-50" :disabled="historyBusy">{{ t('messaging.search') }}</button><button v-if="historyMode" type="button" class="grid size-11 shrink-0 place-items-center rounded-xl border border-slate-200 text-slate-600 transition hover:bg-slate-50" :aria-label="t('messaging.clearSearch')" @click="historySearch = ''; searchHistory()"><MessagingIcon name="close" class="size-4" /></button></form>
                    </header>
                    <p v-if="historyMode" class="border-b border-amber-100 bg-amber-50 px-4 py-2 text-xs font-bold text-amber-950">{{ t('messaging.searchResults') }}</p>
                    <p v-if="loading" role="status" class="p-4 text-sm text-slate-500">{{ t('messaging.loading') }}</p>
                    <div ref="feed" class="min-h-56 max-h-[34rem] space-y-4 overflow-y-auto bg-[#fbf9fa] p-4 sm:p-6" tabindex="0" :aria-label="t('messaging.history')">
                        <button v-if="hasOlder" type="button" class="min-h-11 w-full rounded-xl border border-slate-200 bg-white text-sm font-bold text-brand transition hover:bg-brand-soft" @click="chat.older(historyMode ? historySearch : '')">{{ t('messaging.older') }}</button>
                        <div v-if="!loading && !messages.length" class="grid place-items-center gap-3 py-16 text-center text-slate-500"><span class="grid size-14 place-items-center rounded-2xl bg-white text-brand shadow-sm"><MessagingIcon name="messages" class="size-7" /></span><p class="text-sm font-semibold">{{ t('messaging.noMessages') }}</p></div>
                        <div v-for="message in messages" :key="message.id ?? message.client_id" dir="ltr" class="flex min-w-0" :class="message.user.id === auth.user?.id ? 'justify-end' : 'justify-start'">
                            <div class="min-w-0 max-w-[min(100%,42rem)]">
                                <article :dir="locale === 'ar' ? 'rtl' : 'ltr'" class="min-w-0 space-y-2 rounded-2xl border p-3.5 shadow-[0_3px_12px_rgba(25,14,20,.04)] sm:p-4" :class="[message.user.id === auth.user?.id ? 'border-brand/15 bg-brand-soft' : 'border-slate-200 bg-white', canOpenMessageActions(message) ? 'cursor-pointer transition hover:border-brand/35 focus-visible:outline-3 focus-visible:outline-brand' : '']" :tabindex="canOpenMessageActions(message) ? 0 : undefined" :aria-label="canOpenMessageActions(message) ? `${message.user.name}: ${message.body || t('messaging.attachment')}. ${t('messaging.messageActionsHint')}` : undefined" :aria-expanded="reactionMenu?.message.id === message.id" @click="openMessageActions(message, $event)" @keydown="onMessageKeydown(message, $event)">
                                <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 text-xs"><span class="font-extrabold text-brand">{{ message.user.name }}</span><time v-if="message.created_at" :datetime="message.created_at" :title="fullTime(message.created_at)" :aria-label="fullTime(message.created_at)" class="text-slate-500">{{ relativeTime(message.created_at) }}</time></div>
                                <blockquote v-if="message.reply" class="rounded-lg border-s-2 border-brand bg-white/70 px-3 py-2 text-xs leading-5 text-slate-600">
                                    <div class="flex min-w-0 items-center gap-2">
                                        <img v-if="!message.reply.deleted && replyAttachment(message.reply)?.kind === 'image'" :src="replyAttachment(message.reply).url" :alt="replyAttachment(message.reply).name" loading="lazy" class="size-12 shrink-0 rounded-lg object-cover">
                                        <span v-else-if="!message.reply.deleted && replyAttachment(message.reply)?.kind === 'voice'" class="grid size-12 shrink-0 place-items-center rounded-lg bg-brand-soft text-brand"><MessagingIcon name="microphone" class="size-5" /></span>
                                        <span class="min-w-0 truncate">{{ message.reply.deleted ? t('messaging.deleted') : message.reply.body || replyAttachment(message.reply)?.name || t('messaging.attachment') }}</span>
                                    </div>
                                </blockquote>
                                <p v-if="message.deleted || message.body" class="whitespace-pre-wrap break-words text-sm leading-8 text-slate-900" :class="message.deleted ? 'italic text-slate-500' : ''"><template v-for="(part, index) in messageSegments(message.deleted ? t('messaging.deleted') : message.body)" :key="index"><span :class="part.emoji ? 'inline-block align-middle text-[1.65rem] leading-none' : ''">{{ part.text }}</span></template></p>
                                <div v-for="attachment in message.attachments" :key="attachment.id" class="min-w-0"><a v-if="attachment.kind === 'image'" :href="attachment.url" target="_blank" rel="noopener"><img :src="attachment.url" :alt="attachment.name" loading="lazy" class="max-h-64 max-w-full rounded-xl border border-slate-200"></a><div v-else><audio :src="attachment.url" controls preload="none" class="max-w-full" /><p class="text-xs text-slate-500">{{ Math.round(attachment.duration) }}s</p></div><a :href="attachment.url" :download="attachment.name" class="inline-flex min-h-11 max-w-full items-center gap-1.5 break-all text-xs font-bold text-brand underline underline-offset-2"><MessagingIcon name="download" class="size-4 shrink-0" />{{ t('messaging.download') }}: {{ attachment.name }}</a></div>
                                <div class="flex flex-wrap items-center gap-2 text-xs"><span v-if="message.pending" role="status" class="font-bold text-amber-800">{{ t('messaging.sending') }}</span><span v-if="message.failed" role="status" class="font-bold text-red-700">{{ t('messaging.failedRetry') }}</span><span v-if="message.user.id === auth.user?.id && message.id" class="inline-flex items-center gap-1 text-slate-500"><MessagingIcon name="check" class="size-3.5" />{{ t('messaging.readers', { count: message.reader_count ?? 0 }) }}</span></div>
                                </article>
                                <div v-if="!message.deleted && visibleReactions(message).length" class="relative z-10 -mt-2 flex flex-wrap gap-1 px-3" :class="message.user.id === auth.user?.id ? 'justify-end' : 'justify-start'">
                                    <button v-for="reaction in visibleReactions(message)" :key="reaction.emoji" type="button" dir="ltr" class="inline-flex min-h-9 min-w-11 items-center justify-center gap-1 rounded-full border bg-white px-2.5 text-xs font-bold shadow-[0_2px_7px_rgba(25,14,20,.12)] transition hover:-translate-y-0.5 hover:border-brand/40 focus-visible:outline-3 focus-visible:outline-brand disabled:cursor-default" :class="reaction.mine ? 'border-brand/35 text-brand' : 'border-slate-200 text-slate-700'" :aria-label="`${reaction.emoji} · ${reaction.count}`" :aria-pressed="reaction.mine" :disabled="!selected.can_send || pendingReactionIds.has(message.id)" @click="react(message, reaction.emoji)"><span aria-hidden="true" class="text-base leading-none">{{ reaction.emoji }}</span><span class="tabular-nums">{{ reaction.count }}</span></button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-if="deleteId" ref="deleteDialog" role="alertdialog" @keydown.esc="deleteId = null" :aria-label="t('messaging.confirmDelete')" class="flex flex-wrap items-center gap-3 border-t border-red-100 bg-red-50 p-4 text-sm"><p class="min-w-0 flex-1 font-semibold text-red-900">{{ t('messaging.confirmDelete') }}</p><button type="button" class="min-h-11 rounded-xl border border-red-200 bg-white px-4 font-bold text-red-800" @click="deleteId = null">{{ t('messaging.cancel') }}</button><button type="button" class="min-h-11 rounded-xl bg-red-700 px-4 font-bold text-white" @click="remove">{{ t('messaging.delete') }}</button></div>
                    <MessageComposer v-if="!historyMode" ref="composer" :key="selected.id" :sending="sending" :disabled="!selected.can_send || !online" :reply="reply" :send-error="sendError" @cancel-reply="reply = null" @send="send" />
                </section>
                <div v-else class="grid min-h-80 place-items-center gap-3 rounded-[1.5rem] border border-dashed border-slate-300 bg-white px-6 py-12 text-center"><span class="grid size-16 place-items-center rounded-2xl bg-brand-soft text-brand"><MessagingIcon name="messages" class="size-8" /></span><div><h3 class="text-lg font-black text-slate-900">{{ t('messaging.chooseConversation') }}</h3><p class="mt-1 text-sm text-slate-500">{{ t('messaging.selectConversationHint') }}</p></div></div>
            </div>
        </div>
        <Teleport to="body">
            <div v-if="reactionMenu" ref="reactionMenuElement" id="reaction-popover" role="group" :aria-label="t(reactionMenu.mode === 'actions' ? 'messaging.messageActions' : 'messaging.react')" :dir="locale === 'ar' ? 'rtl' : 'ltr'" class="fixed z-[60] flex flex-wrap justify-center gap-0.5 rounded-2xl border border-slate-200 bg-white p-1.5 shadow-[0_12px_32px_rgba(30,20,26,.22)]" :style="{ top: `${reactionMenu.top}px`, left: `${reactionMenu.left}px`, width: `${reactionMenu.width}px` }" @keydown.esc.stop.prevent="closeReactionMenu(true)">
                <template v-if="reactionMenu.mode === 'actions'">
                    <button v-if="selected?.can_send" type="button" class="grid size-11 place-items-center rounded-xl text-brand transition hover:bg-brand-soft focus-visible:outline-3 focus-visible:outline-brand" :aria-label="t('messaging.reply')" :title="t('messaging.reply')" @click="chooseReply"><MessagingIcon name="reply" class="size-5" /></button>
                    <button v-if="selected?.can_send" type="button" class="grid size-11 place-items-center rounded-xl text-brand transition hover:bg-brand-soft focus-visible:outline-3 focus-visible:outline-brand disabled:opacity-50" :aria-label="t('messaging.react')" :title="t('messaging.react')" :disabled="pendingReactionIds.has(reactionMenu.message.id)" @click="openReactionChoices"><MessagingIcon name="reaction" class="size-5" /></button>
                    <button v-if="reactionMenu.message.can_delete" type="button" class="grid size-11 place-items-center rounded-xl text-red-700 transition hover:bg-red-50 focus-visible:outline-3 focus-visible:outline-red-600" :aria-label="t('messaging.delete')" :title="t('messaging.delete')" @click="chooseDelete"><MessagingIcon name="trash" class="size-5" /></button>
                </template>
                <button v-for="reaction in reactionMenu.mode === 'reactions' ? emoji : []" :key="reaction" type="button" class="grid size-11 place-items-center rounded-xl text-xl transition hover:bg-brand-soft focus-visible:outline-3 focus-visible:outline-brand" :aria-label="reaction" @click="chooseReaction(reaction)">{{ reaction }}</button>
            </div>
        </Teleport>
    </section>
</template>

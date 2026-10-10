<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../../stores/auth';
import { useCourseMessaging } from '../../composables/useCourseMessaging';
import * as api from '../../api/messaging';
import MessageComposer from './MessageComposer.vue';
import GroupWizard from './GroupWizard.vue';
const props = defineProps({ courseId: { type: [Number, String], default: null } });
const { t, locale } = useI18n(), auth = useAuthStore();
const chat = useCourseMessaging();
const { conversations, selected, messages, error, loading, sending, online, hasOlder, notificationCount, page, lastPage } = chat;
const courses = ref([]), courseFilter = ref(props.courseId ?? ''), search = ref(''), unread = ref(false), wizard = ref(null), reply = ref(null), deleteId = ref(null), historySearch = ref(''), historyMode = ref(false), listBusy = ref(false), historyBusy = ref(false), feed = ref(null);
const composer = ref(null), deleteDialog = ref(null);
watch(deleteId, async (id) => { await nextTick(); if (id) deleteDialog.value?.querySelector('button')?.focus(); else composer.value?.focus(); });
async function beginReply(message) { reply.value = message; await nextTick(); composer.value?.focus(); }
const selectedCourse = computed(() => courses.value.find((course) => String(course.id) === String(courseFilter.value)));
const emoji = ['👍', '❤️', '😂', '🎉', '😮', '🙏'];
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
async function select(conversation) { if (sending.value) return; reply.value = null; deleteId.value = null; historyMode.value = false; historySearch.value = ''; await chat.select(conversation); await nextTick(); if (feed.value) feed.value.scrollTop = feed.value.scrollHeight; }
async function manage() { courseFilter.value = selected.value.course.id; await nextTick(); wizard.value = 'members'; }
async function start() {
    if (!selectedCourse.value) return;
    if (selectedCourse.value.is_instructor) { wizard.value = 'private'; return; }
    try { const conversation = await api.startPrivate(selectedCourse.value.id); await load(); await select(conversation); }
    catch (failure) { error.value = failure; }
}
async function done(conversation) { wizard.value = null; await load(); await select(conversation); }
async function send({ payload, attachment, acknowledge }) {
    if (await chat.send(payload, attachment, auth.user)) { acknowledge(); await nextTick(); if (feed.value) feed.value.scrollTop = feed.value.scrollHeight; }
}
async function react(message, reaction) { try { await api.reactToMessage(message.id, reaction); await chat.sync(); } catch (failure) { error.value = failure; } }
async function remove() { try { await api.deleteMessage(deleteId.value); deleteId.value = null; await chat.sync(); } catch (failure) { error.value = failure; } }
async function searchHistory() {
    if (!selected.value || historyBusy.value) return;
    historyBusy.value = true;
    try { await chat.searchMessages(historySearch.value); historyMode.value = !!historySearch.value.trim(); }
    catch (failure) { error.value = failure; }
    finally { historyBusy.value = false; }
}
</script>
<template>
    <section class="min-w-0 space-y-4" :dir="locale === 'ar' ? 'rtl' : 'ltr'" :aria-label="t('messaging.title')">
        <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-xl font-black">{{ t('messaging.title') }} <span class="text-sm text-brand" aria-live="polite">({{ notificationCount }})</span></h2><span class="text-xs text-slate-500">{{ t('messaging.polling') }}</span></div>
        <p v-if="!online" role="status" class="rounded-xl bg-amber-50 p-3">{{ t('messaging.offline') }}</p>
        <div v-if="error" role="alert" class="rounded-xl bg-red-50 p-3 text-red-800"><p>{{ error.errors?.attachment?.[0] ?? error.errors?.body?.[0] ?? t([403, 404].includes(error.status) ? 'messaging.denied' : 'messaging.error') }}</p><button type="button" class="min-h-11 font-bold underline" @click="chat.sync(); load()">{{ t('messaging.retry') }}</button></div>
        <div class="grid min-w-0 gap-4 lg:grid-cols-[minmax(14rem,19rem)_minmax(0,1fr)]">
            <aside class="min-w-0 space-y-3 rounded-2xl border border-slate-200 bg-white p-4">
                <label v-if="!courseId" class="block text-sm">{{ t('messaging.course') }}<select v-model="courseFilter" class="mt-1 min-h-11 w-full rounded-xl border px-3"><option value="">{{ t('messaging.allCourses') }}</option><option v-for="course in courses" :key="course.id" :value="course.id">{{ course.title }}</option></select></label>
                <form class="flex gap-2" @submit.prevent="load()"><label class="sr-only" for="conversation-search">{{ t('messaging.search') }}</label><input id="conversation-search" v-model="search" :placeholder="t('messaging.search')" maxlength="120" class="min-h-11 min-w-0 flex-1 rounded-xl border px-3"><button class="min-h-11 rounded-xl border px-3" type="submit">{{ t('messaging.search') }}</button></form>
                <label class="flex min-h-11 items-center gap-2"><input v-model="unread" type="checkbox" @change="load()">{{ t('messaging.unreadOnly') }}</label>
                <div class="flex flex-wrap gap-2"><button v-if="selectedCourse && auth.can('messaging.send')" type="button" class="min-h-11 rounded-xl border px-3 text-sm" :disabled="sending" @click="start">{{ t('messaging.startPrivate') }}</button><button v-if="selectedCourse?.can_manage" type="button" class="min-h-11 rounded-xl border px-3 text-sm" @click="wizard = 'group'">{{ t('messaging.newGroup') }}</button></div>
                <p v-if="!selectedCourse" class="text-xs text-slate-500">{{ t('messaging.chooseCourse') }}</p><p v-if="listBusy" role="status">{{ t('messaging.loading') }}</p><p v-else-if="!conversations.length" class="text-sm text-slate-500">{{ t('messaging.empty') }}</p>
                <nav class="max-h-80 space-y-2 overflow-auto lg:max-h-[32rem]" :aria-label="t('messaging.conversations')"><button v-for="conversation in conversations" :key="conversation.id" type="button" class="w-full rounded-xl border p-3 text-start" :class="selected?.id === conversation.id ? 'border-brand bg-brand/5' : 'border-slate-200'" :aria-pressed="selected?.id === conversation.id" :disabled="sending" @click="select(conversation)"><span class="block break-words font-bold">{{ conversation.title }}</span><span class="block text-xs text-slate-500">{{ conversation.course.title }} · {{ t(`messaging.kind.${conversation.kind}`) }}</span><span v-if="conversation.unread_count" class="text-sm font-bold text-brand">{{ t('messaging.unreadCount', { count: conversation.unread_count }) }}</span></button></nav>
                <div v-if="lastPage > 1" class="flex items-center justify-between gap-2"><button type="button" class="min-h-11" :disabled="page <= 1 || listBusy" @click="load(page - 1)">{{ t('messaging.previous') }}</button><span>{{ page }}/{{ lastPage }}</span><button type="button" class="min-h-11" :disabled="page >= lastPage || listBusy" @click="load(page + 1)">{{ t('messaging.next') }}</button></div>
            </aside>
            <div class="min-w-0 space-y-4">
                <GroupWizard v-if="wizard && selectedCourse" :key="`${wizard}-${selected?.id ?? 0}`" :course="selectedCourse" :mode="wizard" :conversation="wizard === 'members' ? selected : null" @done="done" @cancel="wizard = null"/>
                <section v-if="selected" class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    <header class="space-y-3 border-b p-4"><div class="flex flex-wrap items-center justify-between gap-2"><h3 class="break-words font-bold">{{ selected.title }} · {{ selected.course.title }}</h3><button v-if="selected.can_manage && selected.kind === 'selected'" type="button" class="min-h-11 rounded-xl border px-3" @click="manage">{{ t('messaging.manageMembers') }}</button></div><form class="flex gap-2" @submit.prevent="searchHistory"><label class="sr-only" for="message-search">{{ t('messaging.searchMessages') }}</label><input id="message-search" v-model="historySearch" maxlength="120" :placeholder="t('messaging.searchMessages')" class="min-h-11 min-w-0 flex-1 rounded-xl border px-3"><button type="submit" class="min-h-11 rounded-xl border px-3" :disabled="historyBusy">{{ t('messaging.search') }}</button></form></header>
                    <p v-if="loading" role="status" class="p-4">{{ t('messaging.loading') }}</p>
                    <div ref="feed" class="max-h-[34rem] min-h-40 space-y-4 overflow-y-auto p-4" tabindex="0" :aria-label="t('messaging.history')">
                        <button v-if="hasOlder" type="button" class="min-h-11 w-full rounded-xl border" @click="chat.older(historyMode ? historySearch : '')">{{ t('messaging.older') }}</button>
                        <p v-if="!loading && !messages.length">{{ t('messaging.noMessages') }}</p>
                        <article v-for="message in messages" :key="message.id ?? message.client_id" class="min-w-0 space-y-2 rounded-xl p-3" :class="message.user.id === auth.user?.id ? 'bg-brand/5' : 'bg-slate-50'">
                            <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-500"><span class="font-bold">{{ message.user.name }}</span><time v-if="message.created_at">{{ new Date(message.created_at).toLocaleString(locale === 'ar' ? 'ar' : 'en') }}</time><span v-if="message.pending" role="status">{{ t('messaging.sending') }}</span><span v-if="message.failed" role="status">{{ t('messaging.failedRetry') }}</span></div>
                            <blockquote v-if="message.reply" class="border-s-2 border-brand ps-3 text-sm text-slate-500">{{ message.reply.deleted ? t('messaging.deleted') : message.reply.body || t('messaging.attachment') }}</blockquote>
                            <p class="whitespace-pre-wrap break-words text-sm leading-7">{{ message.deleted ? t('messaging.deleted') : message.body }}</p>
                            <div v-for="attachment in message.attachments" :key="attachment.id" class="min-w-0"><a v-if="attachment.kind === 'image'" :href="attachment.url" target="_blank" rel="noopener"><img :src="attachment.url" :alt="attachment.name" loading="lazy" class="max-h-64 max-w-full rounded-xl"></a><div v-else><audio :src="attachment.url" controls preload="none" class="max-w-full"/><p class="text-xs">{{ Math.round(attachment.duration) }}s</p></div><a :href="attachment.url" :download="attachment.name" class="inline-flex min-h-11 items-center break-all text-xs text-brand underline">{{ t('messaging.download') }}: {{ attachment.name }}</a></div>
                            <div v-if="message.id && !message.deleted && !historyMode" class="flex flex-wrap items-center gap-1"><button v-if="selected.can_send" type="button" class="min-h-11 rounded-lg px-3 text-sm text-brand" @click="beginReply(message)">{{ t('messaging.reply') }}</button><button v-for="reaction in message.reactions" :key="reaction.emoji" type="button" class="min-h-11 rounded-lg border px-2" :aria-pressed="reaction.mine" :disabled="!selected.can_send" @click="react(message, reaction.emoji)">{{ reaction.emoji }} {{ reaction.count }}</button><details v-if="selected.can_send"><summary class="flex min-h-11 cursor-pointer items-center rounded-lg px-3">{{ t('messaging.react') }}</summary><div class="flex flex-wrap"><button v-for="reaction in emoji" :key="reaction" type="button" class="min-h-11 min-w-11" :aria-label="reaction" @click="react(message, reaction)">{{ reaction }}</button></div></details><button v-if="message.can_delete" type="button" class="min-h-11 rounded-lg px-3 text-sm text-red-700" @click="deleteId = message.id">{{ t('messaging.delete') }}</button></div>
                            <p v-if="message.user.id === auth.user?.id && message.id" class="text-xs text-slate-500">{{ t('messaging.readers', { count: message.reader_count ?? 0 }) }}</p>
                        </article>
                    </div>
                    <div v-if="deleteId" ref="deleteDialog" role="alertdialog" @keydown.esc="deleteId = null" :aria-label="t('messaging.confirmDelete')" class="flex flex-wrap items-center gap-3 border-t bg-red-50 p-4"><p>{{ t('messaging.confirmDelete') }}</p><button type="button" class="min-h-11 rounded-xl border px-3" @click="deleteId = null">{{ t('messaging.cancel') }}</button><button type="button" class="min-h-11 rounded-xl bg-red-700 px-3 text-white" @click="remove">{{ t('messaging.delete') }}</button></div>
                    <MessageComposer v-if="!historyMode" ref="composer" :key="selected.id" :sending="sending" :disabled="!selected.can_send || !online" :reply="reply" @cancel-reply="reply = null" @send="send"/>
                </section>
                <p v-else class="rounded-2xl border border-dashed p-8 text-center text-slate-500">{{ t('messaging.chooseConversation') }}</p>
            </div>
        </div>
    </section>
</template>

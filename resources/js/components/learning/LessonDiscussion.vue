<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { fetchLessonComments, fetchLessonReplies, postLessonComment } from '../../api/lesson-discussions';
import LessonDiscussionEntry from './LessonDiscussionEntry.vue';

const props = defineProps({
    courseSlug: { type: String, required: true },
    lessonId: { type: Number, required: true },
    instructorId: { type: Number, default: null },
});
const { t, locale } = useI18n();
const comments = ref([]);
const nextBefore = ref(null);
const body = ref('');
const attachment = ref(null);
const attachmentInput = ref(null);
const attachmentError = ref(null);
const replyTo = ref(null);
const composer = ref(null);
const loading = ref(false);
const loadingOlder = ref(false);
const loadingReplyId = ref(null);
const replyErrors = ref({});
const expandedReplies = ref({});
const sending = ref(false);
const loadError = ref(null);
const sendError = ref(null);
let requestVersion = 0;

const canSend = computed(() => (body.value.trim().length > 0 || attachment.value) && body.value.length <= 2000 && !attachmentError.value && !sending.value);
const unavailable = computed(() => loadError.value?.status === 403 || loadError.value?.status === 404 || sendError.value?.status === 403 || sendError.value?.status === 404);

function toggleReplies(commentId) {
    expandedReplies.value = { ...expandedReplies.value, [commentId]: !expandedReplies.value[commentId] };
}

function clearAttachment() {
    attachment.value = null;
    attachmentError.value = null;
    if (attachmentInput.value) attachmentInput.value.value = '';
}

function chooseAttachment(event) {
    const file = event.target.files?.[0];
    attachmentError.value = null;
    attachment.value = null;
    if (!file) return;

    if (file.size > 10 * 1024 * 1024) {
        attachmentError.value = t('lessonDiscussion.attachmentTooLarge');
        event.target.value = '';
        return;
    }

    attachment.value = file;
}

async function chooseReply(comment) {
    replyTo.value = comment;
    await nextTick();
    composer.value?.focus();
}

async function load() {
    const version = ++requestVersion;
    comments.value = [];
    nextBefore.value = null;
    loadingReplyId.value = null;
    replyErrors.value = {};
    loadError.value = null;
    sendError.value = null;
    loading.value = true;

    try {
        const result = await fetchLessonComments(props.courseSlug, props.lessonId);
        if (version !== requestVersion) return;
        comments.value = result.data.reverse();
        nextBefore.value = result.next_before;
    } catch (error) {
        if (version === requestVersion) loadError.value = error;
    } finally {
        if (version === requestVersion) loading.value = false;
    }
}

async function loadOlder() {
    if (!nextBefore.value || loadingOlder.value) return;
    const version = requestVersion;
    loadingOlder.value = true;
    loadError.value = null;
    try {
        const result = await fetchLessonComments(props.courseSlug, props.lessonId, nextBefore.value);
        if (version !== requestVersion) return;
        const knownIds = new Set(comments.value.map((comment) => comment.id));
        comments.value = [...result.data.reverse().filter((comment) => !knownIds.has(comment.id)), ...comments.value];
        nextBefore.value = result.next_before;
    } catch (error) {
        if (version === requestVersion) loadError.value = error;
    } finally {
        if (version === requestVersion) loadingOlder.value = false;
    }
}

async function loadReplies(root) {
    if (!root.next_reply_after || loadingReplyId.value !== null) return;
    const version = requestVersion;
    loadingReplyId.value = root.id;
    replyErrors.value[root.id] = null;
    try {
        const result = await fetchLessonReplies(props.courseSlug, props.lessonId, root.id, root.next_reply_after);
        if (version !== requestVersion) return;
        const knownIds = new Set((root.replies ?? []).map((reply) => reply.id));
        root.replies = [...(root.replies ?? []), ...result.data.filter((reply) => !knownIds.has(reply.id))].sort((left, right) => left.id - right.id);
        root.next_reply_after = result.next_after;
    } catch (error) {
        if (version === requestVersion) replyErrors.value[root.id] = error;
    } finally {
        if (version === requestVersion) loadingReplyId.value = null;
    }
}

async function submit() {
    if (!canSend.value) return;
    const version = requestVersion;
    sending.value = true;
    sendError.value = null;
    try {
        let payload = {
            body: body.value.trim(),
            ...(replyTo.value ? { parent_id: replyTo.value.parent_id ?? replyTo.value.id } : {}),
        };
        if (attachment.value) {
            const formData = new FormData();
            if (payload.body) formData.append('body', payload.body);
            if (payload.parent_id) formData.append('parent_id', String(payload.parent_id));
            formData.append('attachment', attachment.value);
            payload = formData;
        }
        const comment = await postLessonComment(props.courseSlug, props.lessonId, payload);
        if (version !== requestVersion) return;
        if (comment.parent_id) {
            const root = comments.value.find((item) => item.id === comment.parent_id);
            if (root) {
                root.replies = [...(root.replies ?? []), comment].sort((left, right) => left.id - right.id);
                root.replies_count += 1;
                expandedReplies.value = { ...expandedReplies.value, [root.id]: true };
            }
        } else {
            comments.value.push({ ...comment, replies: [], replies_count: 0, next_reply_after: null });
        }
        body.value = '';
        clearAttachment();
        replyTo.value = null;
    } catch (error) {
        if (version === requestVersion) sendError.value = error;
    } finally {
        if (version === requestVersion) sending.value = false;
    }
}

watch([() => props.courseSlug, () => props.lessonId], () => {
    body.value = '';
    clearAttachment();
    replyTo.value = null;
    sending.value = false;
    loadingOlder.value = false;
    loadingReplyId.value = null;
    expandedReplies.value = {};
    load();
}, { immediate: true });
onBeforeUnmount(() => { requestVersion += 1; });
</script>

<template>
    <section class="min-w-0 space-y-5 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7" :dir="locale === 'ar' ? 'rtl' : 'ltr'" :aria-label="t('lessonDiscussion.title')">
        <div class="flex items-start gap-3 border-b border-slate-100 pb-4">
            <div class="grid size-11 shrink-0 place-items-center rounded-2xl bg-brand-soft text-brand" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-6"><path stroke-linecap="round" stroke-linejoin="round" d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5c-1.3 0-2.5-.3-3.5-.9L4 20l1.9-5A7.5 7.5 0 1 1 20 11.5Z" /></svg>
            </div>
            <div class="min-w-0 flex-1"><h3 class="text-lg font-black text-slate-950">{{ t('lessonDiscussion.title') }}</h3><p class="mt-1 text-sm leading-6 text-slate-500">{{ t('lessonDiscussion.description') }}</p></div>
            <button type="button" :disabled="loading || sending" class="min-h-9 shrink-0 rounded-lg px-2 text-xs font-bold text-brand hover:bg-brand-soft disabled:opacity-50" @click="load">{{ t('lessonDiscussion.refresh') }}</button>
        </div>

        <p v-if="loading" role="status" class="py-5 text-center text-sm text-slate-500">{{ t('common.loading') }}</p>
        <div v-else class="space-y-4">
            <div v-if="loadError" role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800">{{ t(loadError.status === 403 || loadError.status === 404 ? 'lessonDiscussion.unavailable' : 'lessonDiscussion.loadError') }} <button type="button" class="font-bold underline" @click="comments.length ? loadOlder() : load()">{{ t('lessonDiscussion.retry') }}</button></div>
            <button v-if="nextBefore" type="button" :disabled="loadingOlder" class="mx-auto block min-h-10 rounded-xl border border-slate-200 px-4 text-sm font-bold text-brand transition hover:bg-slate-50 disabled:opacity-50" @click="loadOlder">{{ t('lessonDiscussion.older') }}</button>
            <p v-if="!comments.length && !loadError" class="rounded-2xl bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">{{ t('lessonDiscussion.empty') }}</p>
            <ol class="space-y-3">
                <li v-for="comment in comments" :key="comment.id" class="min-w-0 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <LessonDiscussionEntry :comment="comment" :instructor-id="instructorId" @reply="chooseReply" />
                    <button v-if="comment.replies_count" type="button" :aria-expanded="Boolean(expandedReplies[comment.id])" :aria-controls="`lesson-replies-${lessonId}-${comment.id}`" class="mt-2 inline-flex min-h-9 items-center gap-2 rounded-lg px-2 text-xs font-bold text-brand transition hover:bg-brand-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand" @click="toggleReplies(comment.id)">
                        <svg class="size-4 transition-transform" :class="{ 'rotate-180': expandedReplies[comment.id] }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" /></svg>
                        {{ t(expandedReplies[comment.id] ? 'lessonDiscussion.hideReplies' : 'lessonDiscussion.showReplies', { count: comment.replies_count }) }}
                    </button>
                    <div v-if="comment.replies_count" :id="`lesson-replies-${lessonId}-${comment.id}`" :class="expandedReplies[comment.id] ? 'mt-2 border-s-2 border-slate-200 ps-4 sm:ps-6' : ''">
                        <ol v-if="expandedReplies[comment.id]" class="space-y-2">
                            <li v-for="reply in comment.replies ?? []" :key="reply.id" class="rounded-xl bg-slate-50 px-3 py-2">
                                <LessonDiscussionEntry :comment="reply" :instructor-id="instructorId" @reply="chooseReply" />
                            </li>
                        </ol>
                        <button v-if="expandedReplies[comment.id] && comment.next_reply_after" type="button" :disabled="loadingReplyId !== null" class="mt-2 min-h-9 rounded-lg px-2 text-xs font-bold text-brand hover:bg-brand-soft disabled:opacity-50" @click="loadReplies(comment)">{{ t('lessonDiscussion.moreReplies', { count: comment.replies_count - (comment.replies?.length ?? 0) }) }}</button>
                        <p v-if="expandedReplies[comment.id] && replyErrors[comment.id]" role="alert" class="mt-2 text-xs text-rose-700">{{ t('lessonDiscussion.repliesError') }}</p>
                    </div>
                </li>
            </ol>
        </div>

        <form v-if="!unavailable" class="space-y-3 border-t border-slate-100 pt-5" @submit.prevent="submit">
            <div v-if="replyTo" class="flex items-center justify-between gap-3 rounded-xl bg-brand-soft px-3 py-2 text-sm text-brand"><span class="truncate font-bold">{{ t('lessonDiscussion.replyingTo', { name: replyTo.author?.name }) }}</span><button type="button" class="shrink-0 font-bold underline" @click="replyTo = null">{{ t('lessonDiscussion.cancelReply') }}</button></div>
            <label class="sr-only" :for="`lesson-comment-${lessonId}`">{{ t('lessonDiscussion.placeholder') }}</label>
            <textarea :id="`lesson-comment-${lessonId}`" ref="composer" v-model="body" rows="3" maxlength="2000" :placeholder="t('lessonDiscussion.placeholder')" class="w-full resize-y rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm leading-7 text-slate-950 outline-none focus:border-brand focus:ring-2 focus:ring-brand/20" />
            <input ref="attachmentInput" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf,.txt,.doc,.docx,.xls,.xlsx,.ppt,.pptx" class="sr-only" :aria-label="t('lessonDiscussion.chooseAttachment')" @change="chooseAttachment" />
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex min-w-0 flex-wrap items-center gap-2">
                    <button type="button" :disabled="sending" class="inline-flex min-h-10 items-center gap-2 rounded-xl border border-slate-200 px-3 text-sm font-bold text-brand transition hover:bg-brand-soft focus-visible:outline-2 focus-visible:outline-brand disabled:opacity-50" @click="attachmentInput?.click()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 11.5 12.5 20a5 5 0 0 1-7-7l9-9a3.5 3.5 0 0 1 5 5l-9 9a2 2 0 0 1-3-3l8-8" /></svg>{{ t('lessonDiscussion.chooseAttachment') }}</button>
                    <span v-if="attachment" class="max-w-48 truncate rounded-lg bg-slate-100 px-2 py-1 text-xs text-slate-700" :title="attachment.name">{{ attachment.name }}</span>
                    <button v-if="attachment" type="button" :disabled="sending" class="min-h-9 rounded-lg px-2 text-xs font-bold text-rose-700 hover:bg-rose-50" @click="clearAttachment">{{ t('lessonDiscussion.removeAttachment') }}</button>
                    <span class="text-xs text-slate-500">{{ t('lessonDiscussion.attachmentHint') }}</span>
                </div>
                <div class="flex items-center gap-3"><span class="text-xs text-slate-500">{{ t('lessonDiscussion.limit', { count: body.length }) }}</span><button type="submit" :disabled="!canSend" class="min-h-11 rounded-xl bg-brand px-5 text-sm font-bold text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50">{{ t(sending ? 'lessonDiscussion.sending' : 'lessonDiscussion.send') }}</button></div>
            </div>
            <p v-if="attachmentError || sendError" role="alert" class="text-sm text-rose-700">{{ attachmentError ?? sendError?.errors?.attachment?.[0] ?? sendError?.errors?.body?.[0] ?? t('lessonDiscussion.sendError') }}</p>
        </form>
        <p v-if="unavailable && !loadError" role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800">{{ t('lessonDiscussion.unavailable') }}</p>
    </section>
</template>

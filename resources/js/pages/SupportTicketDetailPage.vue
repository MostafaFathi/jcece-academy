<script setup>
import { onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../stores/auth';
import { downloadTicketAttachment, fetchTicket, fetchTicketMessages, reopenTicket, replyToTicket } from '../api/support';
import { attachmentExtensions, hasAttachmentErrors, ticketFormData, validateAttachments } from '../utils/support';
import { formatDate } from '../utils/catalog';
import PageHeading from '../components/ui/PageHeading.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseAlert from '../components/ui/BaseAlert.vue';
import LoadingState from '../components/ui/LoadingState.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import PaginationNav from '../components/ui/PaginationNav.vue';

const props = defineProps({ id: { type: [String, Number], required: true } });
const { t, locale } = useI18n();
const auth = useAuthStore();
const ticket = ref(null);
const messages = ref([]);
const meta = ref(null);
const loading = ref(true);
const messagesLoading = ref(false);
const error = ref(null);
const actionError = ref(null);
const downloadError = ref(null);
const busy = ref(false);
const downloadingId = ref(null);
const replyBody = ref('');
const files = ref([]);
const validation = ref(null);
let requestVersion = 0;

async function loadMessages(page = 1) {
    messagesLoading.value = true;
    try { const result = await fetchTicketMessages(props.id, page); messages.value = result.items; meta.value = result.meta; }
    catch (failure) { actionError.value = failure; messages.value = []; meta.value = null; }
    finally { messagesLoading.value = false; }
}
async function load() {
    const version = ++requestVersion;
    loading.value = true;
    error.value = null;
    ticket.value = null;
    messages.value = [];
    try { const result = await fetchTicket(props.id); if (version !== requestVersion) return; ticket.value = result; await loadMessages(); }
    catch (failure) { if (version === requestVersion) error.value = failure; }
    finally { if (version === requestVersion) loading.value = false; }
}
onMounted(load);
watch(() => props.id, load);

async function refreshAfterMutation() {
    ticket.value = await fetchTicket(props.id);
    await loadMessages(1);
    if (meta.value?.last_page > 1) await loadMessages(meta.value.last_page);
}
function selectFiles(event) { files.value = Array.from(event.target.files ?? []); validation.value = validateAttachments(files.value); }
async function sendReply() {
    if (busy.value || !ticket.value || ticket.value.status === 'closed') return;
    validation.value = !replyBody.value.trim() || replyBody.value.length > 10000 ? 'bodyRequired' : validateAttachments(files.value);
    if (validation.value) return;
    busy.value = true;
    actionError.value = null;
    try { await replyToTicket(props.id, ticketFormData({ body: replyBody.value.trim() }, files.value)); replyBody.value = ''; files.value = []; await refreshAfterMutation(); }
    catch (failure) { actionError.value = failure; await fetchTicket(props.id).then((result) => { ticket.value = result; }).catch(() => {}); }
    finally { busy.value = false; }
}
async function reopen() {
    if (busy.value || ticket.value?.status !== 'closed') return;
    busy.value = true;
    actionError.value = null;
    try { await reopenTicket(props.id); await refreshAfterMutation(); }
    catch (failure) { actionError.value = failure; }
    finally { busy.value = false; }
}
async function download(id) {
    if (downloadingId.value !== null) return;
    downloadingId.value = id;
    downloadError.value = null;
    try { await downloadTicketAttachment(id); }
    catch (failure) { downloadError.value = failure; }
    finally { downloadingId.value = null; }
}
</script>

<template>
    <div class="mx-auto max-w-5xl space-y-6" :dir="locale === 'ar' ? 'rtl' : 'ltr'"><RouterLink :to="{ name: 'student.support.index' }" class="text-sm font-bold text-brand">{{ t('common.back') }}</RouterLink><LoadingState v-if="loading" />
        <BaseAlert v-else-if="error" tone="danger">{{ t('support.detailError') }} <BaseButton variant="secondary" class="ms-2" @click="load">{{ t('common.retry') }}</BaseButton></BaseAlert>
        <template v-else-if="ticket"><PageHeading :title="ticket.subject" :description="ticket.ticket_number" /><section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7"><dl class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4"><div><dt class="text-slate-500">{{ t('support.statusLabel') }}</dt><dd class="mt-1 font-bold text-brand">{{ t(`support.status.${ticket.status}`) }}</dd></div><div><dt class="text-slate-500">{{ t('support.category') }}</dt><dd class="mt-1 font-bold">{{ t(`support.categories.${ticket.category}`) }}</dd></div><div><dt class="text-slate-500">{{ t('support.priority') }}</dt><dd class="mt-1 font-bold">{{ t(`support.priorities.${ticket.priority}`) }}</dd></div><div><dt class="text-slate-500">{{ t('support.created') }}</dt><dd class="mt-1">{{ formatDate(ticket.created_at, locale) }}</dd></div></dl><div v-if="ticket.related_course || ticket.related_order" class="mt-5 flex flex-wrap gap-4 border-t border-slate-100 pt-4 text-sm font-bold"><RouterLink v-if="ticket.related_course?.slug" :to="{ name: 'courses.show', params: { slug: ticket.related_course.slug } }" class="text-brand underline">{{ ticket.related_course.title }}</RouterLink><RouterLink v-if="ticket.related_order?.id" :to="{ name: 'student.orders.show', params: { id: ticket.related_order.id } }" class="text-brand underline"><bdi>{{ ticket.related_order.order_number }}</bdi></RouterLink></div></section>
            <BaseAlert v-if="actionError" tone="danger">{{ t('support.replyFailed') }} <button type="button" class="font-bold underline" @click="load">{{ t('common.retry') }}</button></BaseAlert><BaseAlert v-if="downloadError" tone="danger">{{ t('support.downloadFailed') }}</BaseAlert>
            <section class="space-y-5" :aria-label="t('support.conversation')"><h2 class="text-xl font-black text-slate-950">{{ t('support.conversation') }}</h2><LoadingState v-if="messagesLoading" /><EmptyState v-else-if="!messages.length" :title="t('support.noMessages')" /><ol v-else class="space-y-4"><li v-for="message in messages" :key="message.id" class="rounded-2xl border p-5" :class="message.author?.id === auth.user?.id ? 'border-brand/20 bg-brand-soft' : 'border-slate-200 bg-white'"><div class="flex flex-wrap justify-between gap-2 text-xs"><strong class="text-slate-900">{{ message.author?.id === auth.user?.id ? t('support.you') : t('support.staff') }} · {{ message.author?.name }}</strong><time class="text-slate-500" :datetime="message.created_at">{{ formatDate(message.created_at, locale) }}</time></div><p class="mt-3 whitespace-pre-wrap break-words text-sm leading-7 text-slate-800">{{ message.body }}</p><ul v-if="message.attachments?.length" class="mt-4 flex flex-wrap gap-2"><li v-for="attachment in message.attachments" :key="attachment.id"><button type="button" class="min-h-11 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-bold text-brand hover:bg-slate-50 disabled:opacity-60" :disabled="downloadingId !== null" @click="download(attachment.id)">{{ t('support.download') }}: {{ attachment.filename }}</button></li></ul></li></ol><PaginationNav v-if="!messagesLoading && meta?.last_page > 1" :current-page="meta.current_page" :last-page="meta.last_page" @change="loadMessages" /></section>
            <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7"><BaseAlert v-if="ticket.status === 'closed'">{{ t('support.closedNote') }} <BaseButton class="ms-3" variant="secondary" :loading="busy" @click="reopen">{{ t('support.reopen') }}</BaseButton></BaseAlert><template v-else><p v-if="ticket.status === 'resolved'" class="mb-4 text-sm text-slate-600">{{ t('support.resolvedNote') }}</p><p v-if="ticket.status === 'waiting_for_student'" class="mb-4 text-sm text-slate-600">{{ t('support.waitingNote') }}</p><form class="space-y-4" @submit.prevent="sendReply"><div><label for="ticket-reply" class="mb-2 block text-sm font-bold">{{ t('support.reply') }}</label><textarea id="ticket-reply" v-model="replyBody" rows="4" maxlength="10000" required class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-brand focus:outline-none" :disabled="busy" /><p v-if="validation === 'bodyRequired' || actionError?.errors?.body" role="alert" class="mt-1 text-sm text-red-700">{{ t('support.bodyRequired') }}</p></div><div><label for="reply-files" class="mb-2 block text-sm font-bold">{{ t('support.files') }}</label><input id="reply-files" type="file" multiple :accept="attachmentExtensions" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm" :disabled="busy" @change="selectFiles"><p class="mt-1 text-xs text-slate-500">{{ t('support.fileHint') }}</p><p v-if="['tooManyFiles', 'invalidFile'].includes(validation) || hasAttachmentErrors(actionError?.errors)" role="alert" class="text-sm text-red-700">{{ t(`support.${validation === 'tooManyFiles' ? validation : 'invalidFile'}`) }}</p></div><BaseButton type="submit" :loading="busy">{{ t('support.sendReply') }}</BaseButton></form></template></section>
        </template>
    </div>
</template>

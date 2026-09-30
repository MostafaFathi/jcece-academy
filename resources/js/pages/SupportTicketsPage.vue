<script setup>
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { fetchTickets } from '../api/support';
import { formatDate } from '../utils/catalog';
import PageHeading from '../components/ui/PageHeading.vue';
import BaseButton from '../components/ui/BaseButton.vue';
import BaseAlert from '../components/ui/BaseAlert.vue';
import LoadingState from '../components/ui/LoadingState.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import PaginationNav from '../components/ui/PaginationNav.vue';

const { t, locale } = useI18n();
const tickets = ref([]);
const meta = ref(null);
const loading = ref(true);
const error = ref(null);
let requesting = false;
async function load(page = 1) {
    if (requesting) return;
    requesting = true;
    loading.value = true;
    error.value = null;
    try { const result = await fetchTickets(page); tickets.value = result.items; meta.value = result.meta; }
    catch (failure) { error.value = failure; }
    finally { loading.value = false; requesting = false; }
}
onMounted(() => load());
</script>

<template>
    <div class="space-y-7" :dir="locale === 'ar' ? 'rtl' : 'ltr'"><PageHeading :title="t('support.heading')" :description="t('support.description')"><template #actions><RouterLink :to="{ name: 'student.support.create' }" class="inline-flex min-h-11 items-center rounded-xl bg-brand px-5 py-3 text-sm font-bold text-white hover:bg-brand-dark">{{ t('support.newTicket') }}</RouterLink></template></PageHeading>
        <LoadingState v-if="loading" />
        <BaseAlert v-else-if="error" tone="danger">{{ t('support.listError') }} <BaseButton class="ms-2" variant="secondary" @click="load(meta?.current_page ?? 1)">{{ t('common.retry') }}</BaseButton></BaseAlert>
        <EmptyState v-else-if="!tickets.length" :title="t('support.empty')" :description="t('support.emptyDescription')" />
        <div v-else class="grid gap-4 lg:grid-cols-2"><article v-for="ticket in tickets" :key="ticket.id" class="min-w-0 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"><div class="flex flex-wrap items-start justify-between gap-3"><div class="min-w-0"><p class="text-xs font-bold text-brand"><bdi>{{ ticket.ticket_number }}</bdi></p><h2 class="mt-2 break-words text-lg font-black text-slate-950">{{ ticket.subject }}</h2></div><span class="rounded-full bg-brand-soft px-3 py-1.5 text-xs font-bold text-brand">{{ t(`support.status.${ticket.status}`) }}</span></div><dl class="mt-5 grid grid-cols-2 gap-3 text-sm"><div><dt class="text-slate-500">{{ t('support.category') }}</dt><dd class="font-bold text-slate-800">{{ t(`support.categories.${ticket.category}`) }}</dd></div><div><dt class="text-slate-500">{{ t('support.priority') }}</dt><dd class="font-bold text-slate-800">{{ t(`support.priorities.${ticket.priority}`) }}</dd></div><div><dt class="text-slate-500">{{ t('support.created') }}</dt><dd class="text-slate-800">{{ formatDate(ticket.created_at, locale) }}</dd></div><div><dt class="text-slate-500">{{ t('support.lastReply') }}</dt><dd class="text-slate-800">{{ formatDate(ticket.last_reply_at, locale) || '—' }}</dd></div></dl><RouterLink :to="{ name: 'student.support.show', params: { id: ticket.id } }" class="mt-5 inline-flex min-h-11 items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold text-brand hover:bg-brand-soft">{{ t('support.openTicket') }}</RouterLink></article></div>
        <PaginationNav v-if="!loading && !error && meta?.last_page > 1" :current-page="meta.current_page" :last-page="meta.last_page" @change="load" />
    </div>
</template>

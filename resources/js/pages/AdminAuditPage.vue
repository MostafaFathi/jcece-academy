<script setup>
import { reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { api } from '../api/client';
import { fetchAdminUser, fetchAdminUsers } from '../api/admin-users';
import { formatDate } from '../utils/catalog';
import PageHeading from '../components/ui/PageHeading.vue';
import BaseAlert from '../components/ui/BaseAlert.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import LoadingState from '../components/ui/LoadingState.vue';
import PaginationNav from '../components/ui/PaginationNav.vue';
import AsyncResourceSelect from '../components/ui/AsyncResourceSelect.vue';

const route = useRoute();
const router = useRouter();
const { t, locale } = useI18n();
const filters = reactive({ event_type: '', actor_id: '', from: '', to: '' });
const events = ref([]);
const meta = ref(null);
const loading = ref(true);
const error = ref(null);
let requestSequence = 0;

async function load() {
    const sequence = ++requestSequence;
    loading.value = true;
    error.value = null;
    const params = { page: Math.max(Number.parseInt(String(route.query.page ?? 1), 10) || 1, 1) };
    for (const key of Object.keys(filters)) {
        filters[key] = String(route.query[key] ?? '');
        if (filters[key]) params[key] = filters[key];
    }
    try {
        const response = await api.get('/api/v1/admin/audit-events', { params });
        if (sequence === requestSequence) { events.value = response.data.data; meta.value = response.data; }
    } catch (failure) { if (sequence === requestSequence) error.value = failure; }
    finally { if (sequence === requestSequence) loading.value = false; }
}
function applyFilters() { router.push({ name: 'admin.audit.index', query: Object.fromEntries(Object.entries(filters).filter(([, value]) => value)) }); }
function changePage(page) { router.push({ name: 'admin.audit.index', query: { ...route.query, page } }); }
watch(() => route.query, load, { immediate: true, deep: true });
</script>

<template>
    <div class="space-y-6" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <PageHeading :title="t('audit.heading')" :description="t('audit.description')" />
        <form class="grid gap-3 rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-5" @submit.prevent="applyFilters">
            <label class="sr-only" for="audit-event">{{ t('audit.eventType') }}</label><input id="audit-event" v-model="filters.event_type" :placeholder="t('audit.eventType')" class="min-h-11 rounded-xl border border-slate-300 px-4">
            <AsyncResourceSelect v-model="filters.actor_id" id="audit-actor" :loader="fetchAdminUsers" :resolver="fetchAdminUser" :label="t('audit.actor')" />
            <label class="sr-only" for="audit-from">{{ t('audit.from') }}</label><input id="audit-from" v-model="filters.from" type="date" class="min-h-11 rounded-xl border border-slate-300 px-4">
            <label class="sr-only" for="audit-to">{{ t('audit.to') }}</label><input id="audit-to" v-model="filters.to" type="date" class="min-h-11 rounded-xl border border-slate-300 px-4">
            <div class="flex gap-2"><button type="submit" class="min-h-11 flex-1 rounded-xl bg-brand px-4 font-bold text-white">{{ t('audit.filter') }}</button><button type="button" class="min-h-11 rounded-xl border border-slate-300 px-4" @click="router.push({ name: 'admin.audit.index' })">{{ t('audit.reset') }}</button></div>
        </form>
        <LoadingState v-if="loading" />
        <BaseAlert v-else-if="error" tone="danger">{{ t('audit.loadError') }}</BaseAlert>
        <EmptyState v-else-if="!events.length" :title="t('audit.empty')" />
        <div v-else class="space-y-3"><article v-for="event in events" :key="event.id" class="grid gap-2 rounded-2xl border border-slate-200 bg-white p-5 text-sm shadow-sm md:grid-cols-[minmax(0,1fr)_auto]">
            <div class="min-w-0"><h2 class="break-all font-black text-slate-950">{{ event.event_type }}</h2><p class="mt-1 text-slate-600">{{ t('audit.actor') }}: {{ event.actor?.name ?? t('audit.system') }} · {{ t('audit.subject') }}: {{ event.subject_type }} #{{ event.subject_id }}</p><p v-if="Object.keys(event.metadata ?? {}).length" class="mt-2 break-words text-xs text-slate-500">{{ t('audit.metadata') }}: {{ Object.entries(event.metadata).map(([key, value]) => `${key}: ${value}`).join(' · ') }}</p></div>
            <time class="text-xs text-slate-500">{{ formatDate(event.created_at, locale) }}</time>
        </article></div>
        <PaginationNav v-if="!loading && !error && meta?.last_page > 1" :current-page="meta.current_page" :last-page="meta.last_page" @change="changePage" />
    </div>
</template>

<script setup>
import { reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../stores/auth';
import { fetchAdminInstructors } from '../api/admin-instructors';
import PageHeading from '../components/ui/PageHeading.vue';
import BaseAlert from '../components/ui/BaseAlert.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import LoadingState from '../components/ui/LoadingState.vue';
import PaginationNav from '../components/ui/PaginationNav.vue';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const { t, locale } = useI18n();
const filters = reactive({ search: '' });
const instructors = ref([]);
const meta = ref(null);
const loading = ref(true);
const error = ref(null);
let sequence = 0;

async function load() {
    const current = ++sequence;
    loading.value = true;
    error.value = null;
    filters.search = String(route.query.search ?? '');
    try {
        const result = await fetchAdminInstructors({ page: Math.max(Number(route.query.page) || 1, 1), ...(filters.search ? { search: filters.search } : {}) });
        if (current === sequence) { instructors.value = result.items; meta.value = result.meta; }
    } catch (failure) { if (current === sequence) error.value = failure; }
    finally { if (current === sequence) loading.value = false; }
}
function applyFilters() { router.push({ name: 'admin.instructors.index', query: filters.search ? { search: filters.search } : {} }); }
function changePage(page) { router.push({ name: 'admin.instructors.index', query: { ...route.query, page } }); }
watch(() => route.query, load, { immediate: true, deep: true });
</script>

<template>
    <div class="space-y-6" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <PageHeading :title="t('admin.instructors')" :description="t('admin.instructorListDescription')"><template #actions><RouterLink v-if="auth.can('instructors.manage')" :to="{ name: 'admin.instructors.create' }" class="inline-flex min-h-11 items-center rounded-xl bg-brand px-5 py-2.5 text-sm font-bold text-white">{{ t('admin.addInstructor') }}</RouterLink></template></PageHeading>
        <form class="flex flex-wrap gap-3 rounded-3xl border border-slate-200 bg-white p-4 shadow-sm" @submit.prevent="applyFilters"><label class="sr-only" for="instructor-search">{{ t('admin.searchInstructors') }}</label><input id="instructor-search" v-model="filters.search" type="search" :placeholder="t('admin.searchInstructors')" class="min-h-11 min-w-52 flex-1 rounded-xl border border-slate-300 px-4"><button type="submit" class="min-h-11 rounded-xl bg-brand px-5 text-sm font-bold text-white">{{ t('admin.apply') }}</button><button type="button" class="min-h-11 rounded-xl border border-slate-300 px-5 text-sm font-bold" @click="router.push({ name: 'admin.instructors.index' })">{{ t('admin.clear') }}</button></form>
        <LoadingState v-if="loading" /><BaseAlert v-else-if="error" tone="danger">{{ t(error.status === 403 ? 'admin.notAllowed' : 'admin.loadError') }} <button type="button" class="font-bold underline" @click="load">{{ t('common.retry') }}</button></BaseAlert><EmptyState v-else-if="!instructors.length" :title="t('admin.emptyInstructors')" />
        <div v-else class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm"><div class="hidden overflow-x-auto md:block"><table class="min-w-full text-sm"><thead class="bg-slate-50 text-slate-500"><tr><th class="p-4 text-start">{{ t('admin.name') }}</th><th class="p-4 text-start">{{ t('admin.jobTitle') }}</th><th class="p-4 text-start">{{ t('admin.specialties') }}</th><th class="p-4 text-start">{{ t('admin.status') }}</th><th class="p-4 text-start">{{ t('common.details') }}</th></tr></thead><tbody><tr v-for="item in instructors" :key="item.id" class="border-t border-slate-100"><td class="p-4 font-bold">{{ item.name }}</td><td class="p-4">{{ item.profile?.job_title ?? '—' }}</td><td class="p-4">{{ item.profile?.specialties?.join('، ') ?? '—' }}</td><td class="p-4">{{ t(`labels.statuses.${item.status}`) }}</td><td class="p-4"><RouterLink v-if="auth.can('instructors.update') || auth.can('instructors.manage')" :to="{ name: 'admin.instructors.edit', params: { id: item.id } }" class="font-bold text-brand underline">{{ t('admin.edit') }}</RouterLink></td></tr></tbody></table></div><div class="divide-y divide-slate-100 md:hidden"><article v-for="item in instructors" :key="item.id" class="space-y-2 p-5"><h2 class="font-black">{{ item.name }}</h2><p class="text-sm text-slate-600">{{ item.profile?.job_title ?? '—' }}</p><p class="text-sm">{{ item.profile?.specialties?.join('، ') ?? '—' }}</p><p class="text-xs text-slate-500">{{ t(`labels.statuses.${item.status}`) }}</p><RouterLink v-if="auth.can('instructors.update') || auth.can('instructors.manage')" :to="{ name: 'admin.instructors.edit', params: { id: item.id } }" class="inline-block min-h-11 py-3 font-bold text-brand underline">{{ t('admin.edit') }}</RouterLink></article></div></div>
        <PaginationNav v-if="!loading && !error && meta?.last_page > 1" :current-page="meta.current_page" :last-page="meta.last_page" @change="changePage" />
    </div>
</template>

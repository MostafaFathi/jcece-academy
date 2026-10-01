<script setup>
import { reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../stores/auth';
import { deleteAdminPackage, fetchAdminPackages } from '../api/admin-packages';
import { formatAmount, formatDate } from '../utils/catalog';
import PageHeading from '../components/ui/PageHeading.vue';
import BaseAlert from '../components/ui/BaseAlert.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import LoadingState from '../components/ui/LoadingState.vue';
import PaginationNav from '../components/ui/PaginationNav.vue';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const { t, locale } = useI18n();
const filters = reactive({ search: '', type: '' });
const packages = ref([]);
const meta = ref(null);
const loading = ref(true);
const error = ref(null);
const removingId = ref(null);
let sequence = 0;
async function load() {
    const current = ++sequence;
    loading.value = true; error.value = null;
    filters.search = String(route.query.search ?? ''); filters.type = String(route.query.type ?? '');
    try {
        const result = await fetchAdminPackages({ page: Math.max(Number(route.query.page) || 1, 1), ...(filters.search ? { search: filters.search } : {}), ...(filters.type ? { type: filters.type } : {}) });
        if (current === sequence) { packages.value = result.items; meta.value = result.meta; }
    } catch (failure) { if (current === sequence) error.value = failure; }
    finally { if (current === sequence) loading.value = false; }
}
function applyFilters() { router.push({ name: 'admin.packages.index', query: Object.fromEntries(Object.entries(filters).filter(([, value]) => value)) }); }
function changePage(page) { router.push({ name: 'admin.packages.index', query: { ...route.query, page } }); }
async function remove(item) {
    if (removingId.value !== null || !window.confirm(t('packages.confirmDelete'))) return;
    removingId.value = item.id; error.value = null;
    try { await deleteAdminPackage(item.id); await load(); }
    catch (failure) { error.value = failure; }
    finally { removingId.value = null; }
}
watch(() => route.query, load, { immediate: true, deep: true });
</script>

<template>
    <div class="space-y-6" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <PageHeading :title="t('packages.adminHeading')" :description="t('packages.adminDescription')"><template #actions><RouterLink v-if="auth.can('packages.create')" :to="{ name: 'admin.packages.create' }" class="inline-flex min-h-11 items-center rounded-xl bg-brand px-5 py-2.5 text-sm font-bold text-white">{{ t('packages.add') }}</RouterLink></template></PageHeading>
        <BaseAlert v-if="error && !loading" tone="danger">{{ t(error.status === 403 ? 'admin.notAllowed' : 'admin.loadError') }} <button type="button" class="font-bold underline" @click="load">{{ t('common.retry') }}</button></BaseAlert>
        <form class="flex flex-wrap gap-3 rounded-3xl border border-slate-200 bg-white p-4 shadow-sm" @submit.prevent="applyFilters"><label class="sr-only" for="package-search">{{ t('packages.search') }}</label><input id="package-search" v-model="filters.search" type="search" :placeholder="t('packages.search')" class="min-h-11 min-w-52 flex-1 rounded-xl border border-slate-300 px-4"><label class="sr-only" for="package-type">{{ t('packages.type') }}</label><select id="package-type" v-model="filters.type" class="min-h-11 rounded-xl border border-slate-300 px-4"><option value="">{{ t('packages.allTypes') }}</option><option value="package">{{ t('packages.types.package') }}</option><option value="learning_path">{{ t('packages.types.learning_path') }}</option></select><button type="submit" class="min-h-11 rounded-xl bg-brand px-5 text-sm font-bold text-white">{{ t('admin.apply') }}</button><button type="button" class="min-h-11 rounded-xl border border-slate-300 px-5 text-sm font-bold" @click="router.push({ name: 'admin.packages.index' })">{{ t('admin.clear') }}</button></form>
        <LoadingState v-if="loading" /><EmptyState v-else-if="!error && !packages.length" :title="t('packages.empty')" />
        <div v-if="!loading && packages.length" class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm"><div class="hidden overflow-x-auto lg:block"><table class="min-w-full text-sm"><thead class="bg-slate-50 text-slate-500"><tr><th class="p-4 text-start">{{ t('admin.title') }}</th><th class="p-4 text-start">{{ t('packages.type') }}</th><th class="p-4 text-start">{{ t('admin.status') }}</th><th class="p-4 text-start">{{ t('admin.price') }}</th><th class="p-4 text-start">{{ t('admin.access') }}</th><th class="p-4 text-start">{{ t('packages.courseCount') }}</th><th class="p-4 text-start">{{ t('admin.updated') }}</th><th class="p-4 text-start">{{ t('common.details') }}</th></tr></thead><tbody><tr v-for="item in packages" :key="item.id" class="border-t border-slate-100"><td class="p-4 font-bold">{{ item.title }}</td><td class="p-4">{{ t(`packages.types.${item.type}`) }}</td><td class="p-4">{{ t(`packages.statuses.${item.status}`) }}</td><td class="p-4"><bdi>{{ formatAmount(item.price, locale) }} {{ item.currency }}</bdi></td><td class="p-4">{{ item.access_duration_days === null ? t('admin.lifetime') : `${item.access_duration_days} ${t('admin.accessDays')}` }}</td><td class="p-4">{{ item.course_count ?? '—' }}</td><td class="p-4">{{ formatDate(item.updated_at, locale) }}</td><td class="p-4"><div class="flex gap-3"><RouterLink v-if="auth.can('packages.update')" :to="{ name: 'admin.packages.edit', params: { id: item.id } }" class="font-bold text-brand underline">{{ t('admin.edit') }}</RouterLink><RouterLink :to="{ name: 'admin.packages.courses', params: { id: item.id } }" class="font-bold text-brand underline">{{ t('packages.courses') }}</RouterLink><button v-if="auth.can('packages.delete')" type="button" class="font-bold text-red-700 underline" :disabled="removingId !== null" @click="remove(item)">{{ t('admin.delete') }}</button></div></td></tr></tbody></table></div><div class="divide-y divide-slate-100 lg:hidden"><article v-for="item in packages" :key="item.id" class="space-y-2 p-5"><h2 class="font-black">{{ item.title }}</h2><p class="text-sm text-slate-600">{{ t(`packages.types.${item.type}`) }} · {{ t(`packages.statuses.${item.status}`) }} · {{ item.course_count ?? '—' }} {{ t('packages.courses') }}</p><p class="text-sm font-bold"><bdi>{{ formatAmount(item.price, locale) }} {{ item.currency }}</bdi></p><p class="text-xs text-slate-500">{{ item.access_duration_days === null ? t('admin.lifetime') : `${item.access_duration_days} ${t('admin.accessDays')}` }} · {{ formatDate(item.updated_at, locale) }}</p><div class="flex flex-wrap gap-4"><RouterLink v-if="auth.can('packages.update')" :to="{ name: 'admin.packages.edit', params: { id: item.id } }" class="font-bold text-brand underline">{{ t('admin.edit') }}</RouterLink><RouterLink :to="{ name: 'admin.packages.courses', params: { id: item.id } }" class="font-bold text-brand underline">{{ t('packages.courses') }}</RouterLink><button v-if="auth.can('packages.delete')" type="button" class="font-bold text-red-700 underline" :disabled="removingId !== null" @click="remove(item)">{{ t('admin.delete') }}</button></div></article></div></div>
        <PaginationNav v-if="!loading && !error && meta?.last_page > 1" :current-page="meta.current_page" :last-page="meta.last_page" @change="changePage" />
    </div>
</template>

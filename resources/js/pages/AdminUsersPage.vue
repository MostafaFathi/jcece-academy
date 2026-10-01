<script setup>
import { reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../stores/auth';
import { fetchAdminUsers } from '../api/admin-users';
import { formatDate } from '../utils/catalog';
import PageHeading from '../components/ui/PageHeading.vue';
import BaseAlert from '../components/ui/BaseAlert.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import LoadingState from '../components/ui/LoadingState.vue';
import PaginationNav from '../components/ui/PaginationNav.vue';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const { t, locale } = useI18n();
const filters = reactive({ search: '', role: '', status: '' });
const roles = ['student', 'instructor', 'content_manager', 'sales_support', 'admin'];
const statuses = ['active', 'inactive', 'blocked'];
const users = ref([]);
const meta = ref(null);
const loading = ref(true);
const error = ref(null);
let sequence = 0;

async function load() {
    const current = ++sequence;
    loading.value = true;
    error.value = null;
    const params = { page: Math.max(Number.parseInt(String(route.query.page ?? 1), 10) || 1, 1) };
    for (const key of ['search', 'role', 'status']) {
        filters[key] = String(route.query[key] ?? '');
        if (filters[key]) params[key] = filters[key];
    }
    try {
        const result = await fetchAdminUsers(params);
        if (current === sequence) { users.value = result.items; meta.value = result.meta; }
    } catch (failure) { if (current === sequence) error.value = failure; }
    finally { if (current === sequence) loading.value = false; }
}
function applyFilters() { router.push({ name: 'admin.users.index', query: Object.fromEntries(Object.entries(filters).filter(([, value]) => value)) }); }
function changePage(page) { router.push({ name: 'admin.users.index', query: { ...route.query, page } }); }
function canEdit(user) { return (auth.can('users.update') || auth.can('users.manage')) && (!user.roles?.includes('admin') || auth.can('users.manage')); }
watch(() => route.query, load, { immediate: true, deep: true });
</script>

<template>
    <div class="space-y-6" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <PageHeading :title="t('admin.users')" :description="t('admin.userListDescription')"><template #actions><RouterLink v-if="auth.can('users.manage')" :to="{ name: 'admin.users.create' }" class="inline-flex min-h-11 items-center rounded-xl bg-brand px-5 py-2.5 text-sm font-bold text-white">{{ t('admin.addUser') }}</RouterLink></template></PageHeading>
        <form class="grid gap-3 rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="applyFilters">
            <label class="sr-only" for="user-search">{{ t('admin.searchUsers') }}</label><input id="user-search" v-model="filters.search" type="search" :placeholder="t('admin.searchUsers')" class="min-h-11 w-full rounded-xl border border-slate-300 px-4">
            <label class="sr-only" for="user-role">{{ t('admin.role') }}</label><select id="user-role" v-model="filters.role" class="min-h-11 rounded-xl border border-slate-300 px-4"><option value="">{{ t('admin.allRoles') }}</option><option v-for="role in roles" :key="role" :value="role">{{ t(`labels.roles.${role}`) }}</option></select>
            <label class="sr-only" for="user-status">{{ t('admin.status') }}</label><select id="user-status" v-model="filters.status" class="min-h-11 rounded-xl border border-slate-300 px-4"><option value="">{{ t('admin.allStatuses') }}</option><option v-for="status in statuses" :key="status" :value="status">{{ t(`labels.statuses.${status}`) }}</option></select>
            <div class="flex gap-2"><button type="submit" class="min-h-11 flex-1 rounded-xl bg-brand px-4 text-sm font-bold text-white">{{ t('admin.apply') }}</button><button type="button" class="min-h-11 rounded-xl border border-slate-300 px-4 text-sm font-bold" @click="router.push({ name: 'admin.users.index' })">{{ t('admin.clear') }}</button></div>
        </form>
        <LoadingState v-if="loading" /><BaseAlert v-else-if="error" tone="danger">{{ t(error.status === 403 ? 'admin.notAllowed' : 'admin.loadError') }} <button type="button" class="font-bold underline" @click="load">{{ t('common.retry') }}</button></BaseAlert><EmptyState v-else-if="!users.length" :title="t('admin.emptyUsers')" />
        <div v-else class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm"><div class="hidden overflow-x-auto md:block"><table class="min-w-full text-sm"><thead class="bg-slate-50 text-slate-500"><tr><th class="p-4 text-start">{{ t('admin.name') }}</th><th class="p-4 text-start">{{ t('admin.email') }}</th><th class="p-4 text-start">{{ t('admin.roles') }}</th><th class="p-4 text-start">{{ t('admin.status') }}</th><th class="p-4 text-start">{{ t('admin.created') }}</th><th class="p-4 text-start">{{ t('common.details') }}</th></tr></thead><tbody><tr v-for="user in users" :key="user.id" class="border-t border-slate-100"><td class="p-4 font-bold text-slate-900">{{ user.name }}</td><td class="break-all p-4"><bdi>{{ user.email }}</bdi></td><td class="p-4">{{ (user.roles ?? []).map((role) => t(`labels.roles.${role}`)).join('، ') }}</td><td class="p-4">{{ t(`labels.statuses.${user.status}`) }}</td><td class="p-4">{{ formatDate(user.created_at, locale) }}</td><td class="p-4"><RouterLink v-if="canEdit(user)" :to="{ name: 'admin.users.edit', params: { id: user.id } }" class="font-bold text-brand underline">{{ t('admin.edit') }}</RouterLink></td></tr></tbody></table></div><div class="divide-y divide-slate-100 md:hidden"><article v-for="user in users" :key="user.id" class="space-y-2 p-5"><h2 class="font-black text-slate-950">{{ user.name }}</h2><p class="break-all text-sm text-slate-600"><bdi>{{ user.email }}</bdi></p><p class="text-sm">{{ (user.roles ?? []).map((role) => t(`labels.roles.${role}`)).join('، ') }}</p><p class="text-xs text-slate-500">{{ t(`labels.statuses.${user.status}`) }} · {{ formatDate(user.created_at, locale) }}</p><RouterLink v-if="canEdit(user)" :to="{ name: 'admin.users.edit', params: { id: user.id } }" class="inline-block min-h-11 py-3 font-bold text-brand underline">{{ t('admin.edit') }}</RouterLink></article></div></div>
        <PaginationNav v-if="!loading && !error && meta?.last_page > 1" :current-page="meta.current_page" :last-page="meta.last_page" @change="changePage" />
    </div>
</template>

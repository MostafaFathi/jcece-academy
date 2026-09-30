<script setup>
import { ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../stores/auth';
import { deleteAdminCategory, fetchAdminCategories } from '../api/admin';
import PageHeading from '../components/ui/PageHeading.vue';
import LoadingState from '../components/ui/LoadingState.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import BaseAlert from '../components/ui/BaseAlert.vue';
import PaginationNav from '../components/ui/PaginationNav.vue';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const { t, locale } = useI18n();
const categories = ref([]);
const meta = ref(null);
const loading = ref(true);
const error = ref(null);
const deletingId = ref(null);
const deleteError = ref(null);
let sequence = 0;
function currentPage() { return Math.max(Number.parseInt(String(route.query.page ?? '1'), 10) || 1, 1); }
async function load() {
    const current = ++sequence;
    loading.value = true;
    error.value = null;
    try { const result = await fetchAdminCategories(currentPage()); if (current === sequence) { categories.value = result.items; meta.value = result.meta; } }
    catch (failure) { if (current === sequence) error.value = failure; }
    finally { if (current === sequence) loading.value = false; }
}
async function remove(category) {
    if (deletingId.value !== null || !auth.can('categories.delete') || !window.confirm(t('admin.confirmDeleteCategory'))) return;
    deletingId.value = category.id;
    deleteError.value = null;
    try { await deleteAdminCategory(category.id); await load(); }
    catch (failure) { deleteError.value = failure; }
    finally { deletingId.value = null; }
}
watch(() => route.query.page, load, { immediate: true });
</script>

<template>
    <div class="space-y-6" :dir="locale === 'ar' ? 'rtl' : 'ltr'"><PageHeading :title="t('admin.categories')" :description="t('admin.categoryListDescription')"><template #actions><RouterLink v-if="auth.can('categories.create')" :to="{ name: 'admin.categories.create' }" class="inline-flex min-h-11 items-center rounded-xl bg-brand px-5 py-2.5 text-sm font-bold text-white hover:bg-brand-dark">{{ t('admin.addCategory') }}</RouterLink></template></PageHeading>
        <BaseAlert v-if="deleteError" tone="danger">{{ t(deleteError.status === 422 ? 'admin.deleteBlocked' : deleteError.status === 403 ? 'admin.notAllowed' : 'admin.loadError') }}</BaseAlert>
        <LoadingState v-if="loading" /><BaseAlert v-else-if="error" tone="danger">{{ t(error.status === 403 ? 'admin.notAllowed' : 'admin.loadError') }} <button type="button" class="font-bold underline" @click="load">{{ t('common.retry') }}</button></BaseAlert><EmptyState v-else-if="!categories.length" :title="t('admin.emptyCategories')" />
        <div v-else class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm"><div class="hidden overflow-x-auto md:block"><table class="min-w-full text-sm"><thead class="bg-slate-50 text-start text-slate-500"><tr><th scope="col" class="p-4 text-start">{{ t('admin.name') }}</th><th scope="col" class="p-4 text-start">{{ t('admin.slug') }}</th><th scope="col" class="p-4 text-start">{{ t('admin.parent') }}</th><th scope="col" class="p-4 text-start">{{ t('admin.status') }}</th><th scope="col" class="p-4 text-start">{{ t('admin.sortOrder') }}</th><th scope="col" class="p-4 text-start">{{ t('common.details') }}</th></tr></thead><tbody><tr v-for="category in categories" :key="category.id" class="border-t border-slate-100"><td class="p-4 font-bold text-slate-900">{{ category.name }}</td><td class="p-4"><bdi>{{ category.slug }}</bdi></td><td class="p-4">{{ category.parent_id ?? '—' }}</td><td class="p-4">{{ t(category.is_active ? 'admin.active' : 'admin.inactive') }}</td><td class="p-4">{{ category.sort_order }}</td><td class="p-4"><div class="flex gap-3"><RouterLink v-if="auth.can('categories.update')" :to="{ name: 'admin.categories.edit', params: { id: category.id } }" class="font-bold text-brand underline">{{ t('admin.edit') }}</RouterLink><button v-if="auth.can('categories.delete')" type="button" class="font-bold text-red-700 underline disabled:opacity-50" :disabled="deletingId !== null" @click="remove(category)">{{ t('admin.delete') }}</button></div></td></tr></tbody></table></div><div class="divide-y divide-slate-100 md:hidden"><article v-for="category in categories" :key="category.id" class="space-y-3 p-5"><div class="flex items-start justify-between gap-3"><h2 class="break-words font-black text-slate-950">{{ category.name }}</h2><span class="rounded-full px-2 py-1 text-xs font-bold" :class="category.is_active ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-600'">{{ t(category.is_active ? 'admin.active' : 'admin.inactive') }}</span></div><p class="break-all text-xs text-slate-500"><bdi>{{ category.slug }}</bdi></p><p v-if="category.parent_id" class="text-sm text-slate-500">{{ t('admin.parent') }} #{{ category.parent_id }}</p><div class="flex gap-4"><RouterLink v-if="auth.can('categories.update')" :to="{ name: 'admin.categories.edit', params: { id: category.id } }" class="font-bold text-brand underline">{{ t('admin.edit') }}</RouterLink><button v-if="auth.can('categories.delete')" type="button" class="font-bold text-red-700 underline" :disabled="deletingId !== null" @click="remove(category)">{{ t('admin.delete') }}</button></div></article></div></div>
        <PaginationNav v-if="!loading && !error && meta?.last_page > 1" :current-page="meta.current_page" :last-page="meta.last_page" @change="(page) => router.push({ name: 'admin.categories.index', query: { page } })" />
    </div>
</template>

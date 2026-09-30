<script setup>
import { reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../stores/auth';
import { deleteAdminCourse, fetchAdminCourses } from '../api/admin';
import { formatAmount, formatDate } from '../utils/catalog';
import PageHeading from '../components/ui/PageHeading.vue';
import LoadingState from '../components/ui/LoadingState.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import BaseAlert from '../components/ui/BaseAlert.vue';
import PaginationNav from '../components/ui/PaginationNav.vue';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const { t, locale } = useI18n();
const courses = ref([]);
const meta = ref(null);
const loading = ref(true);
const error = ref(null);
const deleteError = ref(null);
const deletingId = ref(null);
const filters = reactive({ search: '', status: '', level: '' });
const statuses = ['draft', 'published', 'hidden', 'coming_soon', 'archived'];
const levels = ['beginner', 'intermediate', 'advanced', 'all_levels'];
let sequence = 0;

async function load() {
    const current = ++sequence;
    loading.value = true;
    error.value = null;
    const params = { page: Math.max(Number.parseInt(String(route.query.page ?? 1), 10) || 1, 1) };
    for (const key of ['search', 'status', 'level']) {
        const value = String(route.query[key] ?? '');
        filters[key] = value;
        if (value) params[key] = value;
    }
    try { const result = await fetchAdminCourses(params); if (current === sequence) { courses.value = result.items; meta.value = result.meta; } }
    catch (failure) { if (current === sequence) error.value = failure; }
    finally { if (current === sequence) loading.value = false; }
}
function applyFilters() { router.push({ name: 'admin.courses.index', query: Object.fromEntries(Object.entries(filters).filter(([, value]) => value)) }); }
function changePage(page) { router.push({ name: 'admin.courses.index', query: { ...route.query, page } }); }
async function remove(course) {
    if (deletingId.value !== null || !auth.can('courses.delete') || !window.confirm(t('admin.confirmDeleteCourse'))) return;
    deletingId.value = course.id;
    deleteError.value = null;
    try { await deleteAdminCourse(course.id); await load(); }
    catch (failure) { deleteError.value = failure; }
    finally { deletingId.value = null; }
}
watch(() => route.query, load, { immediate: true, deep: true });
</script>

<template>
    <div class="space-y-6" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <PageHeading :title="t('admin.courses')" :description="t('admin.courseListDescription')" />
        <BaseAlert v-if="auth.can('courses.create')">{{ t('admin.createUnavailable') }}</BaseAlert>
        <BaseAlert v-if="deleteError" tone="danger">{{ t(deleteError.status === 403 ? 'admin.notAllowed' : 'admin.loadError') }}</BaseAlert>
        <form class="grid gap-3 rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4" @submit.prevent="applyFilters">
            <label class="sr-only" for="course-search">{{ t('admin.search') }}</label><input id="course-search" v-model="filters.search" type="search" :placeholder="t('admin.search')" class="min-h-11 w-full rounded-xl border border-slate-300 px-4">
            <label class="sr-only" for="course-status-filter">{{ t('admin.status') }}</label><select id="course-status-filter" v-model="filters.status" class="min-h-11 rounded-xl border border-slate-300 px-4"><option value="">{{ t('admin.allStatuses') }}</option><option v-for="status in statuses" :key="status" :value="status">{{ t(`admin.courseStatus.${status}`) }}</option></select>
            <label class="sr-only" for="course-level-filter">{{ t('admin.level') }}</label><select id="course-level-filter" v-model="filters.level" class="min-h-11 rounded-xl border border-slate-300 px-4"><option value="">{{ t('admin.allLevels') }}</option><option v-for="level in levels" :key="level" :value="level">{{ t(`labels.levels.${level}`) }}</option></select>
            <div class="flex gap-2"><button type="submit" class="min-h-11 flex-1 rounded-xl bg-brand px-4 text-sm font-bold text-white">{{ t('admin.apply') }}</button><button type="button" class="min-h-11 rounded-xl border border-slate-300 px-4 text-sm font-bold" @click="router.push({ name: 'admin.courses.index' })">{{ t('admin.clear') }}</button></div>
        </form>
        <LoadingState v-if="loading" /><BaseAlert v-else-if="error" tone="danger">{{ t(error.status === 403 ? 'admin.notAllowed' : 'admin.loadError') }} <button type="button" class="font-bold underline" @click="load">{{ t('common.retry') }}</button></BaseAlert><EmptyState v-else-if="!courses.length" :title="t('admin.emptyCourses')" />
        <div v-else class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm"><div class="hidden overflow-x-auto lg:block"><table class="min-w-full text-sm"><thead class="bg-slate-50 text-slate-500"><tr><th class="p-4 text-start">{{ t('admin.title') }}</th><th class="p-4 text-start">{{ t('admin.category') }}</th><th class="p-4 text-start">{{ t('admin.instructor') }}</th><th class="p-4 text-start">{{ t('admin.status') }}</th><th class="p-4 text-start">{{ t('admin.price') }}</th><th class="p-4 text-start">{{ t('admin.access') }}</th><th class="p-4 text-start">{{ t('admin.updated') }}</th><th class="p-4 text-start">{{ t('common.details') }}</th></tr></thead><tbody><tr v-for="course in courses" :key="course.id" class="border-t border-slate-100"><td class="max-w-xs p-4 font-bold text-slate-900">{{ course.title }}</td><td class="p-4">{{ course.category?.name ?? '—' }}</td><td class="p-4">{{ course.instructor?.name ?? '—' }}</td><td class="p-4"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-bold">{{ t(`admin.courseStatus.${course.status}`) }}</span></td><td class="p-4"><bdi>{{ formatAmount(course.price, locale) }} {{ course.currency }}</bdi></td><td class="p-4">{{ course.access_duration_days === null ? t('admin.lifetime') : `${course.access_duration_days} ${t('admin.accessDays')}` }}</td><td class="p-4">{{ formatDate(course.updated_at, locale) }}</td><td class="p-4"><div class="flex gap-3"><RouterLink v-if="auth.can('courses.update')" :to="{ name: 'admin.courses.edit', params: { id: course.id } }" class="font-bold text-brand underline">{{ t('admin.edit') }}</RouterLink><button v-if="auth.can('courses.delete')" type="button" class="font-bold text-red-700 underline disabled:opacity-50" :disabled="deletingId !== null" @click="remove(course)">{{ t('admin.delete') }}</button></div></td></tr></tbody></table></div><div class="divide-y divide-slate-100 lg:hidden"><article v-for="course in courses" :key="course.id" class="space-y-3 p-5"><div class="flex items-start justify-between gap-3"><h2 class="font-black text-slate-950">{{ course.title }}</h2><span class="shrink-0 rounded-full bg-slate-100 px-2 py-1 text-xs font-bold">{{ t(`admin.courseStatus.${course.status}`) }}</span></div><p class="text-sm text-slate-600">{{ course.category?.name ?? '—' }} · {{ course.instructor?.name ?? '—' }}</p><p class="text-sm font-bold"><bdi>{{ formatAmount(course.price, locale) }} {{ course.currency }}</bdi></p><div class="flex gap-4"><RouterLink v-if="auth.can('courses.update')" :to="{ name: 'admin.courses.edit', params: { id: course.id } }" class="font-bold text-brand underline">{{ t('admin.edit') }}</RouterLink><button v-if="auth.can('courses.delete')" type="button" class="font-bold text-red-700 underline" :disabled="deletingId !== null" @click="remove(course)">{{ t('admin.delete') }}</button></div></article></div></div>
        <PaginationNav v-if="!loading && !error && meta?.last_page > 1" :current-page="meta.current_page" :last-page="meta.last_page" @change="changePage" />
    </div>
</template>

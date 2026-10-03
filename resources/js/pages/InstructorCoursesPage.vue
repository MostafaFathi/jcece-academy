<script setup>
import { reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { fetchInstructorCourses } from '../api/instructor';
import { formatDate } from '../utils/catalog';
import PageHeading from '../components/ui/PageHeading.vue';
import LoadingState from '../components/ui/LoadingState.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import BaseAlert from '../components/ui/BaseAlert.vue';
import PaginationNav from '../components/ui/PaginationNav.vue';

const route = useRoute();
const router = useRouter();
const { t, locale } = useI18n();
const items = ref([]);
const meta = ref(null);
const loading = ref(true);
const error = ref(null);
const filters = reactive({ search: '', status: '' });
let sequence = 0;

async function load() {
    const current = ++sequence;
    loading.value = true;
    error.value = null;
    filters.search = String(route.query.search ?? '');
    filters.status = String(route.query.status ?? '');
    const params = { page: Number(route.query.page) || 1 };
    if (filters.search) params.search = filters.search;
    if (filters.status) params.status = filters.status;
    try {
        const result = await fetchInstructorCourses(params);
        if (current === sequence) { items.value = result.items; meta.value = result.meta; }
    } catch (failure) { if (current === sequence) error.value = failure; }
    finally { if (current === sequence) loading.value = false; }
}
function search() { router.push({ name: 'instructor.courses.index', query: Object.fromEntries(Object.entries(filters).filter(([, value]) => value)) }); }
function changePage(page) { router.push({ name: 'instructor.courses.index', query: { ...route.query, page } }); }
watch(() => route.query, load, { immediate: true, deep: true });
</script>

<template>
    <div class="space-y-6" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <PageHeading :title="t('instructor.myCourses')" :description="t('instructor.coursesIntro')" />
        <form class="flex flex-wrap gap-3 rounded-3xl border border-slate-200 bg-white p-4 shadow-sm" @submit.prevent="search">
            <label class="sr-only" for="instructor-course-search">{{ t('instructor.search') }}</label>
            <input id="instructor-course-search" v-model="filters.search" type="search" :placeholder="t('instructor.search')" class="min-h-11 min-w-48 flex-1 rounded-xl border border-slate-300 px-4">
            <label class="sr-only" for="instructor-course-status">{{ t('instructor.status') }}</label>
            <select id="instructor-course-status" v-model="filters.status" class="min-h-11 rounded-xl border border-slate-300 px-4"><option value="">{{ t('instructor.allStatuses') }}</option><option v-for="status in ['draft', 'published', 'hidden', 'coming_soon', 'archived']" :key="status" :value="status">{{ t(`instructor.statusLabels.${status}`) }}</option></select>
            <button type="submit" class="min-h-11 rounded-xl bg-brand px-5 font-bold text-white">{{ t('instructor.searchButton') }}</button>
        </form>
        <LoadingState v-if="loading" />
        <BaseAlert v-else-if="error" tone="danger">{{ t('instructor.loadError') }} <button type="button" class="font-bold underline" @click="load">{{ t('instructor.retry') }}</button></BaseAlert>
        <EmptyState v-else-if="!items.length" :title="t('instructor.noCourses')" />
        <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article v-for="item in items" :key="item.id" class="flex flex-col rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-start justify-between gap-3"><h2 class="text-lg font-black text-slate-950">{{ item.title }}</h2><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold">{{ t(`instructor.statusLabels.${item.status}`) }}</span></div>
                <p class="mt-3 text-sm text-slate-600">{{ item.category?.name ?? '—' }} · {{ t(`labels.levels.${item.level}`) }}</p>
                <p class="mt-2 text-xs text-slate-500">{{ t('instructor.updated') }}: {{ formatDate(item.updated_at, locale) }}</p>
                <RouterLink :to="{ name: 'instructor.courses.show', params: { courseId: item.id } }" class="mt-5 inline-flex min-h-11 items-center font-bold text-brand underline">{{ t('instructor.view') }}</RouterLink>
            </article>
        </div>
        <PaginationNav v-if="!loading && !error && meta?.last_page > 1" :current-page="meta.current_page" :last-page="meta.last_page" @change="changePage" />
    </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { fetchCategories } from '../api/categories';
import { fetchCourses } from '../api/courses';
import BaseAlert from '../components/ui/BaseAlert.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import PaginationNav from '../components/ui/PaginationNav.vue';
import CatalogSkeleton from '../components/public/CatalogSkeleton.vue';
import CourseCard from '../components/public/CourseCard.vue';
import { usePageMeta } from '../composables/usePageMeta';

const route = useRoute();
const router = useRouter();
const { t } = useI18n();
const courses = ref([]);
const categories = ref([]);
const meta = ref(null);
const loading = ref(true);
const error = ref(null);
let requestSequence = 0;

const form = reactive({ search: '', category: '', level: '', sort: 'latest' });
const levelOptions = ['beginner', 'intermediate', 'advanced', 'all_levels'];
const sortOptions = [
    { value: 'latest', label: 'catalog.latest' },
    { value: 'oldest', label: 'catalog.oldest' },
    { value: 'price_asc', label: 'catalog.priceAsc' },
    { value: 'price_desc', label: 'catalog.priceDesc' },
    { value: 'title', label: 'catalog.titleSort' },
];

const currentPage = computed(() => meta.value?.current_page ?? 1);
const lastPage = computed(() => meta.value?.last_page ?? 1);
const resultSummary = computed(() => meta.value && meta.value.total > 0 ? t('catalog.resultSummary', { from: meta.value.from, to: meta.value.to, total: meta.value.total }) : t('common.results', { count: 0 }));

usePageMeta(() => t('catalog.coursesTitle'), () => t('catalog.coursesDescription'));

function queryValue(value, fallback = '') {
    return typeof value === 'string' ? value : fallback;
}

function syncForm(query) {
    form.search = queryValue(query.search);
    form.category = queryValue(query.category);
    form.level = levelOptions.includes(queryValue(query.level)) ? query.level : '';
    form.sort = sortOptions.some((item) => item.value === query.sort) ? query.sort : 'latest';
}

function requestParams(query) {
    return Object.fromEntries(Object.entries({
        search: queryValue(query.search),
        category: queryValue(query.category),
        level: levelOptions.includes(queryValue(query.level)) ? query.level : '',
        sort: sortOptions.some((item) => item.value === query.sort) ? query.sort : 'latest',
        page: Math.max(Number.parseInt(queryValue(query.page, '1'), 10) || 1, 1),
        per_page: 12,
    }).filter(([, value]) => value !== ''));
}

async function loadCourses() {
    const sequence = ++requestSequence;
    loading.value = true;
    error.value = null;

    try {
        const result = await fetchCourses(requestParams(route.query));
        if (sequence !== requestSequence) return;
        courses.value = result.items;
        meta.value = result.meta;
    } catch (requestError) {
        if (sequence !== requestSequence) return;
        error.value = requestError;
        courses.value = [];
        meta.value = null;
    } finally {
        if (sequence === requestSequence) loading.value = false;
    }
}

function applyFilters() {
    const query = Object.fromEntries(Object.entries({ search: form.search.trim(), category: form.category, level: form.level, sort: form.sort === 'latest' ? '' : form.sort }).filter(([, value]) => value !== ''));
    void router.push({ name: 'courses.index', query });
}

function resetFilters() {
    Object.assign(form, { search: '', category: '', level: '', sort: 'latest' });
    void router.push({ name: 'courses.index' });
}

function changePage(page) {
    void router.push({ name: 'courses.index', query: { ...route.query, page: String(page) } });
}

fetchCategories().then((items) => { categories.value = items; }).catch(() => { categories.value = []; });
watch(() => route.query, (query) => { syncForm(query); void loadCourses(); }, { immediate: true, deep: true });
</script>

<template>
    <div class="min-h-screen bg-slate-50">
        <section class="border-b border-slate-200 bg-white"><div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8 lg:py-18"><p class="text-xs font-black tracking-[0.2em] text-brand uppercase">JCEC ACADEMY</p><h1 class="mt-4 max-w-3xl text-4xl font-black tracking-tight text-slate-950 sm:text-5xl">{{ t('catalog.coursesTitle') }}</h1><p class="mt-4 max-w-2xl text-base leading-8 text-slate-600">{{ t('catalog.coursesDescription') }}</p></div></section>
        <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8"><form class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" @submit.prevent="applyFilters"><div class="mb-5"><h2 class="font-black text-slate-950">{{ t('catalog.filters') }}</h2><p class="mt-1 text-sm text-slate-500">{{ t('catalog.filtersDescription') }}</p></div><div class="grid gap-4 md:grid-cols-2 xl:grid-cols-[1.5fr_1fr_1fr_1fr_auto]"><label class="block"><span class="mb-2 block text-sm font-bold text-slate-700">{{ t('common.search') }}</span><input v-model="form.search" type="search" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-4 outline-none focus:border-brand focus:ring-3 focus:ring-brand/15" :placeholder="t('catalog.searchCourses')"></label><label class="block"><span class="mb-2 block text-sm font-bold text-slate-700">{{ t('catalog.category') }}</span><select v-model="form.category" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 outline-none focus:border-brand focus:ring-3 focus:ring-brand/15"><option value="">{{ t('catalog.allCategories') }}</option><template v-for="category in categories" :key="category.id"><option :value="category.slug">{{ category.name }}</option><option v-for="child in category.children ?? []" :key="child.id" :value="child.slug">— {{ child.name }}</option></template></select></label><label class="block"><span class="mb-2 block text-sm font-bold text-slate-700">{{ t('catalog.level') }}</span><select v-model="form.level" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 outline-none focus:border-brand focus:ring-3 focus:ring-brand/15"><option value="">{{ t('catalog.allLevels') }}</option><option v-for="level in levelOptions" :key="level" :value="level">{{ t(`labels.levels.${level}`) }}</option></select></label><label class="block"><span class="mb-2 block text-sm font-bold text-slate-700">{{ t('catalog.sort') }}</span><select v-model="form.sort" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 outline-none focus:border-brand focus:ring-3 focus:ring-brand/15"><option v-for="option in sortOptions" :key="option.value" :value="option.value">{{ t(option.label) }}</option></select></label><div class="flex items-end gap-2"><button type="submit" class="min-h-11 flex-1 rounded-xl bg-brand px-5 text-sm font-black text-white hover:bg-brand-dark">{{ t('common.apply') }}</button><button type="button" class="min-h-11 rounded-xl border border-slate-300 px-4 text-sm font-bold text-slate-600 hover:bg-slate-50" @click="resetFilters">{{ t('common.reset') }}</button></div></div></form>
            <div class="mt-8 flex flex-wrap items-center justify-between gap-4"><p class="text-sm font-semibold text-slate-600">{{ resultSummary }}</p></div>
            <BaseAlert v-if="error" tone="danger" class="mt-6">{{ t('catalog.loadError') }} <button type="button" class="font-black underline" @click="loadCourses">{{ t('common.retry') }}</button></BaseAlert><div v-if="loading" class="mt-6 grid gap-6 md:grid-cols-2 xl:grid-cols-3"><CatalogSkeleton v-for="index in 6" :key="index" /></div><div v-else-if="courses.length" class="mt-6 grid gap-6 md:grid-cols-2 xl:grid-cols-3"><CourseCard v-for="course in courses" :key="course.id" :course="course" /></div><EmptyState v-else-if="!error" class="mt-6" :title="t('catalog.emptyCourses')" :description="t('catalog.emptyCoursesText')"><button type="button" class="rounded-xl bg-brand px-5 py-2.5 text-sm font-black text-white" @click="resetFilters">{{ t('common.reset') }}</button></EmptyState><PaginationNav v-if="!loading && !error && lastPage > 1" class="mt-10" :current-page="currentPage" :last-page="lastPage" @change="changePage" /></div>
    </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { fetchCategories } from '../api/categories';
import { fetchCourses } from '../api/courses';
import { fetchInstructors } from '../api/public-site';
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
const instructors = ref([]);
const meta = ref(null);
const loading = ref(true);
const error = ref(null);
let requestSequence = 0;

const form = reactive({ search: '', category: '', instructor: '', level: '', language: '', training_type: '', price_type: '', rating_min: '', sort: 'latest' });
const levels = ['beginner', 'intermediate', 'advanced', 'all_levels'];
const trainingTypes = ['recorded', 'live', 'hybrid'];
const sorts = [
    { value: 'latest', label: 'catalog.latest' },
    { value: 'oldest', label: 'catalog.oldest' },
    { value: 'rating', label: 'discovery.highestRated' },
    { value: 'bestseller', label: 'discovery.bestseller' },
    { value: 'price_asc', label: 'catalog.priceAsc' },
    { value: 'price_desc', label: 'catalog.priceDesc' },
    { value: 'title', label: 'catalog.titleSort' },
];
const currentPage = computed(() => meta.value?.current_page ?? 1);
const lastPage = computed(() => meta.value?.last_page ?? 1);
const resultSummary = computed(() => meta.value?.total ? t('catalog.resultSummary', { from: meta.value.from, to: meta.value.to, total: meta.value.total }) : t('common.results', { count: 0 }));

usePageMeta(() => t('catalog.coursesTitle'), () => t('catalog.coursesDescription'));

function queryValue(value) { return typeof value === 'string' ? value : ''; }

function normalized(query) {
    return {
        search: queryValue(query.search),
        category: queryValue(query.category),
        instructor: /^\d+$/.test(queryValue(query.instructor)) ? query.instructor : '',
        level: levels.includes(queryValue(query.level)) ? query.level : '',
        language: ['ar', 'en'].includes(queryValue(query.language)) ? query.language : '',
        training_type: trainingTypes.includes(queryValue(query.training_type)) ? query.training_type : '',
        price_type: ['free', 'paid'].includes(queryValue(query.price_type)) ? query.price_type : '',
        rating_min: ['1', '2', '3', '4', '5'].includes(queryValue(query.rating_min)) ? query.rating_min : '',
        sort: sorts.some((item) => item.value === query.sort) ? query.sort : 'latest',
    };
}

async function loadCourses() {
    const sequence = ++requestSequence;
    loading.value = true;
    error.value = null;
    try {
        const filters = Object.fromEntries(Object.entries(normalized(route.query)).filter(([, value]) => value !== ''));
        const result = await fetchCourses({ ...filters, page: Math.max(Number.parseInt(queryValue(route.query.page), 10) || 1, 1), per_page: 12 });
        if (sequence !== requestSequence) return;
        courses.value = result.items;
        meta.value = result.meta;
    } catch (failure) {
        if (sequence !== requestSequence) return;
        error.value = failure;
        courses.value = [];
        meta.value = null;
    } finally {
        if (sequence === requestSequence) loading.value = false;
    }
}

function applyFilters() {
    const query = Object.fromEntries(Object.entries({ ...form, search: form.search.trim() }).filter(([, value]) => value !== '' && value !== 'latest'));
    void router.push({ name: 'courses.index', query });
}

function resetFilters() { void router.push({ name: 'courses.index' }); }
function changePage(page) { void router.push({ name: 'courses.index', query: { ...route.query, page: String(page) } }); }

async function loadInstructors() {
    try {
        let page = 1;
        let lastPage = 1;
        const found = [];
        do {
            const result = await fetchInstructors({ per_page: 100, page });
            found.push(...result.items);
            lastPage = result.meta?.last_page ?? page;
            page += 1;
        } while (page <= lastPage);
        instructors.value = found;
    } catch { instructors.value = []; }
}

fetchCategories().then((items) => { categories.value = items; }).catch(() => { categories.value = []; });
void loadInstructors();
watch(() => route.query, (query) => { Object.assign(form, normalized(query)); void loadCourses(); }, { immediate: true, deep: true });
</script>

<template>
    <div class="min-h-screen bg-slate-50">
        <section class="border-b border-slate-200 bg-white">
            <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
                <p class="text-xs font-black tracking-[0.2em] text-brand uppercase">JCEC ACADEMY</p>
                <h1 class="mt-4 max-w-3xl text-4xl font-black tracking-tight text-slate-950 sm:text-5xl">{{ t('catalog.coursesTitle') }}</h1>
                <p class="mt-4 max-w-2xl leading-8 text-slate-600">{{ t('catalog.coursesDescription') }}</p>
            </div>
        </section>
        <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
            <form class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" @submit.prevent="applyFilters">
                <h2 class="font-black text-slate-950">{{ t('catalog.filters') }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ t('catalog.filtersDescription') }}</p>
                <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <label class="block"><span class="mb-2 block text-sm font-bold">{{ t('common.search') }}</span><input v-model="form.search" type="search" class="min-h-11 w-full rounded-xl border border-slate-300 px-4 focus-visible:outline-3 focus-visible:outline-brand" :placeholder="t('catalog.searchCourses')"></label>
                    <label class="block"><span class="mb-2 block text-sm font-bold">{{ t('catalog.category') }}</span><select v-model="form.category" class="min-h-11 w-full rounded-xl border border-slate-300 px-3"><option value="">{{ t('catalog.allCategories') }}</option><template v-for="category in categories" :key="category.id"><option :value="category.slug">{{ category.name }}</option><option v-for="child in category.children ?? []" :key="child.id" :value="child.slug">— {{ child.name }}</option></template></select></label>
                    <label class="block"><span class="mb-2 block text-sm font-bold">{{ t('discovery.instructors') }}</span><select v-model="form.instructor" class="min-h-11 w-full rounded-xl border border-slate-300 px-3"><option value="">{{ t('discovery.allInstructors') }}</option><option v-for="instructor in instructors" :key="instructor.id" :value="String(instructor.id)">{{ instructor.name }}</option></select></label>
                    <label class="block"><span class="mb-2 block text-sm font-bold">{{ t('catalog.level') }}</span><select v-model="form.level" class="min-h-11 w-full rounded-xl border border-slate-300 px-3"><option value="">{{ t('catalog.allLevels') }}</option><option v-for="level in levels" :key="level" :value="level">{{ t(`labels.levels.${level}`) }}</option></select></label>
                    <label class="block"><span class="mb-2 block text-sm font-bold">{{ t('discovery.language') }}</span><select v-model="form.language" class="min-h-11 w-full rounded-xl border border-slate-300 px-3"><option value="">{{ t('catalog.allLanguages') }}</option><option v-for="language in ['ar', 'en']" :key="language" :value="language">{{ t(`labels.languages.${language}`) }}</option></select></label>
                    <label class="block"><span class="mb-2 block text-sm font-bold">{{ t('discovery.trainingType') }}</span><select v-model="form.training_type" class="min-h-11 w-full rounded-xl border border-slate-300 px-3"><option value="">{{ t('discovery.allTrainingTypes') }}</option><option v-for="type in trainingTypes" :key="type" :value="type">{{ t(`discovery.trainingTypes.${type}`) }}</option></select></label>
                    <label class="block"><span class="mb-2 block text-sm font-bold">{{ t('discovery.priceType') }}</span><select v-model="form.price_type" class="min-h-11 w-full rounded-xl border border-slate-300 px-3"><option value="">{{ t('discovery.allPrices') }}</option><option value="free">{{ t('common.free') }}</option><option value="paid">{{ t('discovery.paid') }}</option></select></label>
                    <label class="block"><span class="mb-2 block text-sm font-bold">{{ t('discovery.rating') }}</span><select v-model="form.rating_min" class="min-h-11 w-full rounded-xl border border-slate-300 px-3"><option value="">{{ t('discovery.allRatings') }}</option><option v-for="rating in [5, 4, 3, 2, 1]" :key="rating" :value="String(rating)">{{ t('discovery.ratingAtLeast', { count: rating }) }}</option></select></label>
                    <label class="block"><span class="mb-2 block text-sm font-bold">{{ t('catalog.sort') }}</span><select v-model="form.sort" class="min-h-11 w-full rounded-xl border border-slate-300 px-3"><option v-for="option in sorts" :key="option.value" :value="option.value">{{ t(option.label) }}</option></select></label>
                </div>
                <div class="mt-5 flex flex-wrap gap-3"><button type="submit" class="min-h-11 rounded-xl bg-brand px-6 text-sm font-black text-white focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-accent">{{ t('common.apply') }}</button><button type="button" class="min-h-11 rounded-xl border border-slate-300 px-5 text-sm font-bold" @click="resetFilters">{{ t('common.reset') }}</button></div>
            </form>
            <p class="mt-8 text-sm font-semibold text-slate-600" role="status" aria-live="polite">{{ resultSummary }}</p>
            <BaseAlert v-if="error" tone="danger" class="mt-6">{{ t('catalog.loadError') }} <button type="button" class="font-black underline" @click="loadCourses">{{ t('common.retry') }}</button></BaseAlert>
            <div v-if="loading" class="mt-6 grid gap-6 md:grid-cols-2 xl:grid-cols-3"><CatalogSkeleton v-for="index in 6" :key="index" /></div>
            <div v-else-if="courses.length" class="mt-6 grid gap-6 md:grid-cols-2 xl:grid-cols-3"><CourseCard v-for="course in courses" :key="course.id" :course="course" /></div>
            <EmptyState v-else-if="!error" class="mt-6" :title="t('catalog.emptyCourses')" :description="t('catalog.emptyCoursesText')"><button type="button" class="rounded-xl bg-brand px-5 py-2.5 text-sm font-black text-white" @click="resetFilters">{{ t('common.reset') }}</button></EmptyState>
            <PaginationNav v-if="!loading && !error && lastPage > 1" class="mt-10" :current-page="currentPage" :last-page="lastPage" @change="changePage" />
        </div>
    </div>
</template>

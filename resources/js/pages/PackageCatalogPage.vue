<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { fetchPackages } from '../api/packages';
import BaseAlert from '../components/ui/BaseAlert.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import PaginationNav from '../components/ui/PaginationNav.vue';
import CatalogSkeleton from '../components/public/CatalogSkeleton.vue';
import PackageCard from '../components/public/PackageCard.vue';
import { usePageMeta } from '../composables/usePageMeta';

const route = useRoute();
const router = useRouter();
const { t } = useI18n();
const packages = ref([]);
const meta = ref(null);
const loading = ref(true);
const error = ref(null);
const form = reactive({ search: '', type: '', sort: 'latest' });
const packageTypes = ['package', 'learning_path'];
const sortOptions = [{ value: 'latest', label: 'catalog.latest' }, { value: 'oldest', label: 'catalog.oldest' }, { value: 'price_asc', label: 'catalog.priceAsc' }, { value: 'price_desc', label: 'catalog.priceDesc' }, { value: 'title', label: 'catalog.titleSort' }];
let requestSequence = 0;

const currentPage = computed(() => meta.value?.current_page ?? 1);
const lastPage = computed(() => meta.value?.last_page ?? 1);
const resultSummary = computed(() => meta.value && meta.value.total > 0 ? t('catalog.resultSummary', { from: meta.value.from, to: meta.value.to, total: meta.value.total }) : t('common.results', { count: 0 }));

usePageMeta(() => t('catalog.packagesTitle'), () => t('catalog.packagesDescription'));
const queryValue = (value, fallback = '') => typeof value === 'string' ? value : fallback;

async function loadPackages() {
    const sequence = ++requestSequence;
    loading.value = true;
    error.value = null;
    form.search = queryValue(route.query.search);
    form.type = packageTypes.includes(queryValue(route.query.type)) ? route.query.type : '';
    form.sort = sortOptions.some((item) => item.value === route.query.sort) ? route.query.sort : 'latest';

    try {
        const result = await fetchPackages(Object.fromEntries(Object.entries({ search: form.search, type: form.type, sort: form.sort, page: Math.max(Number.parseInt(queryValue(route.query.page, '1'), 10) || 1, 1), per_page: 12 }).filter(([, value]) => value !== '')));
        if (sequence !== requestSequence) return;
        packages.value = result.items;
        meta.value = result.meta;
    } catch (requestError) {
        if (sequence !== requestSequence) return;
        error.value = requestError;
        packages.value = [];
        meta.value = null;
    } finally {
        if (sequence === requestSequence) loading.value = false;
    }
}

function applyFilters() {
    void router.push({ name: 'packages.index', query: Object.fromEntries(Object.entries({ search: form.search.trim(), type: form.type, sort: form.sort === 'latest' ? '' : form.sort }).filter(([, value]) => value !== '')) });
}
function resetFilters() { Object.assign(form, { search: '', type: '', sort: 'latest' }); void router.push({ name: 'packages.index' }); }
function changePage(page) { void router.push({ name: 'packages.index', query: { ...route.query, page: String(page) } }); }

watch(() => route.query, () => { void loadPackages(); }, { immediate: true, deep: true });
</script>

<template>
    <div class="min-h-screen bg-slate-50"><section class="border-b border-slate-200 bg-white"><div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8 lg:py-18"><p class="text-xs font-black tracking-[0.2em] text-brand uppercase">JCEC ACADEMY</p><h1 class="mt-4 max-w-3xl text-4xl font-black tracking-tight text-slate-950 sm:text-5xl">{{ t('catalog.packagesTitle') }}</h1><p class="mt-4 max-w-2xl text-base leading-8 text-slate-600">{{ t('catalog.packagesDescription') }}</p></div></section><div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8"><form class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" @submit.prevent="applyFilters"><div class="grid gap-4 md:grid-cols-[1.5fr_1fr_1fr_auto]"><label><span class="mb-2 block text-sm font-bold text-slate-700">{{ t('common.search') }}</span><input v-model="form.search" type="search" class="min-h-11 w-full rounded-xl border border-slate-300 px-4 outline-none focus:border-brand focus:ring-3 focus:ring-brand/15" :placeholder="t('catalog.searchPackages')"></label><label><span class="mb-2 block text-sm font-bold text-slate-700">{{ t('catalog.type') }}</span><select v-model="form.type" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 outline-none focus:border-brand focus:ring-3 focus:ring-brand/15"><option value="">{{ t('catalog.allTypes') }}</option><option v-for="type in packageTypes" :key="type" :value="type">{{ t(`labels.packageTypes.${type}`) }}</option></select></label><label><span class="mb-2 block text-sm font-bold text-slate-700">{{ t('catalog.sort') }}</span><select v-model="form.sort" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 outline-none focus:border-brand focus:ring-3 focus:ring-brand/15"><option v-for="option in sortOptions" :key="option.value" :value="option.value">{{ t(option.label) }}</option></select></label><div class="flex items-end gap-2"><button class="min-h-11 flex-1 rounded-xl bg-brand px-5 text-sm font-black text-white">{{ t('common.apply') }}</button><button type="button" class="min-h-11 rounded-xl border border-slate-300 px-4 text-sm font-bold text-slate-600" @click="resetFilters">{{ t('common.reset') }}</button></div></div></form><p class="mt-8 text-sm font-semibold text-slate-600">{{ resultSummary }}</p><BaseAlert v-if="error" tone="danger" class="mt-6">{{ t('catalog.loadError') }} <button type="button" class="font-black underline" @click="loadPackages">{{ t('common.retry') }}</button></BaseAlert><div v-if="loading" class="mt-6 grid gap-6 md:grid-cols-2 xl:grid-cols-3"><CatalogSkeleton v-for="index in 6" :key="index" /></div><div v-else-if="packages.length" class="mt-6 grid gap-6 md:grid-cols-2 xl:grid-cols-3"><PackageCard v-for="packageItem in packages" :key="packageItem.id" :package-item="packageItem" /></div><EmptyState v-else-if="!error" class="mt-6" :title="t('catalog.emptyPackages')" :description="t('catalog.emptyPackagesText')"><button type="button" class="rounded-xl bg-brand px-5 py-2.5 text-sm font-black text-white" @click="resetFilters">{{ t('common.reset') }}</button></EmptyState><PaginationNav v-if="!loading && !error && lastPage > 1" class="mt-10" :current-page="currentPage" :last-page="lastPage" @change="changePage" /></div></div>
</template>

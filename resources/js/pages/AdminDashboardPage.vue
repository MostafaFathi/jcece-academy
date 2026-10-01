<script setup>
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../stores/auth';
import { fetchAdminCategories, fetchAdminCourses } from '../api/admin';
import PageHeading from '../components/ui/PageHeading.vue';
import LoadingState from '../components/ui/LoadingState.vue';
import BaseAlert from '../components/ui/BaseAlert.vue';
import BaseButton from '../components/ui/BaseButton.vue';

const { t, locale } = useI18n();
const auth = useAuthStore();
const metrics = ref([]);
const loading = ref(true);
const error = ref(false);

async function load() {
    loading.value = true;
    error.value = false;
    const requests = [];
    if (auth.can('categories.view')) requests.push({ key: 'totalCategories', run: () => fetchAdminCategories(1) });
    if (auth.can('courses.view')) requests.push(...[
        { key: 'totalCourses', run: () => fetchAdminCourses({ per_page: 1 }) },
        { key: 'publishedCourses', run: () => fetchAdminCourses({ status: 'published', per_page: 1 }) },
        { key: 'draftCourses', run: () => fetchAdminCourses({ status: 'draft', per_page: 1 }) },
    ]);
    const results = await Promise.allSettled(requests.map((request) => request.run()));
    metrics.value = results.flatMap((result, index) => result.status === 'fulfilled' && Number.isInteger(result.value.meta?.total) ? [{ key: requests[index].key, value: result.value.meta.total }] : []);
    error.value = results.some((result) => result.status === 'rejected');
    loading.value = false;
}
onMounted(load);
</script>

<template>
    <div class="space-y-7" :dir="locale === 'ar' ? 'rtl' : 'ltr'"><PageHeading :title="t('admin.dashboard')" :description="t('admin.dashboardDescription')" /><LoadingState v-if="loading" />
        <BaseAlert v-else-if="error" tone="danger">{{ t('admin.summaryError') }} <BaseButton class="ms-2" variant="secondary" @click="load">{{ t('common.retry') }}</BaseButton></BaseAlert>
        <div v-if="!loading && metrics.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><article v-for="metric in metrics" :key="metric.key" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"><p class="text-sm font-bold text-slate-500">{{ t(`admin.${metric.key}`) }}</p><p class="mt-4 text-4xl font-black tabular-nums text-brand">{{ new Intl.NumberFormat(locale === 'ar' ? 'ar' : 'en').format(metric.value) }}</p></article></div>
        <BaseAlert v-if="!loading && !metrics.length">{{ t('admin.noMetrics') }}</BaseAlert>
        <BaseAlert v-if="!loading && (auth.can('users.view') || auth.can('instructors.view'))">{{ t('admin.unavailableFields') }}</BaseAlert>
        <div v-if="!loading" class="grid gap-4 md:grid-cols-2"><RouterLink v-if="auth.can('categories.view')" :to="{ name: 'admin.categories.index' }" class="rounded-3xl border border-slate-200 bg-white p-6 font-black text-brand shadow-sm hover:border-brand">{{ t('admin.categories') }} <span aria-hidden="true">↗</span></RouterLink><RouterLink v-if="auth.can('courses.view')" :to="{ name: 'admin.courses.index' }" class="rounded-3xl border border-slate-200 bg-white p-6 font-black text-brand shadow-sm hover:border-brand">{{ t('admin.courses') }} <span aria-hidden="true">↗</span></RouterLink></div>
    </div>
</template>

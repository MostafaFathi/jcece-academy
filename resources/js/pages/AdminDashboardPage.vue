<script setup>
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../stores/auth';
import { fetchAdminDashboardSummary } from '../api/admin';
import PageHeading from '../components/ui/PageHeading.vue';
import LoadingState from '../components/ui/LoadingState.vue';
import BaseAlert from '../components/ui/BaseAlert.vue';
import BaseButton from '../components/ui/BaseButton.vue';

const { t, locale } = useI18n();
const auth = useAuthStore();
const metrics = ref([]);
const sales = ref([]);
const topCourses = ref([]);
const loading = ref(true);
const error = ref(false);
const primaryMetricKeys = new Set(['totalCourses', 'publishedCourses', 'totalStudents', 'activeLearners']);
const primaryMetrics = computed(() => metrics.value.filter((metric) => primaryMetricKeys.has(metric.key)));
const otherMetrics = computed(() => metrics.value.filter((metric) => !primaryMetricKeys.has(metric.key)));

function formatCount(value) {
    return new Intl.NumberFormat(locale.value === 'ar' ? 'ar' : 'en').format(value);
}

async function load() {
    loading.value = true;
    error.value = false;
    const labels = { total_categories: 'totalCategories', total_courses: 'totalCourses', published_courses: 'publishedCourses', draft_courses: 'draftCourses', total_users: 'totalUsers', total_students: 'totalStudents', total_instructors: 'totalInstructors', total_packages: 'totalPackages', pending_payment_approvals: 'pendingPaymentApprovals', pending_refunds: 'pendingRefunds', active_learners: 'activeLearners', new_students: 'newStudents', cohort_enrollments: 'cohortEnrollments', cohort_completions: 'cohortCompletions' };
    try {
        const result = await fetchAdminDashboardSummary();
        metrics.value = Object.entries(result).filter(([key, value]) => labels[key] && Number.isInteger(value)).map(([key, value]) => ({ key: labels[key], value }));
        sales.value = result.operational_sales_last_30_days ?? [];
        topCourses.value = result.top_direct_courses ?? [];
    } catch { error.value = true; }
    finally { loading.value = false; }
}
onMounted(load);
</script>

<template>
    <div class="space-y-7" :dir="locale === 'ar' ? 'rtl' : 'ltr'"><PageHeading :title="t('admin.dashboard')" :description="t('admin.dashboardDescription')" /><LoadingState v-if="loading" />
        <BaseAlert v-else-if="error" tone="danger">{{ t('admin.summaryError') }} <BaseButton class="ms-2" variant="secondary" @click="load">{{ t('common.retry') }}</BaseButton></BaseAlert>
        <section v-if="!loading && primaryMetrics.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><article v-for="(metric, index) in primaryMetrics" :key="metric.key" class="relative overflow-hidden rounded-2xl border border-[#e9e1e4] bg-white p-6 shadow-[0_8px_28px_rgba(36,20,28,.055)]"><span class="absolute inset-x-0 top-0 h-1" :class="index === 0 ? 'bg-brand' : index === 1 ? 'bg-accent' : 'bg-[#aa8e99]'" /><div class="flex items-start justify-between gap-3"><p class="text-sm font-bold text-slate-500">{{ t(`admin.${metric.key}`) }}</p><span class="grid size-9 shrink-0 place-items-center rounded-xl bg-brand-soft text-xs font-black text-brand">0{{ index + 1 }}</span></div><p class="mt-5 text-4xl font-black tabular-nums tracking-tight text-[#30202a]">{{ formatCount(metric.value) }}</p></article></section>
        <section v-if="!loading && otherMetrics.length" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"><article v-for="metric in otherMetrics" :key="metric.key" class="flex items-center justify-between gap-4 rounded-2xl border border-[#ebe5e7] bg-white px-5 py-4 shadow-[0_3px_14px_rgba(36,20,28,.035)]"><p class="text-xs font-bold leading-5 text-slate-600">{{ t(`admin.${metric.key}`) }}</p><p class="shrink-0 text-xl font-black tabular-nums text-brand">{{ formatCount(metric.value) }}</p></article></section>
        <div v-if="!loading && (sales.length || topCourses.length)" class="grid gap-5 xl:grid-cols-2"><section v-if="sales.length" class="rounded-2xl border border-[#e9e1e4] bg-white p-6 shadow-[0_6px_24px_rgba(36,20,28,.045)]"><div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-lg font-black text-[#30202a]">{{ t('reports.net_sales') }}</h2><RouterLink :to="{ name: 'admin.reports' }" class="rounded-lg px-3 py-2 text-xs font-extrabold text-brand transition hover:bg-brand-soft">{{ t('reports.title') }} →</RouterLink></div><div class="mt-5 grid gap-3 sm:grid-cols-2"><article v-for="group in sales" :key="group.currency" class="rounded-xl border border-[#eee7e9] bg-[#fcf9fa] p-4"><p class="text-xs font-bold text-slate-500">{{ group.currency }} · 30 {{ t('reports.days') }}</p><p class="mt-3 text-2xl font-black tabular-nums text-brand">{{ group.net_sales }}</p></article></div></section><section v-if="topCourses.length" class="rounded-2xl border border-[#e9e1e4] bg-white p-6 shadow-[0_6px_24px_rgba(36,20,28,.045)]"><h2 class="text-lg font-black text-[#30202a]">{{ t('admin.topDirectCourses') }}</h2><ol class="mt-4 divide-y divide-[#f0ecee]"><li v-for="(course, index) in topCourses" :key="course.course_id" class="flex items-center gap-3 py-3"><span class="grid size-8 shrink-0 place-items-center rounded-lg bg-brand-soft text-xs font-black text-brand">{{ index + 1 }}</span><span class="min-w-0 flex-1 truncate text-sm font-semibold text-slate-700">{{ course.title }}</span><strong class="rounded-lg bg-[#f7f4f5] px-2.5 py-1 text-sm tabular-nums text-brand">{{ formatCount(course.purchases) }}</strong></li></ol></section></div>
        <BaseAlert v-if="!loading && !error && !metrics.length">{{ t('admin.noMetrics') }}</BaseAlert>
        <div v-if="!loading" class="grid gap-3 md:grid-cols-2 xl:grid-cols-3"><RouterLink v-if="auth.can('categories.view')" :to="{ name: 'admin.categories.index' }" class="group flex items-center justify-between rounded-2xl border border-[#e9e1e4] bg-white px-5 py-4 font-extrabold text-brand shadow-sm transition hover:-translate-y-0.5 hover:border-brand/30 hover:shadow-md">{{ t('admin.categories') }} <span aria-hidden="true" class="text-lg transition group-hover:-translate-x-1">↗</span></RouterLink><RouterLink v-if="auth.can('courses.view')" :to="{ name: 'admin.courses.index' }" class="group flex items-center justify-between rounded-2xl border border-[#e9e1e4] bg-white px-5 py-4 font-extrabold text-brand shadow-sm transition hover:-translate-y-0.5 hover:border-brand/30 hover:shadow-md">{{ t('admin.courses') }} <span aria-hidden="true" class="text-lg transition group-hover:-translate-x-1">↗</span></RouterLink><RouterLink v-if="auth.can('packages.view')" :to="{ name: 'admin.packages.index' }" class="group flex items-center justify-between rounded-2xl border border-[#e9e1e4] bg-white px-5 py-4 font-extrabold text-brand shadow-sm transition hover:-translate-y-0.5 hover:border-brand/30 hover:shadow-md">{{ t('packages.adminHeading') }} <span aria-hidden="true" class="text-lg transition group-hover:-translate-x-1">↗</span></RouterLink><RouterLink v-if="auth.can('users.view')" :to="{ name: 'admin.users.index' }" class="group flex items-center justify-between rounded-2xl border border-[#e9e1e4] bg-white px-5 py-4 font-extrabold text-brand shadow-sm transition hover:-translate-y-0.5 hover:border-brand/30 hover:shadow-md">{{ t('admin.users') }} <span aria-hidden="true" class="text-lg transition group-hover:-translate-x-1">↗</span></RouterLink><RouterLink v-if="auth.can('instructors.view')" :to="{ name: 'admin.instructors.index' }" class="group flex items-center justify-between rounded-2xl border border-[#e9e1e4] bg-white px-5 py-4 font-extrabold text-brand shadow-sm transition hover:-translate-y-0.5 hover:border-brand/30 hover:shadow-md">{{ t('admin.instructors') }} <span aria-hidden="true" class="text-lg transition group-hover:-translate-x-1">↗</span></RouterLink></div>
    </div>
</template>

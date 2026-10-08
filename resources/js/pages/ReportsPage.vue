<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../stores/auth';
import { exportReport, fetchReport } from '../api/reports';
import { fetchAdminCourse, fetchAdminCourses } from '../api/admin';
import { fetchAdminPackage, fetchAdminPackages } from '../api/admin-packages';
import { fetchAdminInstructor, fetchAdminInstructors } from '../api/admin-instructors';
import { fetchInstructorCourse, fetchInstructorCourses } from '../api/instructor';
import PageHeading from '../components/ui/PageHeading.vue';
import LoadingState from '../components/ui/LoadingState.vue';
import BaseAlert from '../components/ui/BaseAlert.vue';
import AsyncResourceSelect from '../components/ui/AsyncResourceSelect.vue';

const props = defineProps({ scope: { type: String, required: true } });
const { t, locale } = useI18n();
const auth = useAuthStore();
const adminTypes = ['sales', 'payments', 'refunds', 'courses', 'learners', 'instructors', 'reviews', 'quizzes', 'packages', 'coupons'];
const instructorTypes = ['courses', 'learners', 'reviews', 'quizzes'];
const types = computed(() => props.scope === 'admin' ? adminTypes : instructorTypes);
const courseLoader = computed(() => props.scope === 'admin' ? fetchAdminCourses : fetchInstructorCourses);
const courseResolver = computed(() => props.scope === 'admin' ? fetchAdminCourse : fetchInstructorCourse);
const selected = ref(props.scope === 'admin' ? 'sales' : 'courses');
const filters = reactive({ from: '', to: '', course_id: '', package_id: '', instructor_id: '', currency: '', status: '', method: '', period: '' });
const report = ref(null);
const appliedFilters = ref({});
const loading = ref(false);
const error = ref('');
const exporting = ref(false);
const exportError = ref(false);
const columns = {
    sales: [['order_number', 'order_number'], ['paid_at', 'paid_at'], ['currency', 'currencyField'], ['total', 'total'], ['discount_total', 'discount_total'], ['coupon_code_snapshot', 'coupon_code_snapshot']],
    payments: [['order_number', 'order_number'], ['method', 'method'], ['status', 'status'], ['amount', 'amount'], ['currency', 'currencyField'], ['created_at', 'created_at']],
    refunds: [['order_number', 'order_number'], ['status', 'status'], ['amount', 'amount'], ['currency', 'currencyField'], ['created_at', 'created_at'], ['processed_at', 'processed_at']],
    courses: [['title', 'titleField'], ['instructor_name', 'instructor_name'], ['enrollments', 'enrollments'], ['learners', 'learnersField'], ['completions', 'completions'], ['completion_percentage', 'completion_percentage'], ['package_access_learners', 'package_access_learners'], ['direct_sales', 'direct_sales'], ['rating_average', 'rating_average']],
    learners: [['learner_name', 'learner_name'], ['course_title', 'course_title'], ['status', 'status'], ['access_source', 'access_source'], ['access_active', 'access_active'], ['progress_percentage', 'progress_percentage'], ['completed_at', 'completed_at']],
    instructors: [['name', 'name'], ['courses', 'coursesField'], ['learners', 'learnersField'], ['enrollments', 'enrollments']],
    reviews: [['course_title', 'course_title'], ['rating_count', 'rating_count'], ['rating_average', 'rating_average'], ['five_star_count', 'five_star_count']],
    quizzes: [['course_title', 'course_title'], ['quiz_title', 'quiz_title'], ['attempts', 'attempts'], ['passed', 'passed'], ['pass_rate_percentage', 'pass_rate_percentage'], ['average_percentage', 'average_percentage']],
    packages: [['package_title', 'package_title'], ['currency', 'currencyField'], ['purchases', 'purchases'], ['learners', 'learnersField'], ['gross_sales', 'gross_sales_row'], ['discounts', 'discounts']],
    coupons: [['coupon_code', 'coupon_code'], ['currency', 'currencyField'], ['redemptions', 'redemptions'], ['coupon_discount', 'coupon_discount']],
};
const rows = computed(() => report.value?.data?.rows?.data ?? []);
const pagination = computed(() => report.value?.data?.rows ?? null);
const salesSummary = computed(() => selected.value === 'sales' ? (report.value?.data?.summary ?? []) : []);
let requestSequence = 0;

async function load(page = 1) {
    const sequence = ++requestSequence;
    const requestedFilters = { ...filters, currency: filters.currency.toUpperCase(), page };
    filters.currency = requestedFilters.currency;
    loading.value = true;
    error.value = '';
    report.value = null;
    try {
        const result = await fetchReport(props.scope, selected.value, requestedFilters);
        if (sequence === requestSequence) {
            report.value = result;
            appliedFilters.value = requestedFilters;
        }
    } catch {
        if (sequence === requestSequence) error.value = t('reports.loadError');
    } finally {
        if (sequence === requestSequence) loading.value = false;
    }
}

function select(type) {
    selected.value = type;
    filters.course_id = '';
    filters.package_id = '';
    filters.instructor_id = '';
    filters.currency = '';
    filters.status = '';
    filters.method = '';
    filters.period = '';
    load();
}

async function download(format) {
    exporting.value = true;
    exportError.value = false;
    try {
        await exportReport(selected.value, format, appliedFilters.value);
    } catch {
        exportError.value = true;
    } finally {
        exporting.value = false;
    }
}

function cell(row, key) {
    const value = row[key];
    if (typeof value === 'boolean') return value ? t('common.yes') : t('common.no');
    if (key === 'status' && value) return t(`reports.statuses.${value}`);
    if (key === 'method' && value) return t(`reports.methods.${value}`);
    if (key === 'access_source' && value) return t(`reports.sources.${value}`);
    if (key === 'direct_sales' && Array.isArray(value)) return value.map((item) => `${item.currency}: ${item.direct_gross_sales}`).join(' · ') || '—';
    return value === null || value === undefined || value === '' ? '—' : value;
}

onMounted(() => load());
</script>

<template>
    <div class="space-y-7" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <PageHeading :title="t('reports.title')" :description="t('reports.intro')" />
        <nav class="flex flex-wrap gap-2" :aria-label="t('reports.title')">
            <button v-for="type in types" :key="type" type="button" class="min-h-11 rounded-xl border px-4 py-2 text-sm font-bold transition focus-visible:outline-3 focus-visible:outline-accent" :class="selected === type ? 'border-brand bg-brand text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-brand'" :aria-current="selected === type ? 'page' : undefined" @click="select(type)">{{ t(`reports.${type}`) }}</button>
        </nav>
        <form class="grid gap-4 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-2 xl:grid-cols-4" @submit.prevent="load()">
            <label class="grid gap-1 text-sm font-semibold text-slate-700">{{ t('reports.from') }}<input v-model="filters.from" type="date" class="min-h-11 rounded-xl border border-slate-300 px-3 focus-visible:outline-3 focus-visible:outline-accent" /></label>
            <label class="grid gap-1 text-sm font-semibold text-slate-700">{{ t('reports.to') }}<input v-model="filters.to" type="date" :min="filters.from || undefined" class="min-h-11 rounded-xl border border-slate-300 px-3 focus-visible:outline-3 focus-visible:outline-accent" /></label>
            <AsyncResourceSelect v-if="['courses', 'learners', 'reviews', 'quizzes'].includes(selected)" v-model="filters.course_id" id="report-course" :loader="courseLoader" :resolver="courseResolver" :label="t('operations.course')" />
            <AsyncResourceSelect v-if="selected === 'packages'" v-model="filters.package_id" id="report-package" :loader="fetchAdminPackages" :resolver="fetchAdminPackage" :label="t('common.package')" />
            <AsyncResourceSelect v-if="scope === 'admin' && ['courses', 'learners', 'instructors', 'reviews', 'quizzes'].includes(selected)" v-model="filters.instructor_id" id="report-instructor" :loader="fetchAdminInstructors" :resolver="fetchAdminInstructor" :label="t('admin.instructor')" />
            <label v-if="['sales', 'payments', 'refunds', 'packages', 'coupons'].includes(selected)" class="grid gap-1 text-sm font-semibold text-slate-700">{{ t('reports.currency') }}<input v-model="filters.currency" type="text" minlength="3" maxlength="3" pattern="[A-Za-z]{3}" dir="ltr" class="min-h-11 rounded-xl border border-slate-300 px-3 uppercase focus-visible:outline-3 focus-visible:outline-accent" /></label>
            <label v-if="selected === 'sales'" class="grid gap-1 text-sm font-semibold text-slate-700">{{ t('reports.period') }}<select v-model="filters.period" class="min-h-11 rounded-xl border border-slate-300 px-3 focus-visible:outline-3 focus-visible:outline-accent"><option value="month">{{ t('reports.month') }}</option><option value="day">{{ t('reports.day') }}</option></select></label>
            <label v-if="['payments', 'refunds'].includes(selected)" class="grid gap-1 text-sm font-semibold text-slate-700">{{ t('reports.status') }}<select v-model="filters.status" class="min-h-11 rounded-xl border border-slate-300 px-3 focus-visible:outline-3 focus-visible:outline-accent"><option value="">{{ t('reports.all') }}</option><option v-for="status in selected === 'payments' ? ['pending_review', 'paid', 'rejected'] : ['pending', 'completed', 'rejected']" :key="status" :value="status">{{ t(`reports.statuses.${status}`) }}</option></select></label>
            <label v-if="selected === 'payments'" class="grid gap-1 text-sm font-semibold text-slate-700">{{ t('reports.method') }}<select v-model="filters.method" class="min-h-11 rounded-xl border border-slate-300 px-3 focus-visible:outline-3 focus-visible:outline-accent"><option value="">{{ t('reports.all') }}</option><option v-for="method in ['bank_transfer', 'wallet', 'manual']" :key="method" :value="method">{{ t(`reports.methods.${method}`) }}</option></select></label>
            <div class="flex items-end"><button type="submit" class="min-h-11 rounded-xl bg-brand px-5 py-2 font-bold text-white hover:bg-brand-dark">{{ t('reports.apply') }}</button></div>
        </form>
        <p class="text-xs text-slate-500">{{ t('reports.timezone') }}</p>
        <div v-if="scope === 'admin' && auth.can('reports.export')" class="flex flex-wrap items-center gap-2">
            <span class="text-sm font-bold text-slate-700">{{ t('reports.export') }}:</span>
            <button v-for="format in ['csv', 'xlsx', 'pdf']" :key="format" type="button" :disabled="loading || exporting || !report" class="min-h-11 rounded-xl border border-brand px-4 py-2 text-sm font-bold uppercase text-brand disabled:opacity-50" @click="download(format)">{{ format }}</button>
        </div>
        <BaseAlert v-if="exportError" tone="danger">{{ t('reports.exportError') }}</BaseAlert>
        <LoadingState v-if="loading" />
        <BaseAlert v-else-if="error" tone="danger">{{ error }} <button type="button" class="ms-2 font-bold underline" @click="load()">{{ t('common.retry') }}</button></BaseAlert>
        <template v-else-if="report">
            <div v-for="group in salesSummary" :key="group.currency" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <article v-for="key in ['gross_sales', 'discounts', 'refunds', 'net_sales', 'order_count', 'zero_total_orders', 'average_order_value']" :key="key" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-sm text-slate-500">{{ t(`reports.${key}`) }} · {{ group.currency }}</p><p class="mt-2 text-2xl font-black text-brand">{{ group[key] }}</p></article>
            </div>
            <section v-if="selected === 'sales' && report.data.trend?.length" class="overflow-x-auto rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="mb-4 font-black text-brand">{{ t('reports.trend') }}</h2><table class="min-w-full text-sm"><thead><tr><th v-for="key in ['period', 'currency', 'gross_sales', 'discounts', 'refunds', 'net_sales']" :key="key" class="px-3 py-2 text-start">{{ t(`reports.${key}`) }}</th></tr></thead><tbody><tr v-for="bucket in report.data.trend" :key="`${bucket.period}-${bucket.currency}`" class="border-t"><td v-for="key in ['period', 'currency', 'gross_sales', 'discounts', 'refunds', 'net_sales']" :key="key" class="px-3 py-2">{{ bucket[key] }}</td></tr></tbody></table></section>
            <div v-if="['payments', 'refunds'].includes(selected) && report.data.summary.length" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4"><article v-for="group in report.data.summary" :key="`${group.currency}-${group.status}`" class="rounded-2xl border border-slate-200 bg-white p-4"><p class="text-sm text-slate-500">{{ t(`reports.statuses.${group.status}`) }} · {{ group.currency }}</p><p class="mt-2 text-2xl font-black text-brand">{{ group.count }} · {{ group.amount }}</p></article></div>
            <div v-if="rows.length" class="overflow-x-auto rounded-3xl border border-slate-200 bg-white shadow-sm"><table class="min-w-full text-sm"><thead class="bg-[#faf8f8] text-slate-700"><tr><th v-for="[key, label] in columns[selected]" :key="key" scope="col" class="whitespace-nowrap border-b border-[#ebe5e7] px-4 py-3 text-start">{{ t(`reports.${label}`) }}</th></tr></thead><tbody><tr v-for="(row, index) in rows" :key="row.id ?? `${selected}-${index}`" class="border-t border-slate-100"><td v-for="[key] in columns[selected]" :key="key" class="whitespace-nowrap px-4 py-3 text-slate-700">{{ cell(row, key) }}</td></tr></tbody></table></div>
            <BaseAlert v-else>{{ t('reports.noData') }}</BaseAlert>
            <div v-if="pagination && pagination.last_page > 1" class="flex items-center justify-between gap-3"><button type="button" class="min-h-11 rounded-xl border px-4" :disabled="pagination.current_page <= 1" @click="load(pagination.current_page - 1)">{{ t('common.previous') }}</button><span>{{ t('common.page', { current: pagination.current_page, total: pagination.last_page }) }}</span><button type="button" class="min-h-11 rounded-xl border px-4" :disabled="pagination.current_page >= pagination.last_page" @click="load(pagination.current_page + 1)">{{ t('common.next') }}</button></div>
        </template>
    </div>
</template>

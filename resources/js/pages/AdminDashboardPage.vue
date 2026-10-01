<script setup>
import { onMounted, ref } from 'vue';
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
const loading = ref(true);
const error = ref(false);

async function load() {
    loading.value = true;
    error.value = false;
    const labels = { total_categories: 'totalCategories', total_courses: 'totalCourses', published_courses: 'publishedCourses', draft_courses: 'draftCourses', total_users: 'totalUsers', total_students: 'totalStudents', total_instructors: 'totalInstructors', total_packages: 'totalPackages' };
    try {
        const result = await fetchAdminDashboardSummary();
        metrics.value = Object.entries(result).filter(([key, value]) => labels[key] && Number.isInteger(value)).map(([key, value]) => ({ key: labels[key], value }));
    } catch { error.value = true; }
    finally { loading.value = false; }
}
onMounted(load);
</script>

<template>
    <div class="space-y-7" :dir="locale === 'ar' ? 'rtl' : 'ltr'"><PageHeading :title="t('admin.dashboard')" :description="t('admin.dashboardDescription')" /><LoadingState v-if="loading" />
        <BaseAlert v-else-if="error" tone="danger">{{ t('admin.summaryError') }} <BaseButton class="ms-2" variant="secondary" @click="load">{{ t('common.retry') }}</BaseButton></BaseAlert>
        <div v-if="!loading && metrics.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"><article v-for="metric in metrics" :key="metric.key" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"><p class="text-sm font-bold text-slate-500">{{ t(`admin.${metric.key}`) }}</p><p class="mt-4 text-4xl font-black tabular-nums text-brand">{{ new Intl.NumberFormat(locale === 'ar' ? 'ar' : 'en').format(metric.value) }}</p></article></div>
        <BaseAlert v-if="!loading && !error && !metrics.length">{{ t('admin.noMetrics') }}</BaseAlert>
        <div v-if="!loading" class="grid gap-4 md:grid-cols-2"><RouterLink v-if="auth.can('categories.view')" :to="{ name: 'admin.categories.index' }" class="rounded-3xl border border-slate-200 bg-white p-6 font-black text-brand shadow-sm hover:border-brand">{{ t('admin.categories') }} <span aria-hidden="true">↗</span></RouterLink><RouterLink v-if="auth.can('courses.view')" :to="{ name: 'admin.courses.index' }" class="rounded-3xl border border-slate-200 bg-white p-6 font-black text-brand shadow-sm hover:border-brand">{{ t('admin.courses') }} <span aria-hidden="true">↗</span></RouterLink><RouterLink v-if="auth.can('packages.view')" :to="{ name: 'admin.packages.index' }" class="rounded-3xl border border-slate-200 bg-white p-6 font-black text-brand shadow-sm hover:border-brand">{{ t('packages.adminHeading') }} <span aria-hidden="true">↗</span></RouterLink><RouterLink v-if="auth.can('users.view')" :to="{ name: 'admin.users.index' }" class="rounded-3xl border border-slate-200 bg-white p-6 font-black text-brand shadow-sm hover:border-brand">{{ t('admin.users') }} <span aria-hidden="true">↗</span></RouterLink><RouterLink v-if="auth.can('instructors.view')" :to="{ name: 'admin.instructors.index' }" class="rounded-3xl border border-slate-200 bg-white p-6 font-black text-brand shadow-sm hover:border-brand">{{ t('admin.instructors') }} <span aria-hidden="true">↗</span></RouterLink></div>
    </div>
</template>

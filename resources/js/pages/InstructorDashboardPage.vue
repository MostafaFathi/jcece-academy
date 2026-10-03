<script setup>
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { fetchInstructorSummary } from '../api/instructor';
import PageHeading from '../components/ui/PageHeading.vue';
import LoadingState from '../components/ui/LoadingState.vue';
import BaseAlert from '../components/ui/BaseAlert.vue';

const { t, locale } = useI18n();
const summary = ref(null);
const loading = ref(true);
const error = ref(null);

async function load() {
    loading.value = true;
    error.value = null;
    try { summary.value = await fetchInstructorSummary(); }
    catch (failure) { error.value = failure; }
    finally { loading.value = false; }
}
onMounted(load);
</script>

<template>
    <div class="space-y-6" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <PageHeading :title="t('instructor.dashboard')" :description="t('instructor.dashboardIntro')" />
        <LoadingState v-if="loading" />
        <BaseAlert v-else-if="error" tone="danger">{{ t('instructor.loadError') }} <button type="button" class="font-bold underline" @click="load">{{ t('instructor.retry') }}</button></BaseAlert>
        <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article v-for="metric in [['courses', 'totalCourses'], ['published_courses', 'publishedCourses'], ['draft_courses', 'draftCourses'], ['awaiting_grading', 'awaitingGrading']]" :key="metric[0]" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-sm font-bold text-slate-500">{{ t(`instructor.${metric[1]}`) }}</p>
                <p class="mt-4 text-4xl font-black text-brand">{{ summary[metric[0]] }}</p>
            </article>
        </div>
        <RouterLink :to="{ name: 'instructor.courses.index' }" class="inline-flex min-h-11 items-center rounded-xl bg-brand px-5 py-2.5 font-bold text-white">{{ t('instructor.myCourses') }}</RouterLink>
    </div>
</template>

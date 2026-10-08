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
const metrics = [['courses', 'totalCourses'], ['published_courses', 'publishedCourses'], ['draft_courses', 'draftCourses'], ['awaiting_grading', 'awaitingGrading']];

function formatCount(value) {
    return new Intl.NumberFormat(locale.value === 'ar' ? 'ar' : 'en').format(value ?? 0);
}

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
        <template v-else>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article v-for="(metric, index) in metrics" :key="metric[0]" class="relative overflow-hidden rounded-2xl border border-[#e9e1e4] bg-white p-6 shadow-[0_8px_28px_rgba(36,20,28,.055)]">
                    <span class="absolute inset-x-0 top-0 h-1" :class="index === 0 ? 'bg-brand' : index === 1 ? 'bg-accent' : 'bg-[#aa8e99]'" />
                    <div class="flex items-start justify-between gap-3"><p class="text-sm font-bold text-slate-500">{{ t(`instructor.${metric[1]}`) }}</p><span class="grid size-9 shrink-0 place-items-center rounded-xl bg-brand-soft text-xs font-black text-brand">0{{ index + 1 }}</span></div>
                    <p class="mt-5 text-4xl font-black tabular-nums tracking-tight text-[#30202a]">{{ formatCount(summary[metric[0]]) }}</p>
                </article>
            </div>
            <RouterLink :to="{ name: 'instructor.courses.index' }" class="group relative flex flex-wrap items-center justify-between gap-5 overflow-hidden rounded-2xl bg-[linear-gradient(110deg,#4f1425,#7b203a)] p-6 text-white shadow-[0_12px_30px_rgba(107,29,50,.18)] transition hover:-translate-y-0.5 hover:shadow-[0_16px_36px_rgba(107,29,50,.24)] focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-brand sm:p-8"><span class="pointer-events-none absolute -end-10 -top-20 size-52 rounded-full border-[28px] border-white/[0.06]" /><span class="relative"><strong class="block text-xl font-black">{{ t('instructor.myCourses') }}</strong><span class="mt-2 block max-w-xl text-sm leading-6 text-white/70">{{ t('instructor.coursesIntro') }}</span></span><span aria-hidden="true" class="relative grid size-11 place-items-center rounded-xl bg-accent text-xl font-black text-brand-dark transition group-hover:-translate-x-1">{{ locale === 'ar' ? '←' : '→' }}</span></RouterLink>
        </template>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { fetchQuizzes, fetchQuizAttempts, fetchAssignments, fetchAssignmentSubmissions, fetchCertificateEligibility, issueCertificate, downloadCertificate } from '../../api/assessments';
import BaseButton from '../ui/BaseButton.vue';
import { formatDate } from '../../utils/catalog';

const props = defineProps({ courseId: { type: Number, required: true }, slug: { type: String, required: true }, lessonId: { type: [Number, null], default: null }, completedLessons: { type: Number, default: 0 } });
const emit = defineEmits(['access-lost']);
const { t, locale } = useI18n();
const quizzes = ref([]);
const assignments = ref([]);
const quizAttempts = ref({});
const assignmentSubmissions = ref({});
const eligibility = ref(null);
const loading = ref(true);
const busy = ref(false);
const error = ref(null);
const issuedCertificate = ref(null);
let generation = 0;
const lessonItems = computed(() => [...quizzes.value.map((item) => ({ ...item, kind: 'quiz' })), ...assignments.value.map((item) => ({ ...item, kind: 'assignment' }))].filter((item) => item.lesson_id === props.lessonId));
const courseItems = computed(() => [...quizzes.value.map((item) => ({ ...item, kind: 'quiz' })), ...assignments.value.map((item) => ({ ...item, kind: 'assignment' }))].filter((item) => item.lesson_id == null));
function attemptsFor(item) { return item.kind === 'quiz' ? quizAttempts.value[item.id] || [] : assignmentSubmissions.value[item.id] || []; }
function statusFor(item) {
    const attempts = attemptsFor(item);
    if (attempts.some((record) => record.status === 'draft' || record.status === 'in_progress')) return attempts.find((record) => record.status === 'draft' || record.status === 'in_progress').status;
    return attempts[0]?.status || null;
}
function limitReached(item) { return item.max_attempts !== null && attemptsFor(item).length >= item.max_attempts && !['draft', 'in_progress'].includes(statusFor(item)); }
async function load() {
    const current = ++generation;
    loading.value = true; error.value = null; quizzes.value = []; assignments.value = []; eligibility.value = null;
    try {
        const [availableQuizzes, availableAssignments, certificateEligibility] = await Promise.all([fetchQuizzes(), fetchAssignments(), fetchCertificateEligibility(props.slug)]);
        if (current !== generation) return;
        quizzes.value = availableQuizzes.filter((item) => item.course_id === props.courseId);
        assignments.value = availableAssignments.filter((item) => item.course_id === props.courseId);
        eligibility.value = certificateEligibility;
        const [qHistory, aHistory] = await Promise.all([
            Promise.all(quizzes.value.map(async (item) => [item.id, await fetchQuizAttempts(item.id)])),
            Promise.all(assignments.value.map(async (item) => [item.id, await fetchAssignmentSubmissions(item.id)])),
        ]);
        if (current !== generation) return;
        quizAttempts.value = Object.fromEntries(qHistory);
        assignmentSubmissions.value = Object.fromEntries(aHistory);
    } catch (failure) {
        if (current !== generation) return;
        error.value = failure;
        quizzes.value = []; assignments.value = []; quizAttempts.value = {}; assignmentSubmissions.value = {}; eligibility.value = null;
        if ([401, 403].includes(failure.status)) emit('access-lost');
    } finally { if (current === generation) loading.value = false; }
}
watch(() => [props.courseId, props.slug, props.completedLessons], load, { immediate: true });
async function issue() {
    if (busy.value || !eligibility.value?.eligible || eligibility.value?.certificate_status === 'issued') return;
    busy.value = true; error.value = null;
    try {
        issuedCertificate.value = await issueCertificate(props.slug);
        eligibility.value = await fetchCertificateEligibility(props.slug);
    } catch (failure) { error.value = failure; if ([401, 403].includes(failure.status)) emit('access-lost'); }
    finally { busy.value = false; }
}
async function download() {
    if (busy.value || !issuedCertificate.value) return;
    busy.value = true; error.value = null;
    try { await downloadCertificate(issuedCertificate.value.id); }
    catch (failure) { error.value = failure; if ([401, 403].includes(failure.status)) emit('access-lost'); }
    finally { busy.value = false; }
}
</script>
<template>
    <section class="space-y-5 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7"><h2 class="text-xl font-black">{{ t('assessments.courseAssessments') }}</h2><p v-if="loading" role="status" class="text-sm text-slate-500">{{ t('common.loading') }}</p><p v-if="error" role="alert" class="text-sm text-red-700">{{ t('assessments.requestFailed') }} <BaseButton variant="secondary" class="mt-2" @click="load">{{ t('common.retry') }}</BaseButton></p>
        <template v-if="!loading && !error"><p v-if="!lessonItems.length && !courseItems.length" class="text-sm text-slate-500">{{ t('assessments.noAssessments') }}</p><div v-if="lessonItems.length" class="space-y-3"><h3 class="font-bold">{{ t('assessments.lessonAssessments') }}</h3><article v-for="item in lessonItems" :key="`${item.kind}-${item.id}`" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-slate-50 p-4"><div><p class="text-xs font-bold text-brand">{{ t(item.kind === 'quiz' ? 'assessments.quiz' : 'assessments.assignment') }}</p><h4 class="font-bold">{{ item.title }}</h4><p class="mt-1 text-xs text-slate-500">{{ statusFor(item) ? t(`assessments.status.${statusFor(item)}`) : (limitReached(item) ? t('assessments.limitReached') : t('assessments.start')) }}</p></div><RouterLink :to="{ name: item.kind === 'quiz' ? 'student.quizzes.show' : 'student.assignments.show', params: { id: item.id } }" class="inline-flex min-h-11 items-center rounded-xl border border-brand px-4 py-2 text-sm font-bold text-brand">{{ t('assessments.open') }}</RouterLink></article></div><div v-if="courseItems.length" class="space-y-3"><h3 class="font-bold">{{ t('assessments.courseAssessments') }}</h3><article v-for="item in courseItems" :key="`${item.kind}-${item.id}`" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-slate-50 p-4"><div><p class="text-xs font-bold text-brand">{{ t(item.kind === 'quiz' ? 'assessments.quiz' : 'assessments.assignment') }}</p><h4 class="font-bold">{{ item.title }}</h4><p v-if="item.due_at" class="mt-1 text-xs text-slate-500">{{ t('assessments.due', { date: formatDate(item.due_at, locale) }) }}</p><p v-if="statusFor(item)" class="mt-1 text-xs text-slate-500">{{ t(`assessments.status.${statusFor(item)}`) }}</p></div><RouterLink :to="{ name: item.kind === 'quiz' ? 'student.quizzes.show' : 'student.assignments.show', params: { id: item.id } }" class="inline-flex min-h-11 items-center rounded-xl border border-brand px-4 py-2 text-sm font-bold text-brand">{{ t('assessments.open') }}</RouterLink></article></div>
            <div v-if="eligibility" class="border-t border-slate-100 pt-5"><h3 class="font-black">{{ t('assessments.certificates') }}</h3><p class="mt-2 text-sm font-bold" :class="eligibility.eligible ? 'text-emerald-700' : 'text-slate-600'">{{ t(eligibility.certificate_status === 'issued' || issuedCertificate ? 'assessments.alreadyIssued' : eligibility.certificate_status === 'revoked' ? 'assessments.revoked' : eligibility.eligible ? 'assessments.eligible' : 'assessments.ineligible') }}</p><ul v-if="!eligibility.eligible" class="mt-2 list-inside list-disc text-sm text-slate-600"><li v-for="reason in eligibility.reasons" :key="reason">{{ t(`assessments.reasons.${reason}`) }}</li></ul><div class="mt-4 flex flex-wrap gap-3"><BaseButton v-if="eligibility.eligible && eligibility.certificate_status !== 'issued' && eligibility.certificate_status !== 'revoked' && !issuedCertificate" :loading="busy" @click="issue">{{ t('assessments.requestCertificate') }}</BaseButton><BaseButton v-if="issuedCertificate" variant="secondary" :loading="busy" @click="download">{{ t('assessments.download') }} PDF</BaseButton><RouterLink :to="{ name: 'student.certificates.index' }" class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold">{{ t('assessments.certificates') }}</RouterLink></div></div>
        </template>
    </section>
</template>

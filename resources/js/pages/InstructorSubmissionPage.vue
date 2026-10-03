<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { fetchInstructorSubmission, gradeInstructorSubmission, requestInstructorRevision, correctInstructorGrade, downloadInstructorSubmissionFile } from '../api/instructor';
import { validScore } from '../utils/grading';
import { formatDate } from '../utils/catalog';
import PageHeading from '../components/ui/PageHeading.vue';
import LoadingState from '../components/ui/LoadingState.vue';
import BaseAlert from '../components/ui/BaseAlert.vue';

const route = useRoute();
const { t, locale } = useI18n();
const submission = ref(null);
const loading = ref(true);
const busy = ref(false);
const error = ref(null);
const actionError = ref(null);
const formError = ref('');
const saved = ref(false);
const downloadingId = ref(null);
const grade = reactive({ score: '', feedback: '' });
const revision = reactive({ feedback: '' });
const correction = reactive({ score: '', feedback: '', reason: '' });

async function refresh() {
    submission.value = await fetchInstructorSubmission(route.params.courseId, route.params.assignmentId, route.params.submissionId);
    grade.score = submission.value.score ?? '';
    grade.feedback = submission.value.feedback ?? '';
    correction.score = submission.value.score ?? '';
    correction.feedback = submission.value.feedback ?? '';
}
async function load() { loading.value = true; error.value = null; try { await refresh(); } catch (failure) { error.value = failure; } finally { loading.value = false; } }
async function act(operation) {
    if (busy.value) return;
    busy.value = true;
    actionError.value = null;
    formError.value = '';
    saved.value = false;
    try { await operation(); await refresh(); saved.value = true; }
    catch (failure) { actionError.value = failure; }
    finally { busy.value = false; }
}
function submitGrade() {
    if (!validScore(grade.score, submission.value.maximum_score)) { formError.value = t('instructor.scoreError'); return; }
    act(() => gradeInstructorSubmission(submission.value.id, { score: grade.score, feedback: grade.feedback || null }));
}
function submitRevision() {
    if (!revision.feedback.trim()) { formError.value = t('instructor.feedbackRequired'); return; }
    act(() => requestInstructorRevision(submission.value.id, { feedback: revision.feedback.trim() }));
}
function submitCorrection() {
    if (!validScore(correction.score, submission.value.maximum_score)) { formError.value = t('instructor.scoreError'); return; }
    if (!correction.reason.trim()) { formError.value = t('instructor.reasonRequired'); return; }
    if (!window.confirm(t('instructor.correctionConfirm'))) return;
    act(() => correctInstructorGrade(submission.value.id, { score: correction.score, feedback: correction.feedback || null, reason: correction.reason.trim() }));
}
async function download(file) {
    if (downloadingId.value !== null) return;
    downloadingId.value = file.id;
    actionError.value = null;
    try { await downloadInstructorSubmissionFile(file.id, file.original_filename); }
    catch (failure) { actionError.value = failure; }
    finally { downloadingId.value = null; }
}
onMounted(load);
</script>

<template>
    <div class="space-y-6" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <RouterLink :to="{ name: 'instructor.assignments.show', params: { courseId: route.params.courseId, assignmentId: route.params.assignmentId } }" class="font-bold text-brand underline">{{ t('instructor.submissions') }}</RouterLink>
        <LoadingState v-if="loading" />
        <BaseAlert v-else-if="error" tone="danger">{{ t(error.status === 403 ? 'instructor.forbidden' : error.status === 404 ? 'instructor.notFound' : 'instructor.loadError') }} <button type="button" class="font-bold underline" @click="load">{{ t('instructor.retry') }}</button></BaseAlert>
        <template v-else-if="submission">
            <PageHeading :title="`${submission.student?.name ?? `#${submission.user_id}`} · ${t('instructor.attempt')} ${submission.attempt_number}`" :description="submission.assignment_title" />
            <section class="grid gap-4 rounded-3xl border border-slate-200 bg-white p-6 text-sm sm:grid-cols-2 lg:grid-cols-4"><div><p class="text-slate-500">{{ t('instructor.status') }}</p><p class="font-bold">{{ t(`instructor.statusLabels.${submission.status}`) }}</p></div><div><p class="text-slate-500">{{ t('instructor.submittedAt') }}</p><p class="font-bold">{{ formatDate(submission.submitted_at, locale) }}</p></div><div><p class="text-slate-500">{{ t('instructor.score') }}</p><p class="font-bold">{{ submission.score ?? '—' }} / {{ submission.maximum_score }}</p></div><div><p class="text-slate-500">{{ t('instructor.deadline') }}</p><p class="font-bold">{{ formatDate(submission.due_at, locale) }} · {{ submission.is_late ? t('instructor.late') : t('instructor.onTime') }}</p></div></section>
            <section class="space-y-3 rounded-3xl border border-slate-200 bg-white p-6"><h2 class="text-xl font-black">{{ t('instructor.snapshot') }}</h2><p class="font-bold">{{ submission.assignment_title }}</p><p class="whitespace-pre-wrap text-sm leading-7 text-slate-700">{{ submission.assignment_instructions }}</p><p class="text-sm text-slate-500">{{ t('instructor.type') }}: {{ t(`instructor.submissionTypes.${submission.submission_type}`) }} · {{ t('instructor.maximum') }}: {{ submission.maximum_score }} · {{ t('instructor.deadline') }}: {{ formatDate(submission.due_at, locale) }}</p></section>
            <section class="space-y-3 rounded-3xl border border-slate-200 bg-white p-6"><h2 class="text-xl font-black">{{ t('instructor.answer') }}</h2><div v-if="submission.submission_type !== 'file'" class="whitespace-pre-wrap break-words rounded-xl bg-slate-50 p-4 text-sm leading-7">{{ submission.text_answer || t('instructor.noAnswer') }}</div><div v-if="submission.submission_type !== 'text'" class="flex flex-wrap gap-2"><button v-for="file in submission.files ?? []" :key="file.id" type="button" :disabled="downloadingId !== null" class="min-h-11 rounded-xl border border-slate-300 px-4 font-bold text-brand disabled:opacity-50" @click="download(file)">{{ t('instructor.download') }}: {{ file.original_filename }}</button></div></section>
            <BaseAlert v-if="saved" tone="success">{{ t('instructor.saveSuccess') }}</BaseAlert>
            <BaseAlert v-if="formError" tone="danger">{{ formError }}</BaseAlert>
            <BaseAlert v-if="actionError" tone="danger">{{ t('instructor.mutationError') }} {{ Object.values(actionError.errors ?? {}).flat().join(' ') || actionError.message || '' }}</BaseAlert>
            <div v-if="submission.status === 'submitted'" class="grid gap-5 lg:grid-cols-2"><section class="space-y-4 rounded-3xl border border-slate-200 bg-white p-6"><h2 class="text-xl font-black">{{ t('instructor.grade') }}</h2><form class="space-y-3" @submit.prevent="submitGrade"><label for="grade-score" class="block text-sm font-bold">{{ t('instructor.score') }} / {{ submission.maximum_score }}</label><input id="grade-score" v-model="grade.score" inputmode="decimal" required class="min-h-11 w-full rounded-xl border px-4"><label for="grade-feedback" class="block text-sm font-bold">{{ t('instructor.feedback') }}</label><textarea id="grade-feedback" v-model="grade.feedback" maxlength="10000" rows="4" class="w-full rounded-xl border px-4 py-3" /><button type="submit" :disabled="busy" class="min-h-11 rounded-xl bg-brand px-5 font-bold text-white disabled:opacity-50">{{ t('instructor.saveGrade') }}</button></form></section><section class="space-y-4 rounded-3xl border border-amber-200 bg-amber-50 p-6"><h2 class="text-xl font-black">{{ t('instructor.requestRevision') }}</h2><p class="text-sm">{{ t('instructor.revisionNote') }}</p><form class="space-y-3" @submit.prevent="submitRevision"><label for="revision-feedback" class="block text-sm font-bold">{{ t('instructor.feedback') }}</label><textarea id="revision-feedback" v-model="revision.feedback" required maxlength="10000" rows="4" class="w-full rounded-xl border px-4 py-3" /><button type="submit" :disabled="busy" class="min-h-11 rounded-xl bg-amber-700 px-5 font-bold text-white disabled:opacity-50">{{ t('instructor.sendRevision') }}</button></form></section></div>
            <section v-if="submission.status === 'graded'" class="space-y-4 rounded-3xl border border-slate-200 bg-white p-6"><h2 class="text-xl font-black">{{ t('instructor.correctGrade') }}</h2><form class="grid gap-3" @submit.prevent="submitCorrection"><label for="correction-score" class="text-sm font-bold">{{ t('instructor.score') }} / {{ submission.maximum_score }}</label><input id="correction-score" v-model="correction.score" inputmode="decimal" required class="min-h-11 rounded-xl border px-4"><label for="correction-feedback" class="text-sm font-bold">{{ t('instructor.feedback') }}</label><textarea id="correction-feedback" v-model="correction.feedback" maxlength="10000" rows="3" class="rounded-xl border px-4 py-3" /><label for="correction-reason" class="text-sm font-bold">{{ t('instructor.correctionReason') }}</label><textarea id="correction-reason" v-model="correction.reason" required maxlength="10000" rows="3" class="rounded-xl border px-4 py-3" /><button type="submit" :disabled="busy" class="min-h-11 w-fit rounded-xl bg-brand px-5 font-bold text-white disabled:opacity-50">{{ t('instructor.saveCorrection') }}</button></form></section>
            <section v-if="submission.grading_history?.length" class="space-y-3 rounded-3xl border border-slate-200 bg-white p-6"><h2 class="text-xl font-black">{{ t('instructor.history') }}</h2><ol class="space-y-2 text-sm"><li v-for="event in submission.grading_history" :key="event.id" class="rounded-xl bg-slate-50 p-3">{{ event.action }} · {{ formatDate(event.created_at, locale) }} · {{ event.score ?? '—' }} <span v-if="event.reason">· {{ event.reason }}</span></li></ol></section>
        </template>
    </div>
</template>

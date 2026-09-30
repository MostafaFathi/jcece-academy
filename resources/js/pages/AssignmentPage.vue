<script setup>
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { fetchAssignment, fetchAssignmentSubmissions, fetchAssignmentSubmission, startAssignmentDraft, saveAssignmentDraft, uploadAssignmentFiles, deleteAssignmentFile, submitAssignment, downloadAssignmentAttachment, downloadSubmissionFile } from '../api/assessments';
import BaseButton from '../components/ui/BaseButton.vue';
import LoadingState from '../components/ui/LoadingState.vue';
import { formatDate } from '../utils/catalog';

const props = defineProps({ id: { type: [String, Number], required: true } });
const { t, locale } = useI18n();
const assignment = ref(null);
const submissions = ref([]);
const submission = ref(null);
const textAnswer = ref('');
const filesToUpload = ref([]);
const loading = ref(true);
const busy = ref(false);
const error = ref(null);
const notice = ref('');
const remainingAttempts = computed(() => assignment.value?.max_attempts == null ? null : Math.max(0, assignment.value.max_attempts - submissions.value.length));
const isLate = computed(() => assignment.value?.due_at && new Date(assignment.value.due_at).getTime() < Date.now());
const canStart = computed(() => !submissions.value.some((item) => item.status === 'draft') && remainingAttempts.value !== 0 && (!isLate.value || assignment.value?.allow_late_submissions));
function sync(record) { submission.value = record; textAnswer.value = record?.text_answer ?? ''; filesToUpload.value = []; }
async function load() {
    loading.value = true; error.value = null; assignment.value = null; sync(null);
    try {
        assignment.value = await fetchAssignment(props.id);
        submissions.value = await fetchAssignmentSubmissions(props.id);
        if (submissions.value.length) sync(await fetchAssignmentSubmission(submissions.value[0].id));
    } catch (failure) { error.value = failure; }
    finally { loading.value = false; }
}
watch(() => props.id, load, { immediate: true });
async function run(action, clearOnNotFound = true) {
    if (busy.value) return false;
    busy.value = true; error.value = null; notice.value = '';
    try { await action(); return true; }
    catch (failure) { error.value = failure; if ([401, 403].includes(failure.status) || (clearOnNotFound && failure.status === 404)) { assignment.value = null; sync(null); } return false; }
    finally { busy.value = false; }
}
async function refreshHistory() { submissions.value = await fetchAssignmentSubmissions(props.id); }
async function start() {
    if (!canStart.value) return;
    await run(async () => { sync(await startAssignmentDraft(props.id)); await refreshHistory(); });
}
async function openSubmission(id) { await run(async () => sync(await fetchAssignmentSubmission(id))); }
async function saveDraft() {
    if (submission.value?.status !== 'draft') return false;
    return run(async () => {
        const selectedFiles = [...filesToUpload.value];
        if (assignment.value.submission_type !== 'file') sync(await saveAssignmentDraft(submission.value.id, textAnswer.value));
        if (selectedFiles.length) sync(await uploadAssignmentFiles(submission.value.id, selectedFiles));
        notice.value = t('assessments.draftSaved');
        await refreshHistory();
    });
}
async function removeFile(fileId) {
    await run(async () => { sync(await deleteAssignmentFile(submission.value.id, fileId)); await refreshHistory(); });
}
async function finalSubmit() {
    if (busy.value || submission.value?.status !== 'draft' || !window.confirm(t('assessments.confirmAssignment'))) return;
    if (!(await saveDraft())) return;
    await run(async () => { sync(await submitAssignment(submission.value.id)); await refreshHistory(); });
}
async function downloadAttachment(id) { await run(() => downloadAssignmentAttachment(id), false); }
async function downloadFile(id) { await run(() => downloadSubmissionFile(id), false); }
</script>

<template>
    <div class="mx-auto max-w-5xl space-y-6" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <RouterLink :to="{ name: 'student.courses.index' }" class="text-sm font-bold text-brand">{{ t('learning.back') }}</RouterLink>
        <LoadingState v-if="loading" />
        <div v-else-if="error && !assignment" role="alert" class="rounded-2xl border border-red-200 bg-red-50 p-5 text-red-800">{{ t([401, 403, 404].includes(error.status) ? 'assessments.accessLost' : 'assessments.requestFailed') }} <BaseButton variant="secondary" class="mt-3" @click="load">{{ t('common.retry') }}</BaseButton></div>
        <template v-else-if="assignment">
            <header class="rounded-3xl bg-brand-dark p-6 text-white sm:p-8"><p class="text-xs font-bold uppercase tracking-widest text-accent">{{ t('assessments.assignment') }}</p><h1 class="mt-3 text-2xl font-black sm:text-3xl">{{ assignment.title }}</h1><p v-if="assignment.description" class="mt-3 whitespace-pre-wrap text-white/80">{{ assignment.description }}</p><p v-if="assignment.instructions" class="mt-4 whitespace-pre-wrap border-t border-white/20 pt-4 text-sm leading-7">{{ assignment.instructions }}</p></header>
            <div v-if="error" role="alert" class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ t([401, 403, 404].includes(error.status) ? 'assessments.accessLost' : error.status === 422 ? 'assessments.validationFailed' : 'assessments.requestFailed') }} {{ error.message }}</div>
            <p v-if="notice" role="status" class="text-sm font-bold text-emerald-700">{{ notice }}</p>
            <div class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-5 text-sm sm:grid-cols-2"><p>{{ t('assessments.submissionType') }}: <strong>{{ t(`assessments.types.${assignment.submission_type}`) }}</strong></p><p>{{ t('assessments.maximumScore') }}: <strong>{{ assignment.maximum_score }}</strong></p><p v-if="assignment.passing_score !== null">{{ t('assessments.passingScore') }}: <strong>{{ assignment.passing_score }}</strong></p><p>{{ remainingAttempts === null ? t('assessments.unlimitedAttempts') : t('assessments.remainingAttempts', { count: remainingAttempts }) }}</p><p v-if="assignment.due_at">{{ t('assessments.due', { date: formatDate(assignment.due_at, locale) }) }}</p><p v-if="assignment.allow_late_submissions" class="text-amber-800">{{ t('assessments.lateAllowed') }}</p><p v-else-if="isLate" class="text-red-700">{{ t('assessments.lateClosed') }}</p></div>
            <section v-if="assignment.attachments?.length" class="rounded-2xl border border-slate-200 bg-white p-5"><h2 class="font-black">{{ t('assessments.attachments') }}</h2><div v-for="attachment in assignment.attachments" :key="attachment.id" class="mt-3 flex flex-wrap items-center justify-between gap-3"><span class="break-all">{{ attachment.original_filename }}</span><BaseButton variant="secondary" :disabled="busy" @click="downloadAttachment(attachment.id)">{{ t('assessments.download') }}</BaseButton></div></section>
            <div v-if="canStart" class="rounded-2xl border border-slate-200 bg-white p-5"><p v-if="submission?.status === 'revision_requested'" class="mb-3 text-sm text-slate-600">{{ t('assessments.revisionNote') }}</p><BaseButton :loading="busy" @click="start">{{ t(submission?.status === 'revision_requested' ? 'assessments.newRevision' : 'assessments.startDraft') }}</BaseButton></div>
            <section v-if="submission" class="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 sm:p-7"><h2 class="text-xl font-black">{{ t('assessments.attempt', { number: submission.attempt_number }) }} · {{ t(`assessments.status.${submission.status}`) }}</h2><p v-if="submission.submitted_at" class="text-sm text-slate-500">{{ formatDate(submission.submitted_at, locale) }}</p><p v-if="submission.is_late" class="text-sm font-bold text-amber-800">{{ t('assessments.submittedLate') }}</p><p v-if="submission.feedback" class="whitespace-pre-wrap rounded-xl bg-amber-50 p-4 text-amber-950">{{ t('assessments.feedback') }}: {{ submission.feedback }}</p><div v-if="submission.status === 'graded'" class="rounded-xl bg-emerald-50 p-4 font-bold text-emerald-900">{{ t('assessments.score') }}: {{ submission.score }} / {{ submission.maximum_score }} · {{ t(submission.passed ? 'assessments.passed' : 'assessments.failed') }}<p v-if="submission.graded_at" class="mt-1 text-sm">{{ t('assessments.gradedAt', { date: formatDate(submission.graded_at, locale) }) }}</p></div>
                <label v-if="submission.submission_type !== 'file'" class="block space-y-2 text-sm font-bold"><span>{{ t('assessments.answer') }}</span><textarea v-model="textAnswer" :readonly="submission.status !== 'draft'" maxlength="100000" rows="8" class="w-full rounded-xl border border-slate-300 p-3 font-normal focus:border-brand focus:outline-none" /></label>
                <div v-if="submission.submission_type !== 'text'" class="space-y-3"><h3 class="font-bold">{{ t('assessments.files') }}</h3><div v-for="file in submission.files || []" :key="file.id" class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-slate-50 p-3"><span class="break-all">{{ file.original_filename }}</span><span class="flex gap-2"><BaseButton variant="secondary" :disabled="busy" @click="downloadFile(file.id)">{{ t('assessments.download') }}</BaseButton><BaseButton v-if="submission.status === 'draft'" variant="danger" :disabled="busy" @click="removeFile(file.id)">{{ t('assessments.removeFile') }}</BaseButton></span></div><label v-if="submission.status === 'draft'" class="block text-sm font-bold"><span>{{ t('assessments.addFiles') }}</span><input type="file" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.zip,.txt" class="mt-2 block w-full text-sm" @change="filesToUpload = Array.from($event.target.files || [])" /></label></div>
                <div v-if="submission.status === 'draft'" class="flex flex-wrap gap-3"><BaseButton variant="secondary" :loading="busy" @click="saveDraft">{{ t('assessments.saveDraft') }}</BaseButton><BaseButton :loading="busy" @click="finalSubmit">{{ t('assessments.submitAssignment') }}</BaseButton></div>
            </section>
            <section v-if="submissions.length > 1" class="rounded-2xl border border-slate-200 bg-white p-5"><h2 class="font-black">{{ t('assessments.attempts') }}</h2><div class="mt-3 flex flex-wrap gap-2"><BaseButton v-for="record in submissions" :key="record.id" variant="secondary" :disabled="busy || record.id === submission?.id" @click="openSubmission(record.id)">{{ t('assessments.attempt', { number: record.attempt_number }) }} · {{ t(`assessments.status.${record.status}`) }}</BaseButton></div></section>
        </template>
    </div>
</template>

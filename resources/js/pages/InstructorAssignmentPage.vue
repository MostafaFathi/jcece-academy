<script setup>
import { reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { fetchInstructorAssignment, fetchInstructorSubmissions, downloadInstructorAssignmentAttachment } from '../api/instructor';
import { formatDate } from '../utils/catalog';
import PageHeading from '../components/ui/PageHeading.vue';
import LoadingState from '../components/ui/LoadingState.vue';
import BaseAlert from '../components/ui/BaseAlert.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import PaginationNav from '../components/ui/PaginationNav.vue';

const route = useRoute();
const router = useRouter();
const { t, locale } = useI18n();
const assignment = ref(null);
const submissions = ref([]);
const meta = ref(null);
const loading = ref(true);
const error = ref(null);
const downloadError = ref(null);
const downloading = ref(false);
const filters = reactive({ status: '' });
let sequence = 0;

async function load() {
    const current = ++sequence;
    loading.value = true;
    error.value = null;
    filters.status = String(route.query.status ?? '');
    try {
        const [definition, result] = await Promise.all([
            fetchInstructorAssignment(route.params.courseId, route.params.assignmentId),
            fetchInstructorSubmissions(route.params.courseId, route.params.assignmentId, { page: Number(route.query.page) || 1, ...(filters.status ? { status: filters.status } : {}) }),
        ]);
        if (current === sequence) { assignment.value = definition; submissions.value = result.items; meta.value = result.meta; }
    } catch (failure) { if (current === sequence) error.value = failure; }
    finally { if (current === sequence) loading.value = false; }
}
function applyFilter() { router.push({ name: 'instructor.assignments.show', params: route.params, query: filters.status ? { status: filters.status } : {} }); }
function changePage(page) { router.push({ name: 'instructor.assignments.show', params: route.params, query: { ...route.query, page } }); }
async function download(file) { if (downloading.value) return; downloading.value = true; downloadError.value = null; try { await downloadInstructorAssignmentAttachment(file.id, file.original_filename); } catch (failure) { downloadError.value = failure; } finally { downloading.value = false; } }
watch(() => route.fullPath, load, { immediate: true });
</script>

<template>
    <div class="space-y-6" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <RouterLink :to="{ name: 'instructor.courses.show', params: { courseId: route.params.courseId } }" class="font-bold text-brand underline">{{ t('instructor.back') }}</RouterLink>
        <LoadingState v-if="loading" />
        <BaseAlert v-else-if="error" tone="danger">{{ t(error.status === 403 ? 'instructor.forbidden' : error.status === 404 ? 'instructor.notFound' : 'instructor.loadError') }} <button type="button" class="font-bold underline" @click="load">{{ t('instructor.retry') }}</button></BaseAlert>
        <template v-else-if="assignment">
            <PageHeading :title="assignment.title" :description="t('instructor.submissions')" />
            <section class="grid gap-4 rounded-3xl border border-slate-200 bg-white p-6 text-sm sm:grid-cols-3"><div><p class="text-slate-500">{{ t('instructor.status') }}</p><p class="font-bold">{{ t(`instructor.statusLabels.${assignment.status}`) }}</p></div><div><p class="text-slate-500">{{ t('instructor.type') }}</p><p class="font-bold">{{ t(`instructor.submissionTypes.${assignment.submission_type}`) }}</p></div><div><p class="text-slate-500">{{ t('instructor.maximum') }}</p><p class="font-bold">{{ assignment.maximum_score }}</p></div><div><p class="text-slate-500">{{ t('instructor.deadline') }}</p><p class="font-bold">{{ formatDate(assignment.due_at, locale) }}</p></div><div><p class="text-slate-500">{{ t('instructor.allowedAttempts') }}</p><p class="font-bold">{{ assignment.max_attempts ?? '—' }}</p></div></section>
            <p v-if="assignment.instructions" class="whitespace-pre-wrap rounded-2xl bg-white p-5 text-sm leading-7">{{ assignment.instructions }}</p>
            <BaseAlert v-if="downloadError" tone="danger">{{ t('instructor.mutationError') }}</BaseAlert>
            <div v-if="assignment.attachments?.length" class="flex flex-wrap gap-2"><button v-for="file in assignment.attachments" :key="file.id" type="button" class="min-h-11 rounded-xl border border-slate-300 bg-white px-4 font-bold text-brand" :disabled="downloading" @click="download(file)">{{ t('instructor.download') }}: {{ file.original_filename }}</button></div>
            <section class="space-y-4"><h2 class="text-xl font-black">{{ t('instructor.submissions') }}</h2><form class="flex flex-wrap gap-3" @submit.prevent="applyFilter"><label class="sr-only" for="submission-status">{{ t('instructor.filter') }}</label><select id="submission-status" v-model="filters.status" class="min-h-11 rounded-xl border px-4"><option value="">{{ t('instructor.all') }}</option><option v-for="status in ['submitted', 'graded', 'revision_requested']" :key="status" :value="status">{{ t(`instructor.statusLabels.${status}`) }}</option></select><button type="submit" class="min-h-11 rounded-xl bg-brand px-5 font-bold text-white">{{ t('instructor.filter') }}</button></form><EmptyState v-if="!submissions.length" :title="t('instructor.noSubmissions')" /><article v-for="submission in submissions" :key="submission.id" class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5"><div><p class="font-bold">{{ submission.student?.name ?? `#${submission.user_id}` }} · {{ t('instructor.attempt') }} {{ submission.attempt_number }}</p><p class="mt-1 text-sm text-slate-500">{{ t(`instructor.statusLabels.${submission.status}`) }} · {{ formatDate(submission.submitted_at, locale) }} · {{ submission.is_late ? t('instructor.late') : t('instructor.onTime') }} · {{ submission.score ?? '—' }} / {{ submission.maximum_score }}</p></div><RouterLink :to="{ name: 'instructor.submissions.show', params: { courseId: route.params.courseId, assignmentId: route.params.assignmentId, submissionId: submission.id } }" class="font-bold text-brand underline">{{ t('instructor.view') }}</RouterLink></article><PaginationNav v-if="meta?.last_page > 1" :current-page="meta.current_page" :last-page="meta.last_page" @change="changePage" /></section>
        </template>
    </div>
</template>

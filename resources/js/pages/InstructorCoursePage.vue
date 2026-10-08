<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../stores/auth';
import { fetchInstructorCourse, fetchInstructorCurriculum, fetchInstructorQuizzes, fetchInstructorAssignments, updateInstructorCourse } from '../api/instructor';
import PageHeading from '../components/ui/PageHeading.vue';
import LoadingState from '../components/ui/LoadingState.vue';
import BaseAlert from '../components/ui/BaseAlert.vue';
import EmptyState from '../components/ui/EmptyState.vue';

const route = useRoute();
const auth = useAuthStore();
const { t, locale } = useI18n();
const course = ref(null);
const sections = ref([]);
const quizzes = ref([]);
const assignments = ref([]);
const loading = ref(true);
const error = ref(null);
const saving = ref(false);
const saveError = ref(null);
const saved = ref(false);
const form = reactive({ title: '', short_description: '', description: '' });
const courseId = route.params.courseId;
function lessonTitle(lessonId) {
    if (!lessonId) return null;
    return sections.value.flatMap((section) => section.lessons ?? []).find((lesson) => lesson.id === lessonId)?.title ?? null;
}

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const [courseResult, curriculumResult, quizResult, assignmentResult] = await Promise.all([
            fetchInstructorCourse(courseId),
            auth.can('curriculum.view') ? fetchInstructorCurriculum(courseId) : Promise.resolve({ items: [] }),
            auth.canAny(['assessments.view', 'assignment_submissions.view']) ? fetchInstructorQuizzes(courseId) : Promise.resolve({ items: [] }),
            auth.canAny(['assignments.view', 'assignment_submissions.view']) ? fetchInstructorAssignments(courseId) : Promise.resolve({ items: [] }),
        ]);
        course.value = courseResult;
        sections.value = curriculumResult.items;
        quizzes.value = quizResult.items;
        assignments.value = assignmentResult.items;
        Object.assign(form, { title: courseResult.title, short_description: courseResult.short_description ?? '', description: courseResult.description ?? '' });
    } catch (failure) { error.value = failure; }
    finally { loading.value = false; }
}
async function save() {
    if (saving.value || !form.title.trim()) return;
    saving.value = true;
    saveError.value = null;
    saved.value = false;
    try {
        course.value = await updateInstructorCourse(courseId, { title: form.title.trim(), short_description: form.short_description, description: form.description });
        saved.value = true;
    } catch (failure) { saveError.value = failure; }
    finally { saving.value = false; }
}
onMounted(load);
</script>

<template>
    <div class="space-y-7" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <RouterLink :to="{ name: 'instructor.courses.index' }" class="font-bold text-brand underline">{{ t('instructor.myCourses') }}</RouterLink>
        <LoadingState v-if="loading" />
        <BaseAlert v-else-if="error" tone="danger">{{ t(error.status === 403 ? 'instructor.forbidden' : error.status === 404 ? 'instructor.notFound' : 'instructor.loadError') }} <button type="button" class="font-bold underline" @click="load">{{ t('instructor.retry') }}</button></BaseAlert>
        <template v-else-if="course">
            <PageHeading :title="course.title" :description="course.short_description ?? ''"><template #actions><RouterLink v-if="course.capabilities?.can_view_curriculum" :to="{ name: 'instructor.courses.curriculum', params: { courseId } }" class="inline-flex min-h-11 items-center rounded-xl border border-brand px-5 py-2.5 text-sm font-bold text-brand">{{ t('curriculum.manage') }}</RouterLink></template></PageHeading>
            <section class="grid gap-4 rounded-3xl border border-slate-200 bg-white p-6 sm:grid-cols-3"><div><p class="text-xs font-bold text-slate-500">{{ t('instructor.status') }}</p><p class="mt-1 font-bold">{{ t(`instructor.statusLabels.${course.status}`) }}</p></div><div><p class="text-xs font-bold text-slate-500">{{ t('instructor.category') }}</p><p class="mt-1 font-bold">{{ course.category?.name ?? '—' }}</p></div><div><p class="text-xs font-bold text-slate-500">{{ t('instructor.level') }}</p><p class="mt-1 font-bold">{{ t(`labels.levels.${course.level}`) }}</p></div></section>
            <section v-if="course.capabilities?.can_update_course" class="space-y-4 rounded-3xl border border-slate-200 bg-white p-6"><h2 class="text-xl font-black">{{ t('instructor.editMetadata') }}</h2><p class="text-sm text-slate-500">{{ t('instructor.metadataHint') }}</p><BaseAlert v-if="saveError" tone="danger">{{ t('instructor.mutationError') }} {{ saveError.errors?.title?.[0] ?? '' }}</BaseAlert><BaseAlert v-if="saved" tone="success">{{ t('instructor.saveSuccess') }}</BaseAlert><form class="grid gap-3" @submit.prevent="save"><label class="text-sm font-bold" for="instructor-title">{{ t('instructor.title') }}</label><input id="instructor-title" v-model="form.title" required maxlength="255" class="min-h-11 rounded-xl border px-4"><label class="text-sm font-bold" for="instructor-short">{{ t('instructor.shortDescription') }}</label><textarea id="instructor-short" v-model="form.short_description" maxlength="1000" rows="2" class="rounded-xl border px-4 py-3" /><label class="text-sm font-bold" for="instructor-description">{{ t('instructor.description') }}</label><textarea id="instructor-description" v-model="form.description" rows="4" class="rounded-xl border px-4 py-3" /><button type="submit" :disabled="saving" class="min-h-11 w-fit rounded-xl bg-brand px-5 font-bold text-white disabled:opacity-50">{{ t('instructor.saveMetadata') }}</button></form></section>
            <section v-if="course.capabilities?.can_view_curriculum" class="space-y-4"><div><h2 class="text-xl font-black">{{ t('instructor.curriculum') }}</h2><p v-if="!course.capabilities?.can_create_curriculum && !course.capabilities?.can_update_curriculum && !course.capabilities?.can_delete_curriculum" class="text-sm text-slate-500">{{ t('instructor.readOnlyCurriculum') }}</p></div><EmptyState v-if="!sections.length" :title="t('instructor.curriculum')" /><article v-for="section in sections" :key="section.id" class="rounded-3xl border border-slate-200 bg-white p-5"><h3 class="font-black">{{ section.title }}</h3><ol class="mt-3 space-y-3"><li v-for="lesson in section.lessons ?? []" :key="lesson.id" class="rounded-xl bg-slate-50 p-4"><p class="font-bold">{{ lesson.title }}</p><p class="text-xs text-slate-500">{{ t(`labels.lessonTypes.${lesson.type}`) }}</p><ul v-if="lesson.resources?.length" class="mt-2 space-y-1 text-sm"><li v-for="resource in lesson.resources" :key="resource.id">{{ t('instructor.resource') }}: {{ resource.title }} <span v-if="resource.download_available" class="text-slate-500">({{ t('instructor.unavailable') }})</span></li></ul></li></ol></article></section>
            <div class="grid gap-6 lg:grid-cols-2">
                <section v-if="auth.canAny(['assessments.view', 'assignment_submissions.view'])" class="space-y-4 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-xl font-black">{{ t('instructor.quizzes') }}</h2><RouterLink v-if="course.capabilities?.can_create_quiz" :to="{ name: 'instructor.quizzes.create', params: { courseId } }" class="inline-flex min-h-11 items-center rounded-xl bg-brand px-4 text-sm font-bold text-white">{{ t('assessmentAuthoring.createQuiz') }}</RouterLink></div>
                    <p v-if="!course.capabilities?.can_create_quiz && !quizzes.some((quiz) => quiz.capabilities?.can_update)" class="text-sm text-slate-500">{{ t('instructor.quizDefinitionsReadOnly') }}</p>
                    <EmptyState v-if="!quizzes.length" :title="t('instructor.noQuizzes')" />
                    <article v-for="quiz in quizzes" :key="quiz.id" class="min-w-0 space-y-3 rounded-2xl border border-slate-200 bg-slate-50 p-4"><div class="flex flex-wrap items-start justify-between gap-2"><h3 class="break-words font-bold">{{ quiz.title }}</h3><span class="rounded-full bg-white px-3 py-1 text-xs font-bold text-slate-700">{{ t(`assessmentAuthoring.${quiz.status}`) }}</span></div><p class="text-xs text-slate-600">{{ t('assessmentAuthoring.questionCount', { count: quiz.questions_count ?? 0 }) }} · {{ quiz.passing_score }}% · {{ quiz.max_attempts ?? '∞' }} {{ t('instructor.attempts') }}<span v-if="lessonTitle(quiz.lesson_id)"> · {{ lessonTitle(quiz.lesson_id) }}</span></p><div class="flex flex-wrap gap-3 text-sm font-bold"><RouterLink v-if="auth.can('assessments.view')" :to="{ name: 'instructor.quizzes.edit', params: { courseId, quizId: quiz.id } }" class="inline-flex min-h-11 items-center text-brand underline">{{ t(quiz.capabilities?.can_update ? 'assessmentAuthoring.editQuiz' : 'instructor.view') }}</RouterLink><RouterLink v-if="quiz.capabilities?.can_view_results" :to="{ name: 'instructor.quizzes.show', params: { courseId, quizId: quiz.id } }" class="inline-flex min-h-11 items-center text-brand underline">{{ t('assessmentAuthoring.results') }}</RouterLink></div></article>
                </section>
                <section v-if="auth.canAny(['assignments.view', 'assignment_submissions.view'])" class="space-y-4 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-xl font-black">{{ t('instructor.assignments') }}</h2><RouterLink v-if="course.capabilities?.can_create_assignment" :to="{ name: 'instructor.assignments.create', params: { courseId } }" class="inline-flex min-h-11 items-center rounded-xl bg-brand px-4 text-sm font-bold text-white">{{ t('assessmentAuthoring.createAssignment') }}</RouterLink></div>
                    <p v-if="!course.capabilities?.can_create_assignment && !assignments.some((assignment) => assignment.capabilities?.can_update)" class="text-sm text-slate-500">{{ t('instructor.assignmentDefinitionsReadOnly') }}</p>
                    <EmptyState v-if="!assignments.length" :title="t('instructor.noAssignments')" />
                    <article v-for="assignment in assignments" :key="assignment.id" class="min-w-0 space-y-3 rounded-2xl border border-slate-200 bg-slate-50 p-4"><div class="flex flex-wrap items-start justify-between gap-2"><h3 class="break-words font-bold">{{ assignment.title }}</h3><span class="rounded-full bg-white px-3 py-1 text-xs font-bold text-slate-700">{{ t(`assessmentAuthoring.${assignment.status}`) }}</span></div><p class="text-xs text-slate-600">{{ t(`instructor.submissionTypes.${assignment.submission_type}`) }} · {{ assignment.maximum_score }} {{ t('assessmentAuthoring.points') }}<span v-if="lessonTitle(assignment.lesson_id)"> · {{ lessonTitle(assignment.lesson_id) }}</span></p><div class="flex flex-wrap gap-3 text-sm font-bold"><RouterLink v-if="auth.can('assignments.view')" :to="{ name: 'instructor.assignments.edit', params: { courseId, assignmentId: assignment.id } }" class="inline-flex min-h-11 items-center text-brand underline">{{ t(assignment.capabilities?.can_update ? 'assessmentAuthoring.editAssignment' : 'instructor.view') }}</RouterLink><RouterLink v-if="assignment.capabilities?.can_review_submissions" :to="{ name: 'instructor.assignments.show', params: { courseId, assignmentId: assignment.id } }" class="inline-flex min-h-11 items-center text-brand underline">{{ t('assessmentAuthoring.submissions') }}</RouterLink></div></article>
                </section>
            </div>
        </template>
    </div>
</template>

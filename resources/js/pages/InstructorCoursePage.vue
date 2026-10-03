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

async function load() {
    loading.value = true;
    error.value = null;
    try {
        const [courseResult, curriculumResult, quizResult, assignmentResult] = await Promise.all([
            fetchInstructorCourse(courseId), fetchInstructorCurriculum(courseId), fetchInstructorQuizzes(courseId), fetchInstructorAssignments(courseId),
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
            <PageHeading :title="course.title" :description="course.short_description ?? ''" />
            <section class="grid gap-4 rounded-3xl border border-slate-200 bg-white p-6 sm:grid-cols-3"><div><p class="text-xs font-bold text-slate-500">{{ t('instructor.status') }}</p><p class="mt-1 font-bold">{{ t(`instructor.statusLabels.${course.status}`) }}</p></div><div><p class="text-xs font-bold text-slate-500">{{ t('instructor.category') }}</p><p class="mt-1 font-bold">{{ course.category?.name ?? '—' }}</p></div><div><p class="text-xs font-bold text-slate-500">{{ t('instructor.level') }}</p><p class="mt-1 font-bold">{{ t(`labels.levels.${course.level}`) }}</p></div></section>
            <section v-if="auth.can('courses.update')" class="space-y-4 rounded-3xl border border-slate-200 bg-white p-6"><h2 class="text-xl font-black">{{ t('instructor.editMetadata') }}</h2><p class="text-sm text-slate-500">{{ t('instructor.metadataHint') }}</p><BaseAlert v-if="saveError" tone="danger">{{ t('instructor.mutationError') }} {{ saveError.errors?.title?.[0] ?? '' }}</BaseAlert><BaseAlert v-if="saved" tone="success">{{ t('instructor.saveSuccess') }}</BaseAlert><form class="grid gap-3" @submit.prevent="save"><label class="text-sm font-bold" for="instructor-title">{{ t('instructor.title') }}</label><input id="instructor-title" v-model="form.title" required maxlength="255" class="min-h-11 rounded-xl border px-4"><label class="text-sm font-bold" for="instructor-short">{{ t('instructor.shortDescription') }}</label><textarea id="instructor-short" v-model="form.short_description" maxlength="1000" rows="2" class="rounded-xl border px-4 py-3" /><label class="text-sm font-bold" for="instructor-description">{{ t('instructor.description') }}</label><textarea id="instructor-description" v-model="form.description" rows="4" class="rounded-xl border px-4 py-3" /><button type="submit" :disabled="saving" class="min-h-11 w-fit rounded-xl bg-brand px-5 font-bold text-white disabled:opacity-50">{{ t('instructor.saveMetadata') }}</button></form></section>
            <section class="space-y-4"><div><h2 class="text-xl font-black">{{ t('instructor.curriculum') }}</h2><p class="text-sm text-slate-500">{{ t('instructor.readOnlyCurriculum') }}</p></div><EmptyState v-if="!sections.length" :title="t('instructor.curriculum')" /><article v-for="section in sections" :key="section.id" class="rounded-3xl border border-slate-200 bg-white p-5"><h3 class="font-black">{{ section.title }}</h3><ol class="mt-3 space-y-3"><li v-for="lesson in section.lessons ?? []" :key="lesson.id" class="rounded-xl bg-slate-50 p-4"><p class="font-bold">{{ lesson.title }}</p><p class="text-xs text-slate-500">{{ t(`labels.lessonTypes.${lesson.type}`) }}</p><ul v-if="lesson.resources?.length" class="mt-2 space-y-1 text-sm"><li v-for="resource in lesson.resources" :key="resource.id">{{ t('instructor.resource') }}: {{ resource.title }} <span v-if="resource.download_available" class="text-slate-500">({{ t('instructor.unavailable') }})</span></li></ul></li></ol></article></section>
            <div class="grid gap-6 lg:grid-cols-2"><section class="space-y-3"><div><h2 class="text-xl font-black">{{ t('instructor.quizzes') }}</h2><p class="text-sm text-slate-500">{{ t('instructor.quizDefinitionsReadOnly') }}</p></div><EmptyState v-if="!quizzes.length" :title="t('instructor.noQuizzes')" /><RouterLink v-for="quiz in quizzes" :key="quiz.id" :to="{ name: 'instructor.quizzes.show', params: { courseId, quizId: quiz.id } }" class="block rounded-2xl border border-slate-200 bg-white p-5 hover:border-brand"><span class="font-bold">{{ quiz.title }}</span><span class="ms-2 text-xs text-slate-500">{{ t(`instructor.statusLabels.${quiz.status}`) }}</span></RouterLink></section><section class="space-y-3"><div><h2 class="text-xl font-black">{{ t('instructor.assignments') }}</h2><p class="text-sm text-slate-500">{{ t('instructor.assignmentDefinitionsReadOnly') }}</p></div><EmptyState v-if="!assignments.length" :title="t('instructor.noAssignments')" /><RouterLink v-for="assignment in assignments" :key="assignment.id" :to="{ name: 'instructor.assignments.show', params: { courseId, assignmentId: assignment.id } }" class="block rounded-2xl border border-slate-200 bg-white p-5 hover:border-brand"><span class="font-bold">{{ assignment.title }}</span><span class="ms-2 text-xs text-slate-500">{{ t(`instructor.statusLabels.${assignment.status}`) }}</span></RouterLink></section></div>
        </template>
    </div>
</template>

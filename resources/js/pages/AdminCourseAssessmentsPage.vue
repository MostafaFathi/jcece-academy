<script setup>
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../stores/auth';
import { fetchAdminCourse } from '../api/admin';
import { listCourseAssignments, listCourseQuizzes } from '../api/assessment-authoring';
import PageHeading from '../components/ui/PageHeading.vue';
import LoadingState from '../components/ui/LoadingState.vue';
import BaseAlert from '../components/ui/BaseAlert.vue';

const route = useRoute();
const auth = useAuthStore();
const { t, locale } = useI18n();
const course = ref(null);
const quizzes = ref([]);
const assignments = ref([]);
const loading = ref(true);
const error = ref(null);
async function load() {
    loading.value = true; error.value = null;
    try {
        course.value = await fetchAdminCourse(route.params.courseId);
        const [quizResult, assignmentResult] = await Promise.all([
            auth.can('assessments.view') ? listCourseQuizzes(route.params.courseId) : Promise.resolve({ items: [] }),
            auth.can('assignments.view') ? listCourseAssignments(route.params.courseId) : Promise.resolve({ items: [] }),
        ]);
        quizzes.value = quizResult.items;
        assignments.value = assignmentResult.items;
    } catch (failure) { error.value = failure; }
    finally { loading.value = false; }
}
onMounted(load);
</script>

<template>
    <div class="mx-auto max-w-6xl space-y-6" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <RouterLink :to="{ name: 'admin.courses.edit', params: { id: route.params.courseId } }" class="inline-flex min-h-11 items-center text-sm font-bold text-brand underline">{{ t('assessmentAuthoring.back') }}</RouterLink>
        <PageHeading :title="t('assessmentAuthoring.heading')" :description="course?.title ?? ''" />
        <LoadingState v-if="loading" />
        <BaseAlert v-else-if="error" tone="danger">{{ t('assessmentAuthoring.loadError') }} <button type="button" class="font-bold underline" @click="load">{{ t('common.retry') }}</button></BaseAlert>
        <div v-else class="grid gap-6 lg:grid-cols-2"><section v-for="kind in ['quiz', 'assignment']" :key="kind" class="space-y-4 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm"><div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-xl font-black">{{ t(kind === 'quiz' ? 'assessmentAuthoring.quizzes' : 'assessmentAuthoring.assignments') }}</h2><RouterLink v-if="course?.capabilities?.[kind === 'quiz' ? 'can_create_quiz' : 'can_create_assignment']" :to="{ name: `admin.${kind === 'quiz' ? 'quizzes' : 'assignments'}.create`, params: { courseId: route.params.courseId } }" class="inline-flex min-h-11 items-center rounded-xl bg-brand px-4 text-sm font-bold text-white">{{ t(kind === 'quiz' ? 'assessmentAuthoring.createQuiz' : 'assessmentAuthoring.createAssignment') }}</RouterLink></div><ul class="space-y-3"><li v-for="item in kind === 'quiz' ? quizzes : assignments" :key="item.id" class="min-w-0 rounded-2xl border border-slate-200 bg-slate-50 p-4"><p class="break-words font-bold">{{ item.title }}</p><p class="mt-1 text-xs text-slate-600">{{ t(`assessmentAuthoring.${item.status}`) }} · {{ kind === 'quiz' ? `${item.questions?.length ?? 0} ${t('assessmentAuthoring.questions')}` : `${item.maximum_score} ${t('assessmentAuthoring.points')}` }}</p><RouterLink :to="{ name: `admin.${kind === 'quiz' ? 'quizzes' : 'assignments'}.edit`, params: { courseId: route.params.courseId, [`${kind}Id`]: item.id } }" class="mt-3 inline-flex min-h-11 items-center text-sm font-bold text-brand underline">{{ t(kind === 'quiz' ? 'assessmentAuthoring.editQuiz' : 'assessmentAuthoring.editAssignment') }}</RouterLink></li></ul></section></div>
    </div>
</template>

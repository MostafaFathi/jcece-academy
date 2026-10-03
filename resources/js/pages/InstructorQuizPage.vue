<script setup>
import { ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { fetchInstructorQuiz, fetchInstructorQuizAttempts, fetchInstructorQuizAttempt } from '../api/instructor';
import { formatDate } from '../utils/catalog';
import PageHeading from '../components/ui/PageHeading.vue';
import LoadingState from '../components/ui/LoadingState.vue';
import BaseAlert from '../components/ui/BaseAlert.vue';
import EmptyState from '../components/ui/EmptyState.vue';
import PaginationNav from '../components/ui/PaginationNav.vue';

const route = useRoute();
const router = useRouter();
const { t, locale } = useI18n();
const quiz = ref(null);
const attempts = ref([]);
const selected = ref(null);
const meta = ref(null);
const loading = ref(true);
const error = ref(null);
let sequence = 0;

async function load() {
    const current = ++sequence;
    loading.value = true;
    error.value = null;
    selected.value = null;
    try {
        const { courseId, quizId, attemptId } = route.params;
        const [quizResult, result] = await Promise.all([
            fetchInstructorQuiz(courseId, quizId),
            attemptId ? fetchInstructorQuizAttempt(courseId, quizId, attemptId) : fetchInstructorQuizAttempts(courseId, quizId, Number(route.query.page) || 1),
        ]);
        if (current !== sequence) return;
        quiz.value = quizResult;
        if (attemptId) selected.value = result;
        else { attempts.value = result.items; meta.value = result.meta; }
    } catch (failure) { if (current === sequence) error.value = failure; }
    finally { if (current === sequence) loading.value = false; }
}
function changePage(page) { router.push({ name: 'instructor.quizzes.show', params: { courseId: route.params.courseId, quizId: route.params.quizId }, query: { page } }); }
watch(() => route.fullPath, load, { immediate: true });
</script>

<template>
    <div class="space-y-6" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <RouterLink :to="{ name: 'instructor.courses.show', params: { courseId: route.params.courseId } }" class="font-bold text-brand underline">{{ t('instructor.back') }}</RouterLink>
        <LoadingState v-if="loading" />
        <BaseAlert v-else-if="error" tone="danger">{{ t(error.status === 403 ? 'instructor.forbidden' : error.status === 404 ? 'instructor.notFound' : 'instructor.loadError') }} <button type="button" class="font-bold underline" @click="load">{{ t('instructor.retry') }}</button></BaseAlert>
        <template v-else-if="quiz">
            <PageHeading :title="quiz.title" :description="t('instructor.quizDefinitionsReadOnly')" />
            <div class="rounded-3xl border border-slate-200 bg-white p-5 text-sm"><span class="font-bold">{{ t(`instructor.statusLabels.${quiz.status}`) }}</span><span class="mx-2">·</span>{{ t('instructor.allowedAttempts') }}: {{ quiz.max_attempts ?? '—' }}</div>
            <template v-if="selected"><RouterLink :to="{ name: 'instructor.quizzes.show', params: { courseId: route.params.courseId, quizId: route.params.quizId } }" class="font-bold text-brand underline">{{ t('instructor.attempts') }}</RouterLink><section class="rounded-3xl border border-slate-200 bg-white p-6"><h2 class="text-xl font-black">{{ selected.student?.name ?? `#${selected.user_id}` }} · {{ t('instructor.attempt') }} {{ selected.attempt_number }}</h2><p class="mt-2 text-sm text-slate-600">{{ t('instructor.submittedAt') }}: {{ formatDate(selected.submitted_at, locale) }} · {{ t('instructor.score') }}: {{ t('instructor.scoreOf', { score: selected.score ?? '—', maximum: selected.maximum_score ?? '—' }) }}</p></section><section class="space-y-3"><h2 class="text-xl font-black">{{ t('instructor.results') }}</h2><article v-for="question in selected.questions ?? []" :key="question.id" class="rounded-2xl border border-slate-200 bg-white p-5"><h3 class="font-bold">{{ question.question_text }}</h3><p class="mt-1 text-xs text-slate-500">{{ t('instructor.earned') }}: {{ question.earned_points ?? '—' }} / {{ question.points }}</p><ul class="mt-3 space-y-2"><li v-for="option in question.options ?? []" :key="option.id" class="rounded-xl px-3 py-2 text-sm" :class="option.is_correct ? 'bg-emerald-50 text-emerald-900' : question.selected_option_ids?.includes(option.id) ? 'bg-amber-50 text-amber-900' : 'bg-slate-50'"><span v-if="question.selected_option_ids?.includes(option.id)">{{ t('instructor.selected') }} · </span><span v-if="option.is_correct">{{ t('instructor.correct') }} · </span>{{ option.answer_text }}</li></ul></article></section></template>
            <template v-else><h2 class="text-xl font-black">{{ t('instructor.attempts') }}</h2><EmptyState v-if="!attempts.length" :title="t('instructor.noAttempts')" /><div v-else class="space-y-3"><article v-for="attempt in attempts" :key="attempt.id" class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5"><div><p class="font-bold">{{ attempt.student?.name ?? `#${attempt.user_id}` }} · {{ t('instructor.attempt') }} {{ attempt.attempt_number }}</p><p class="mt-1 text-sm text-slate-500">{{ t(`instructor.statusLabels.${attempt.status}`) }} · {{ formatDate(attempt.started_at, locale) }} · {{ attempt.score ?? '—' }} / {{ attempt.maximum_score ?? '—' }}</p></div><RouterLink v-if="attempt.status === 'submitted'" :to="{ name: 'instructor.quizzes.attempts.show', params: { courseId: route.params.courseId, quizId: route.params.quizId, attemptId: attempt.id } }" class="font-bold text-brand underline">{{ t('instructor.view') }}</RouterLink></article></div><PaginationNav v-if="meta?.last_page > 1" :current-page="meta.current_page" :last-page="meta.last_page" @change="changePage" /></template>
        </template>
    </div>
</template>

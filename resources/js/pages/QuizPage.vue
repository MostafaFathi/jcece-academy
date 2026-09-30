<script setup>
import { computed, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { fetchQuiz, fetchQuizAttempts, fetchQuizAttempt, fetchQuizResult, startQuizAttempt, saveQuizAnswers, submitQuizAttempt } from '../api/assessments';
import BaseButton from '../components/ui/BaseButton.vue';
import LoadingState from '../components/ui/LoadingState.vue';
import { formatDate } from '../utils/catalog';

const props = defineProps({ id: { type: [String, Number], required: true } });
const { t, locale } = useI18n();
const quiz = ref(null);
const attempts = ref([]);
const attempt = ref(null);
const answers = ref({});
const loading = ref(true);
const busy = ref(false);
const error = ref(null);
const notice = ref('');
const now = ref(Date.now());
const timer = setInterval(() => { now.value = Date.now(); }, 1000);
onUnmounted(() => clearInterval(timer));

const remainingSeconds = computed(() => attempt.value?.expires_at ? Math.max(0, Math.ceil((new Date(attempt.value.expires_at).getTime() - now.value) / 1000)) : null);
const remainingTime = computed(() => remainingSeconds.value === null ? null : `${String(Math.floor(remainingSeconds.value / 60)).padStart(2, '0')}:${String(remainingSeconds.value % 60).padStart(2, '0')}`);
const hasResult = computed(() => attempt.value && Object.hasOwn(attempt.value, 'score'));
const remainingAttempts = computed(() => quiz.value?.max_attempts == null ? null : Math.max(0, quiz.value.max_attempts - attempts.value.length));
const canStart = computed(() => !attempt.value || attempt.value.status !== 'in_progress');
function syncAnswers(record) {
    answers.value = Object.fromEntries((record?.questions || []).map((question) => [question.id, [...(question.selected_option_ids || [])]]));
}
function select(question, optionId) {
    const current = answers.value[question.id] || [];
    answers.value = { ...answers.value, [question.id]: question.type === 'multiple_choice' ? (current.includes(optionId) ? current.filter((id) => id !== optionId) : [...current, optionId]) : [optionId] };
    notice.value = '';
}
function isSelected(questionId, optionId) { return (answers.value[questionId] || []).includes(optionId); }
function rejectAccess(failure) {
    error.value = failure;
    if ([401, 403, 404].includes(failure.status)) {
        quiz.value = null;
        attempt.value = null;
        answers.value = {};
        attempts.value = [];
    }
}
async function load() {
    loading.value = true; error.value = null; quiz.value = null; attempt.value = null;
    try {
        quiz.value = await fetchQuiz(props.id);
        attempts.value = await fetchQuizAttempts(props.id);
        if (attempts.value.length) {
            attempt.value = await fetchQuizAttempt(attempts.value[0].id);
            syncAnswers(attempt.value);
        }
    } catch (failure) { rejectAccess(failure); }
    finally { loading.value = false; }
}
watch(() => props.id, load, { immediate: true });
async function start() {
    if (busy.value || remainingAttempts.value === 0) return;
    busy.value = true; error.value = null;
    try {
        const started = await startQuizAttempt(props.id);
        attempt.value = await fetchQuizAttempt(started.id);
        syncAnswers(attempt.value);
        attempts.value = await fetchQuizAttempts(props.id);
    } catch (failure) { rejectAccess(failure); }
    finally { busy.value = false; }
}
async function openAttempt(id) {
    if (busy.value) return;
    busy.value = true; error.value = null;
    try { attempt.value = await fetchQuizAttempt(id); syncAnswers(attempt.value); }
    catch (failure) { rejectAccess(failure); }
    finally { busy.value = false; }
}
async function save() {
    if (busy.value || attempt.value?.status !== 'in_progress') return false;
    busy.value = true; error.value = null; notice.value = '';
    try {
        const payload = Object.entries(answers.value).map(([questionId, optionIds]) => ({ question_id: Number(questionId), option_ids: optionIds }));
        attempt.value = await saveQuizAnswers(attempt.value.id, payload);
        syncAnswers(attempt.value);
        notice.value = t('assessments.answersSaved');
        return true;
    } catch (failure) {
        rejectAccess(failure);
        if (failure.status === 422) await refreshAttempt();
        return false;
    } finally { busy.value = false; }
}
async function refreshAttempt() {
    try { attempt.value = await fetchQuizAttempt(attempt.value.id); syncAnswers(attempt.value); }
    catch (failure) { if ([401, 403, 404].includes(failure.status)) rejectAccess(failure); }
}
async function submit() {
    if (busy.value || attempt.value?.status !== 'in_progress' || !window.confirm(t('assessments.confirmQuiz'))) return;
    const saved = await save();
    if (!saved || busy.value) return;
    busy.value = true; notice.value = '';
    try {
        await submitQuizAttempt(attempt.value.id);
        attempt.value = await fetchQuizResult(attempt.value.id);
        syncAnswers(attempt.value);
        attempts.value = await fetchQuizAttempts(props.id);
    } catch (failure) { rejectAccess(failure); if (quiz.value) await refreshAttempt(); }
    finally { busy.value = false; }
}
</script>

<template>
    <div class="mx-auto max-w-5xl space-y-6" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <RouterLink :to="{ name: 'student.courses.index' }" class="text-sm font-bold text-brand">{{ t('learning.back') }}</RouterLink>
        <LoadingState v-if="loading" />
        <div v-else-if="error && !quiz" role="alert" class="rounded-2xl border border-red-200 bg-red-50 p-5 text-red-800">{{ t([401, 403, 404].includes(error.status) ? 'assessments.accessLost' : 'assessments.requestFailed') }} <BaseButton variant="secondary" class="mt-3" @click="load">{{ t('common.retry') }}</BaseButton></div>
        <template v-else-if="quiz">
            <header class="rounded-3xl bg-brand-dark p-6 text-white sm:p-8"><p class="text-xs font-bold uppercase tracking-widest text-accent">{{ t('assessments.quiz') }}</p><h1 class="mt-3 text-2xl font-black sm:text-3xl">{{ quiz.title }}</h1><p v-if="quiz.description" class="mt-3 whitespace-pre-wrap text-white/80">{{ quiz.description }}</p><p v-if="quiz.instructions" class="mt-4 whitespace-pre-wrap border-t border-white/20 pt-4 text-sm leading-7">{{ quiz.instructions }}</p></header>
            <div v-if="error" role="alert" class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ t([401, 403, 404].includes(error.status) ? 'assessments.accessLost' : error.status === 422 ? 'assessments.validationFailed' : 'assessments.requestFailed') }} {{ error.message }}</div>
            <p v-if="notice" role="status" class="text-sm font-bold text-emerald-700">{{ notice }}</p>
            <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5"><div><p class="font-bold">{{ remainingAttempts === null ? t('assessments.unlimitedAttempts') : t('assessments.remainingAttempts', { count: remainingAttempts }) }}</p><p v-if="quiz.available_until" class="mt-1 text-sm text-slate-500">{{ t('assessments.due', { date: formatDate(quiz.available_until, locale) }) }}</p></div><BaseButton v-if="canStart" :disabled="remainingAttempts === 0 || busy" :loading="busy" @click="start">{{ remainingAttempts === 0 ? t('assessments.limitReached') : t('assessments.start') }}</BaseButton></div>
            <section v-if="attempt" class="space-y-5"><div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-xl font-black">{{ t('assessments.attempt', { number: attempt.attempt_number }) }} · {{ t(`assessments.status.${attempt.status}`) }}</h2><p v-if="attempt.status === 'in_progress' && remainingTime" class="rounded-xl bg-amber-100 px-4 py-2 font-bold tabular-nums text-amber-900" role="timer">{{ t('assessments.timeRemaining', { time: remainingTime }) }}</p></div>
                <p v-if="attempt.status === 'in_progress' && remainingSeconds === 0" role="status" class="text-amber-800">{{ t('assessments.timeExpired') }}</p>
                <div v-if="attempt.status === 'submitted'" class="rounded-2xl border border-slate-200 bg-white p-5"><h3 class="font-bold">{{ t('assessments.result') }}</h3><p v-if="hasResult" class="mt-2 text-lg font-black">{{ t('assessments.score') }}: {{ attempt.score }} / {{ attempt.maximum_score }} · {{ attempt.percentage }}% · {{ t(attempt.passed ? 'assessments.passed' : 'assessments.failed') }}</p><p v-else class="mt-2 text-slate-600">{{ t('assessments.hiddenResults') }}</p><p v-if="attempt.submitted_at" class="mt-2 text-sm text-slate-500">{{ formatDate(attempt.submitted_at, locale) }}</p></div>
                <p v-if="attempt.status === 'in_progress'" class="text-sm text-slate-500">{{ t('assessments.unsavedAnswers') }}</p>
                <div v-for="(question, index) in attempt.questions || []" :key="question.id" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs font-bold text-brand">{{ t('assessments.question', { number: index + 1 }) }} · {{ question.points }} {{ t('assessments.points') }}</p><h3 class="mt-2 whitespace-pre-wrap text-lg font-bold">{{ question.question_text }}</h3><div class="mt-4 space-y-2"><label v-for="option in question.options" :key="option.id" class="flex min-h-11 items-center gap-3 rounded-xl border border-slate-200 px-4 py-2" :class="{ 'border-brand bg-brand/5': isSelected(question.id, option.id) }"><input :type="question.type === 'multiple_choice' ? 'checkbox' : 'radio'" :name="`question-${question.id}`" :checked="isSelected(question.id, option.id)" :disabled="attempt.status !== 'in_progress' || busy" @change="select(question, option.id)" /><span>{{ option.answer_text }}</span></label></div><p v-if="Object.hasOwn(question, 'earned_points')" class="mt-3 text-sm font-bold">{{ t('assessments.earned') }}: {{ question.earned_points }}</p><p v-if="Object.hasOwn(question, 'correct_option_ids')" class="mt-2 text-sm text-emerald-800">{{ t('assessments.correct') }}: {{ question.options.filter((option) => question.correct_option_ids.includes(option.id)).map((option) => option.answer_text).join('، ') }}</p><p v-if="Object.hasOwn(question, 'explanation') && question.explanation" class="mt-2 whitespace-pre-wrap text-sm text-slate-600">{{ t('assessments.explanation') }}: {{ question.explanation }}</p></div>
                <div v-if="attempt.status === 'in_progress'" class="flex flex-wrap gap-3"><BaseButton variant="secondary" :loading="busy" @click="save">{{ t('assessments.saveAnswers') }}</BaseButton><BaseButton :loading="busy" @click="submit">{{ t('assessments.submitQuiz') }}</BaseButton></div>
            </section>
            <section v-if="attempts.length > 1" class="rounded-2xl border border-slate-200 bg-white p-5"><h2 class="font-black">{{ t('assessments.attempts') }}</h2><div class="mt-3 flex flex-wrap gap-2"><BaseButton v-for="record in attempts" :key="record.id" variant="secondary" :disabled="busy || record.id === attempt?.id" @click="openAttempt(record.id)">{{ t('assessments.attempt', { number: record.attempt_number }) }} · {{ t(`assessments.status.${record.status}`) }}</BaseButton></div></section>
        </template>
    </div>
</template>

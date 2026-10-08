<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { addQuestion, removeQuestion, reorderQuestions, updateQuestion } from '../../api/assessment-authoring';
import { emptyQuestion, questionForEditing, questionPayload, questionTypes, validateQuestion } from '../../utils/assessment-authoring';
import BaseAlert from '../ui/BaseAlert.vue';
import BaseButton from '../ui/BaseButton.vue';

const props = defineProps({ quiz: { type: Object, required: true }, editable: Boolean });
const emit = defineEmits(['changed', 'dirty-change']);
const { t } = useI18n();
const editor = ref(null);
const baseline = ref('');
const previousType = ref('single_choice');
const errors = reactive({});
const serverValidation = reactive({});
const serverError = ref(null);
const busy = ref(false);
const questions = computed(() => props.quiz.questions ?? []);
const dirty = computed(() => editor.value !== null && JSON.stringify(editor.value) !== baseline.value);
watch(dirty, (value) => emit('dirty-change', value), { immediate: true });

function clearErrors() { Object.keys(errors).forEach((key) => delete errors[key]); Object.keys(serverValidation).forEach((key) => delete serverValidation[key]); serverError.value = null; }
function begin(question = null) {
    if (dirty.value && !window.confirm(t('assessmentAuthoring.unsaved'))) return;
    editor.value = question ? questionForEditing(question) : emptyQuestion();
    previousType.value = editor.value.type;
    baseline.value = JSON.stringify(editor.value);
    clearErrors();
}
function cancel(force = false) {
    if (!force && dirty.value && !window.confirm(t('assessmentAuthoring.unsaved'))) return;
    editor.value = null; clearErrors();
}
function changeType() {
    if ((editor.value.id || editor.value.options.some((option) => option.answer_text.trim())) && !window.confirm(t('assessmentAuthoring.changeTypeConfirm'))) {
        editor.value.type = previousType.value;
        return;
    }
    editor.value.options = emptyQuestion(editor.value.type).options;
    previousType.value = editor.value.type;
    clearErrors();
}
function setCorrect(index) {
    editor.value.options.forEach((option, optionIndex) => { option.is_correct = optionIndex === index; });
}
async function save() {
    if (busy.value || !editor.value) return;
    clearErrors();
    Object.assign(errors, validateQuestion(editor.value));
    if (Object.keys(errors).length) return;
    busy.value = true;
    try {
        const payload = questionPayload(editor.value);
        if (editor.value.id) await updateQuestion(props.quiz.id, editor.value.id, payload);
        else await addQuestion(props.quiz.id, payload);
        cancel(true);
        emit('changed');
    } catch (error) {
        if (error.status === 422 && error.errors) Object.keys(error.errors).forEach((key) => { serverValidation[key.split('.')[0]] = true; });
        serverError.value = error;
    }
    finally { busy.value = false; }
}
async function remove(question) {
    if (busy.value || !window.confirm(t('assessmentAuthoring.removeQuestionConfirm'))) return;
    busy.value = true; serverError.value = null;
    try { await removeQuestion(props.quiz.id, question.id); if (editor.value?.id === question.id) cancel(true); emit('changed'); }
    catch (error) { serverError.value = error; }
    finally { busy.value = false; }
}
async function move(index, direction) {
    const next = index + direction;
    if (busy.value || next < 0 || next >= questions.value.length) return;
    const ids = questions.value.map((question) => question.id);
    [ids[index], ids[next]] = [ids[next], ids[index]];
    busy.value = true; serverError.value = null;
    try { await reorderQuestions(props.quiz.id, ids); emit('changed'); }
    catch (error) { serverError.value = error; }
    finally { busy.value = false; }
}
</script>

<template>
    <section class="space-y-5 rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3"><div><h2 class="text-xl font-black text-slate-950">{{ t('assessmentAuthoring.questions') }}</h2><p class="text-sm text-slate-500">{{ t('assessmentAuthoring.questionCount', { count: questions.length }) }}</p></div><BaseButton v-if="editable && !editor" type="button" @click="begin()">{{ t('assessmentAuthoring.addQuestion') }}</BaseButton></div>
        <BaseAlert v-if="serverError" tone="danger">{{ t(serverError.status === 422 ? 'assessmentAuthoring.saveError' : 'assessmentAuthoring.actionError') }}</BaseAlert>
        <ol class="space-y-3"><li v-for="(question, index) in questions" :key="question.id" class="min-w-0 rounded-2xl border border-slate-200 bg-slate-50 p-4"><div class="flex flex-wrap items-start gap-3"><span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-soft text-sm font-black text-brand">{{ index + 1 }}</span><div class="min-w-0 flex-1"><p class="break-words font-bold text-slate-950">{{ question.question_text }}</p><p class="mt-1 text-xs text-slate-600">{{ t(`assessmentAuthoring.${{ single_choice: 'singleChoice', multiple_choice: 'multipleChoice', true_false: 'trueFalse' }[question.type]}`) }} · {{ question.points }} {{ t('assessmentAuthoring.points') }} · {{ t('assessmentAuthoring.answerCount', { count: question.options?.length ?? 0 }) }}</p></div><div v-if="editable" class="flex flex-wrap gap-1"><button type="button" class="min-h-11 rounded-xl border border-slate-300 px-3 disabled:opacity-40" :aria-label="t('assessmentAuthoring.moveUp')" :disabled="busy || index === 0" @click="move(index, -1)">↑</button><button type="button" class="min-h-11 rounded-xl border border-slate-300 px-3 disabled:opacity-40" :aria-label="t('assessmentAuthoring.moveDown')" :disabled="busy || index === questions.length - 1" @click="move(index, 1)">↓</button><button type="button" class="min-h-11 rounded-xl px-3 text-sm font-bold text-brand" :disabled="busy" @click="begin(question)">{{ t('assessmentAuthoring.editQuestion') }}</button><button type="button" class="min-h-11 rounded-xl px-3 text-sm font-bold text-red-700" :disabled="busy" @click="remove(question)">{{ t('assessmentAuthoring.removeQuestion') }}</button></div></div></li></ol>
        <form v-if="editor && editable" class="space-y-5 rounded-2xl border border-brand/20 bg-brand-soft/30 p-4 sm:p-6" novalidate @submit.prevent="save">
            <h3 class="text-lg font-black">{{ t(editor.id ? 'assessmentAuthoring.editQuestion' : 'assessmentAuthoring.addQuestion') }}</h3>
            <div class="grid gap-4 sm:grid-cols-2"><div><label for="question-type" class="mb-2 block text-sm font-bold">{{ t('assessmentAuthoring.questionType') }}</label><select id="question-type" v-model="editor.type" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3" @change="changeType"><option v-for="type in questionTypes" :key="type" :value="type">{{ t(`assessmentAuthoring.${{ single_choice: 'singleChoice', multiple_choice: 'multipleChoice', true_false: 'trueFalse' }[type]}`) }}</option></select></div><div><label for="question-points" class="mb-2 block text-sm font-bold">{{ t('assessmentAuthoring.points') }}</label><input id="question-points" v-model="editor.points" type="number" min="0.01" max="999999.99" step="0.01" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3" :aria-invalid="Boolean(errors.points || serverValidation.points)"><p v-if="errors.points || serverValidation.points" role="alert" class="mt-1 text-sm text-red-700">{{ t(`assessmentAuthoring.${errors.points ?? 'pointsInvalid'}`) }}</p></div></div>
            <div><label for="question-text" class="mb-2 block text-sm font-bold">{{ t('assessmentAuthoring.questionText') }}</label><textarea id="question-text" v-model="editor.question_text" rows="3" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2" :aria-invalid="Boolean(errors.question_text || serverValidation.question_text)" /><p v-if="errors.question_text || serverValidation.question_text" role="alert" class="mt-1 text-sm text-red-700">{{ t(`assessmentAuthoring.${errors.question_text ?? 'questionRequired'}`) }}</p></div>
            <fieldset class="space-y-3"><legend class="font-bold">{{ t(editor.type === 'multiple_choice' ? 'assessmentAuthoring.correctAnswers' : 'assessmentAuthoring.correctAnswer') }}</legend><div v-for="(option, index) in editor.options" :key="index" class="flex min-w-0 flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-white p-3"><input v-if="editor.type === 'multiple_choice'" v-model="option.is_correct" type="checkbox" :aria-label="t('assessmentAuthoring.correctAnswer') + ' ' + (index + 1)" class="size-5 accent-brand"><input v-else type="radio" name="correct-option" :checked="option.is_correct" :aria-label="t('assessmentAuthoring.correctAnswer') + ' ' + (index + 1)" class="size-5 accent-brand" @change="setCorrect(index)"><label :for="`answer-${index}`" class="sr-only">{{ t('assessmentAuthoring.option', { number: index + 1 }) }}</label><input :id="`answer-${index}`" v-model="option.answer_text" :readonly="editor.type === 'true_false'" class="min-h-11 min-w-0 flex-1 rounded-xl border border-slate-300 px-3" :aria-label="editor.type === 'true_false' ? t(index === 0 ? 'assessmentAuthoring.true' : 'assessmentAuthoring.false') : t('assessmentAuthoring.option', { number: index + 1 })"><button v-if="editor.type !== 'true_false' && editor.options.length > 2" type="button" class="min-h-11 rounded-xl px-2 text-sm font-bold text-red-700" :aria-label="t('assessmentAuthoring.removeOption')" @click="editor.options.splice(index, 1)">×</button></div><BaseButton v-if="editor.type !== 'true_false'" type="button" variant="secondary" @click="editor.options.push({ answer_text: '', is_correct: false })">{{ t('assessmentAuthoring.addOption') }}</BaseButton><p v-if="errors.options || serverValidation.options" role="alert" class="text-sm text-red-700">{{ t(`assessmentAuthoring.${errors.options ?? 'optionsInvalid'}`) }}</p></fieldset>
            <div><label for="question-explanation" class="mb-2 block text-sm font-bold">{{ t('assessmentAuthoring.explanation') }}</label><textarea id="question-explanation" v-model="editor.explanation" rows="2" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2" /></div>
            <div class="flex flex-wrap gap-3"><BaseButton type="submit" :loading="busy">{{ t('assessmentAuthoring.saveQuestion') }}</BaseButton><BaseButton type="button" variant="secondary" :disabled="busy" @click="cancel()">{{ t('assessmentAuthoring.cancel') }}</BaseButton></div>
        </form>
    </section>
</template>

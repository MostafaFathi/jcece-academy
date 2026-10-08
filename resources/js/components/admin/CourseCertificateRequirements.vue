<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { fetchCourseCertificateRequirements, saveCourseCertificateRequirements } from '../../api/certificate-policy';
import BaseAlert from '../ui/BaseAlert.vue';
import BaseButton from '../ui/BaseButton.vue';

const props = defineProps({ courseId: { type: [Number, String], required: true } });
const { t } = useI18n();
const state = reactive({ certificate_enabled: false, required_lesson_percentage: '', final_exam_required: false, final_exam_quiz_id: '', final_exam_passing_percentage: '', required_assignment_ids: [], admin_approval_required: false });
const quizzes = ref([]);
const assignments = ref([]);
const version = ref(0);
const loading = ref(true);
const loaded = ref(false);
const saving = ref(false);
const error = ref(null);
const saved = ref(false);

async function load() {
    loading.value = true; error.value = null;
    try {
        const data = await fetchCourseCertificateRequirements(props.courseId);
        Object.assign(state, {
            certificate_enabled: data.certificate_enabled,
            required_lesson_percentage: data.required_lesson_percentage ?? '',
            final_exam_required: data.final_exam_required,
            final_exam_quiz_id: data.final_exam_quiz_id ?? '',
            final_exam_passing_percentage: data.final_exam_passing_percentage ?? '',
            required_assignment_ids: data.required_assignment_ids,
            admin_approval_required: data.admin_approval_required,
        });
        quizzes.value = data.quizzes;
        assignments.value = data.assignments;
        version.value = data.requirements_version;
        loaded.value = true;
    } catch (failure) { error.value = failure; }
    finally { loading.value = false; }
}

async function save() {
    if (saving.value) return;
    saving.value = true; error.value = null; saved.value = false;
    try {
        const data = await saveCourseCertificateRequirements(props.courseId, {
            certificate_enabled: state.certificate_enabled,
            required_lesson_percentage: state.required_lesson_percentage,
            final_exam_required: state.final_exam_required,
            final_exam_quiz_id: state.final_exam_required ? Number(state.final_exam_quiz_id) : null,
            final_exam_passing_percentage: state.final_exam_required ? state.final_exam_passing_percentage : null,
            required_assignment_ids: state.required_assignment_ids.map(Number),
            admin_approval_required: state.admin_approval_required,
        });
        version.value = data.requirements_version;
        saved.value = true;
    } catch (failure) { error.value = failure; }
    finally { saving.value = false; }
}

onMounted(load);
</script>

<template>
    <section class="space-y-5 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        <div><h2 class="text-lg font-black">{{ t('certificatePolicy.title') }}</h2><p class="mt-1 text-sm text-slate-600">{{ t('certificatePolicy.description') }}</p></div>
        <p v-if="loading" role="status">{{ t('common.loading') }}</p>
        <BaseAlert v-else-if="error && !loaded" tone="danger">{{ t('admin.loadError') }} <button type="button" class="underline" @click="load">{{ t('common.retry') }}</button></BaseAlert>
        <form v-else class="space-y-5" @submit.prevent="save">
            <BaseAlert v-if="version === 0">{{ t('certificatePolicy.notConfigured') }}</BaseAlert>
            <BaseAlert v-if="saved" tone="success">{{ t('certificatePolicy.saved') }}</BaseAlert>
            <BaseAlert v-if="error" tone="danger">{{ t('certificatePolicy.validation') }}</BaseAlert>
            <label class="flex items-center gap-3 text-sm font-bold"><input v-model="state.certificate_enabled" type="checkbox" class="size-5 accent-brand">{{ t('certificatePolicy.enabled') }}</label>
            <div><label for="certificate-lessons" class="mb-2 block text-sm font-bold">{{ t('certificatePolicy.lessonPercentage') }}</label><input id="certificate-lessons" v-model="state.required_lesson_percentage" type="number" min="0" max="100" step="0.01" required class="w-full max-w-xs rounded-xl border border-slate-300 px-4 py-3"><p v-if="error?.errors?.required_lesson_percentage" role="alert" class="mt-1 text-sm text-red-700">{{ error.errors.required_lesson_percentage[0] }}</p></div>
            <label class="flex items-center gap-3 text-sm font-bold"><input v-model="state.final_exam_required" type="checkbox" class="size-5 accent-brand">{{ t('certificatePolicy.finalRequired') }}</label>
            <div v-if="state.final_exam_required" class="grid gap-4 sm:grid-cols-2"><div><label for="certificate-quiz" class="mb-2 block text-sm font-bold">{{ t('certificatePolicy.finalQuiz') }}</label><select id="certificate-quiz" v-model="state.final_exam_quiz_id" required class="w-full rounded-xl border border-slate-300 px-4 py-3"><option value="" disabled>{{ t('certificatePolicy.chooseQuiz') }}</option><option v-for="quiz in quizzes" :key="quiz.id" :value="quiz.id">{{ quiz.title }} ({{ t(`certificatePolicy.contentStatus.${quiz.status}`) }})</option></select><p v-if="error?.errors?.final_exam_quiz_id" role="alert" class="mt-1 text-sm text-red-700">{{ error.errors.final_exam_quiz_id[0] }}</p></div><div><label for="certificate-score" class="mb-2 block text-sm font-bold">{{ t('certificatePolicy.passingPercentage') }}</label><input id="certificate-score" v-model="state.final_exam_passing_percentage" type="number" min="0" max="100" step="0.01" required class="w-full rounded-xl border border-slate-300 px-4 py-3"><p v-if="error?.errors?.final_exam_passing_percentage" role="alert" class="mt-1 text-sm text-red-700">{{ error.errors.final_exam_passing_percentage[0] }}</p></div></div>
            <fieldset class="space-y-2"><legend class="mb-2 text-sm font-bold">{{ t('certificatePolicy.assignments') }}</legend><p v-if="!assignments.length" class="text-sm text-slate-500">{{ t('certificatePolicy.noAssignments') }}</p><label v-for="assignment in assignments" :key="assignment.id" class="flex items-center gap-3 rounded-xl border border-slate-200 p-3 text-sm"><input v-model="state.required_assignment_ids" type="checkbox" :value="assignment.id" class="size-5 accent-brand">{{ assignment.title }} ({{ t(`certificatePolicy.contentStatus.${assignment.status}`) }})</label><p v-if="error?.errors?.required_assignment_ids" role="alert" class="text-sm text-red-700">{{ error.errors.required_assignment_ids[0] }}</p></fieldset>
            <label class="flex items-center gap-3 text-sm font-bold"><input v-model="state.admin_approval_required" type="checkbox" class="size-5 accent-brand">{{ t('certificatePolicy.approvalRequired') }}</label>
            <BaseButton type="submit" :loading="saving">{{ t('certificatePolicy.save') }}</BaseButton>
        </form>
    </section>
</template>

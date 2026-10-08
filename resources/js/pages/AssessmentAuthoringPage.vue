<script setup>
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue';
import { onBeforeRouteLeave, useRoute, useRouter } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../stores/auth';
import { fetchAdminCourse } from '../api/admin';
import { fetchSections } from '../api/admin-curriculum';
import { fetchInstructorCourse, fetchInstructorCurriculum } from '../api/instructor';
import { archiveAssignment, archiveQuiz, createAssignment, createQuiz, downloadAssignmentAttachment, getAssignment, getQuiz, publishAssignment, publishQuiz, removeAssignmentAttachment, unpublishAssignment, unpublishQuiz, updateAssignment, updateQuiz, uploadAssignmentAttachment } from '../api/assessment-authoring';
import { submissionTypes, toLocalDateTime, toServerDateTime, validateDefinition } from '../utils/assessment-authoring';
import QuizQuestionBuilder from '../components/assessments/QuizQuestionBuilder.vue';
import PageHeading from '../components/ui/PageHeading.vue';
import LoadingState from '../components/ui/LoadingState.vue';
import BaseAlert from '../components/ui/BaseAlert.vue';
import BaseButton from '../components/ui/BaseButton.vue';

const props = defineProps({ kind: { type: String, required: true }, scope: { type: String, required: true } });
const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const { t, locale } = useI18n();
const courseId = computed(() => route.params.courseId);
const definitionId = computed(() => props.kind === 'quiz' ? route.params.quizId : route.params.assignmentId);
const isQuiz = computed(() => props.kind === 'quiz');
const permission = computed(() => isQuiz.value ? 'assessments' : 'assignments');
const course = ref(null);
const lessons = ref([]);
const definition = ref(null);
const loading = ref(true);
const error = ref(null);
const actionError = ref(null);
const errors = reactive({});
const form = reactive({ title: '', description: '', instructions: '', lesson_id: '', passing_score: 70, maximum_score: 100, submission_type: 'text', max_attempts: '', time_limit_minutes: '', shuffle_questions: false, shuffle_answers: false, show_results: true, show_correct_answers: false, available_from: '', available_until: '', due_at: '', allow_late_submissions: false });
const baseline = ref('');
const questionDirty = ref(false);
const selectedFile = ref(null);
const fileInput = ref(null);
const busy = ref(false);
const saved = ref(false);
const canEdit = computed(() => definition.value
    ? definition.value.status !== 'archived' && auth.can(`${permission.value}.update`) && definition.value.capabilities?.can_update === true && Number(definition.value.course_id) === Number(courseId.value)
    : Boolean(course.value?.capabilities?.[isQuiz.value ? 'can_create_quiz' : 'can_create_assignment']));
const canPublish = computed(() => Boolean(definition.value && definition.value.status !== 'archived' && auth.can(`${permission.value}.publish`) && definition.value.capabilities?.can_publish));
const canArchive = computed(() => Boolean(definition.value && definition.value.status !== 'archived' && auth.can(`${permission.value}.delete`) && definition.value.capabilities?.can_delete));
const dirty = computed(() => JSON.stringify(form) !== baseline.value || questionDirty.value || Boolean(selectedFile.value));
const backRoute = computed(() => props.scope === 'instructor'
    ? { name: 'instructor.courses.show', params: { courseId: courseId.value } }
    : { name: 'admin.courses.assessments', params: { courseId: courseId.value } });

function resetErrors() { Object.keys(errors).forEach((key) => delete errors[key]); actionError.value = null; saved.value = false; }
function fieldError(name) {
    const value = errors[name];
    return value && ['titleRequired', 'scoreInvalid', 'passingInvalid', 'attemptsInvalid', 'timeInvalid', 'dateInvalid', 'fieldInvalid'].includes(value)
        ? t(`assessmentAuthoring.${value}`) : value ? t('assessmentAuthoring.fieldInvalid') : '';
}
function hydrate(item) {
    Object.assign(form, {
        title: item.title ?? '', description: item.description ?? '', instructions: item.instructions ?? '', lesson_id: item.lesson_id ?? '',
        passing_score: item.passing_score ?? 70, maximum_score: item.maximum_score ?? 100, submission_type: item.submission_type ?? 'text',
        max_attempts: item.max_attempts ?? '', time_limit_minutes: item.time_limit_minutes ?? '', shuffle_questions: Boolean(item.shuffle_questions),
        shuffle_answers: Boolean(item.shuffle_answers), show_results: item.show_results ?? true, show_correct_answers: Boolean(item.show_correct_answers),
        available_from: toLocalDateTime(item.available_from), available_until: toLocalDateTime(item.available_until), due_at: toLocalDateTime(item.due_at),
        allow_late_submissions: Boolean(item.allow_late_submissions),
    });
    baseline.value = JSON.stringify(form);
}
async function load() {
    loading.value = true; error.value = null;
    try {
        course.value = props.scope === 'instructor' ? await fetchInstructorCourse(courseId.value) : await fetchAdminCourse(courseId.value);
        if (course.value.capabilities?.can_view_curriculum) {
            const sections = props.scope === 'instructor' ? (await fetchInstructorCurriculum(courseId.value)).items : await fetchSections(courseId.value);
            lessons.value = sections.flatMap((section) => (section.lessons ?? []).map((lesson) => ({ id: lesson.id, title: `${section.title} · ${lesson.title}` })));
        }
        if (definitionId.value) {
            const item = isQuiz.value ? await getQuiz(courseId.value, definitionId.value) : await getAssignment(courseId.value, definitionId.value);
            if (Number(item.course_id) !== Number(courseId.value)) throw { status: 404 };
            definition.value = item;
            hydrate(item);
        } else hydrate({});
    } catch (failure) { error.value = failure; }
    finally { loading.value = false; }
}
function payload() {
    const common = { title: form.title.trim(), description: form.description.trim() || null, instructions: form.instructions.trim() || null, lesson_id: form.lesson_id === '' ? null : Number(form.lesson_id), max_attempts: form.max_attempts === '' ? null : Number(form.max_attempts), available_from: toServerDateTime(form.available_from) };
    return isQuiz.value ? { ...common, passing_score: Number(form.passing_score), time_limit_minutes: form.time_limit_minutes === '' ? null : Number(form.time_limit_minutes), shuffle_questions: form.shuffle_questions, shuffle_answers: form.shuffle_answers, show_results: form.show_results, show_correct_answers: form.show_correct_answers, available_until: toServerDateTime(form.available_until) }
        : { ...common, submission_type: form.submission_type, maximum_score: Number(form.maximum_score), passing_score: form.passing_score === '' ? null : Number(form.passing_score), due_at: toServerDateTime(form.due_at), allow_late_submissions: form.allow_late_submissions };
}
async function save() {
    if (busy.value || !canEdit.value) return;
    resetErrors(); Object.assign(errors, validateDefinition(form, props.kind));
    if (Object.keys(errors).length) return;
    busy.value = true;
    try {
        const item = definition.value
            ? (isQuiz.value ? await updateQuiz(courseId.value, definition.value.id, payload()) : await updateAssignment(courseId.value, definition.value.id, payload()))
            : (isQuiz.value ? await createQuiz(courseId.value, payload()) : await createAssignment(courseId.value, payload()));
        const wasNew = !definition.value;
        definition.value = item;
        hydrate(item);
        saved.value = true;
        if (wasNew) await router.replace({ name: `${props.scope}.${isQuiz.value ? 'quizzes' : 'assignments'}.edit`, params: { courseId: courseId.value, [`${props.kind}Id`]: item.id } });
    } catch (failure) { actionError.value = failure; Object.assign(errors, Object.fromEntries(Object.entries(failure.errors ?? {}).map(([key]) => [key, 'fieldInvalid']))); }
    finally { busy.value = false; }
}
async function refreshQuestions() {
    const fresh = await getQuiz(courseId.value, definition.value.id);
    definition.value = { ...definition.value, questions: fresh.questions, status: fresh.status };
}
async function changeStatus(action) {
    if (busy.value || dirty.value || !definition.value) { if (dirty.value) actionError.value = { localMessage: t('assessmentAuthoring.unsaved') }; return; }
    if (action === 'publish' && isQuiz.value && !(definition.value.questions?.length)) { actionError.value = { localMessage: t('assessmentAuthoring.needsQuestion') }; return; }
    if (action === 'archive' && !window.confirm(t('assessmentAuthoring.archiveConfirm'))) return;
    if (action === 'unpublish' && !window.confirm(t('assessmentAuthoring.unpublishConfirm'))) return;
    busy.value = true; actionError.value = null;
    try {
        definition.value = isQuiz.value
            ? (action === 'publish' ? await publishQuiz(definition.value.id) : action === 'unpublish' ? await unpublishQuiz(definition.value.id) : await archiveQuiz(courseId.value, definition.value.id))
            : (action === 'publish' ? await publishAssignment(definition.value.id) : action === 'unpublish' ? await unpublishAssignment(definition.value.id) : await archiveAssignment(courseId.value, definition.value.id));
        saved.value = true;
    } catch (failure) { actionError.value = failure; }
    finally { busy.value = false; }
}
async function uploadFile() {
    if (busy.value || !selectedFile.value || !canEdit.value) return;
    busy.value = true; actionError.value = null;
    try {
        const attachment = await uploadAssignmentAttachment(definition.value.id, selectedFile.value);
        definition.value = { ...definition.value, attachments: [...(definition.value.attachments ?? []), attachment] };
        selectedFile.value = null;
        if (fileInput.value) fileInput.value.value = '';
    } catch (failure) { actionError.value = failure; }
    finally { busy.value = false; }
}
async function removeFile(attachment) {
    if (busy.value || !window.confirm(t('assessmentAuthoring.removeAttachmentConfirm'))) return;
    busy.value = true; actionError.value = null;
    try { await removeAssignmentAttachment(definition.value.id, attachment.id); definition.value = { ...definition.value, attachments: definition.value.attachments.filter((item) => item.id !== attachment.id) }; }
    catch (failure) { actionError.value = failure; }
    finally { busy.value = false; }
}
async function downloadFile(attachment) {
    actionError.value = null;
    try { await downloadAssignmentAttachment(attachment); }
    catch (failure) { actionError.value = failure; }
}
function leaveWarning(event) { if (dirty.value) { event.preventDefault(); event.returnValue = ''; } }
onBeforeRouteLeave(() => !dirty.value || window.confirm(t('assessmentAuthoring.unsaved')));
onMounted(() => { load(); window.addEventListener('beforeunload', leaveWarning); });
onUnmounted(() => window.removeEventListener('beforeunload', leaveWarning));
</script>

<template>
    <div class="mx-auto max-w-6xl space-y-6" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <RouterLink :to="backRoute" class="inline-flex min-h-11 items-center text-sm font-bold text-brand underline">{{ t('assessmentAuthoring.back') }}</RouterLink>
        <LoadingState v-if="loading" />
        <BaseAlert v-else-if="error" tone="danger">{{ t(error.status === 403 ? 'assessmentAuthoring.forbidden' : 'assessmentAuthoring.loadError') }} <button type="button" class="font-bold underline" @click="load">{{ t('common.retry') }}</button></BaseAlert>
        <template v-else-if="course">
            <PageHeading :title="t(`assessmentAuthoring.${definition ? (isQuiz ? 'editQuiz' : 'editAssignment') : (isQuiz ? 'createQuiz' : 'createAssignment')}`)" :description="course.title" />
            <div v-if="definition" class="flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4"><span class="text-sm font-bold text-slate-600">{{ t('assessmentAuthoring.status') }}:</span><span class="rounded-full px-3 py-1 text-sm font-bold" :class="definition.status === 'published' ? 'bg-emerald-100 text-emerald-900' : definition.status === 'archived' ? 'bg-slate-200 text-slate-700' : 'bg-amber-100 text-amber-900'">{{ t(`assessmentAuthoring.${definition.status}`) }}</span><RouterLink v-if="scope === 'instructor' && auth.can('assignment_submissions.view')" :to="{ name: isQuiz ? 'instructor.quizzes.show' : 'instructor.assignments.show', params: { courseId, [isQuiz ? 'quizId' : 'assignmentId']: definition.id } }" class="ms-auto text-sm font-bold text-brand underline">{{ t(isQuiz ? 'assessmentAuthoring.results' : 'assessmentAuthoring.submissions') }}</RouterLink></div>
            <BaseAlert v-if="!canEdit" tone="warning">{{ t('assessmentAuthoring.readonly') }}</BaseAlert>
            <BaseAlert v-if="saved" tone="success">{{ t('assessmentAuthoring.saved') }}</BaseAlert>
            <BaseAlert v-if="actionError" tone="danger">{{ actionError.localMessage ?? t(actionError.status === 422 ? 'assessmentAuthoring.saveError' : 'assessmentAuthoring.actionError') }}</BaseAlert>
            <nav class="flex flex-wrap gap-2 text-sm font-bold" :aria-label="t('assessmentAuthoring.heading')"><a href="#assessment-details" class="rounded-xl bg-brand-soft px-3 py-2 text-brand">{{ t('assessmentAuthoring.details') }}</a><a :href="isQuiz ? '#assessment-questions' : '#assessment-attachments'" class="rounded-xl bg-brand-soft px-3 py-2 text-brand">{{ t(isQuiz ? 'assessmentAuthoring.questions' : 'assessmentAuthoring.attachments') }}</a><a href="#assessment-settings" class="rounded-xl bg-brand-soft px-3 py-2 text-brand">{{ t('assessmentAuthoring.settings') }}</a><a href="#assessment-review" class="rounded-xl bg-brand-soft px-3 py-2 text-brand">{{ t('assessmentAuthoring.review') }}</a></nav>
            <form class="space-y-6" novalidate @submit.prevent="save">
                <section id="assessment-details" class="space-y-4 rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6"><h2 class="text-xl font-black">{{ t('assessmentAuthoring.details') }}</h2><div class="grid gap-4 sm:grid-cols-2"><div><label for="assessment-course" class="mb-2 block text-sm font-bold">{{ t('assessmentAuthoring.course') }}</label><p id="assessment-course" class="min-h-11 rounded-xl bg-slate-100 px-4 py-3 text-sm font-bold">{{ course.title }}</p></div><div><label for="assessment-lesson" class="mb-2 block text-sm font-bold">{{ t('assessmentAuthoring.lesson') }}</label><select v-if="course.capabilities?.can_view_curriculum" id="assessment-lesson" v-model="form.lesson_id" :disabled="!canEdit || busy" class="min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3"><option value="">{{ t('assessmentAuthoring.noLesson') }}</option><option v-for="lesson in lessons" :key="lesson.id" :value="lesson.id">{{ lesson.title }}</option></select><p v-else class="text-sm text-slate-500">{{ t('assessmentAuthoring.lessonUnavailable') }}</p><p v-if="errors.lesson_id" role="alert" class="mt-1 text-sm text-red-700">{{ fieldError('lesson_id') }}</p></div></div>
                    <div><label for="assessment-title" class="mb-2 block text-sm font-bold">{{ t('assessmentAuthoring.title') }}</label><input id="assessment-title" v-model="form.title" maxlength="255" :disabled="!canEdit || busy" :aria-invalid="Boolean(errors.title)" class="min-h-11 w-full rounded-xl border border-slate-300 px-4"><p v-if="errors.title" role="alert" class="mt-1 text-sm text-red-700">{{ fieldError('title') }}</p></div>
                    <div class="grid gap-4 md:grid-cols-2"><div><label for="assessment-description" class="mb-2 block text-sm font-bold">{{ t('assessmentAuthoring.description') }}</label><textarea id="assessment-description" v-model="form.description" rows="4" :disabled="!canEdit || busy" class="w-full rounded-xl border border-slate-300 px-4 py-3" /></div><div><label for="assessment-instructions" class="mb-2 block text-sm font-bold">{{ t('assessmentAuthoring.instructions') }}</label><textarea id="assessment-instructions" v-model="form.instructions" rows="4" :disabled="!canEdit || busy" class="w-full rounded-xl border border-slate-300 px-4 py-3" /></div></div>
                </section>
                <section id="assessment-settings" class="space-y-4 rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6"><h2 class="text-xl font-black">{{ t('assessmentAuthoring.settings') }}</h2><div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-if="!isQuiz"><label for="assessment-type" class="mb-2 block text-sm font-bold">{{ t('assessmentAuthoring.submissionType') }}</label><select id="assessment-type" v-model="form.submission_type" :disabled="!canEdit || busy" class="min-h-11 w-full rounded-xl border border-slate-300 px-3"><option v-for="type in submissionTypes" :key="type" :value="type">{{ t(`assessmentAuthoring.${{ text: 'text', file: 'file', text_and_file: 'textAndFile' }[type]}`) }}</option></select></div>
                    <div><label for="assessment-score" class="mb-2 block text-sm font-bold">{{ t(isQuiz ? 'assessmentAuthoring.passingScore' : 'assessmentAuthoring.maximumScore') }}</label><input id="assessment-score" v-model="form[isQuiz ? 'passing_score' : 'maximum_score']" type="number" :min="isQuiz ? 0 : 0.01" :max="isQuiz ? 100 : 99999999.99" step="0.01" :disabled="!canEdit || busy" :aria-invalid="Boolean(errors[isQuiz ? 'passing_score' : 'maximum_score'])" class="min-h-11 w-full rounded-xl border border-slate-300 px-3"><p v-if="errors[isQuiz ? 'passing_score' : 'maximum_score']" role="alert" class="mt-1 text-sm text-red-700">{{ fieldError(isQuiz ? 'passing_score' : 'maximum_score') }}</p></div>
                    <div v-if="!isQuiz"><label for="assignment-passing" class="mb-2 block text-sm font-bold">{{ t('assessmentAuthoring.assignmentPassing') }}</label><input id="assignment-passing" v-model="form.passing_score" type="number" min="0" :max="form.maximum_score" step="0.01" :disabled="!canEdit || busy" class="min-h-11 w-full rounded-xl border border-slate-300 px-3"><p v-if="errors.passing_score" role="alert" class="mt-1 text-sm text-red-700">{{ fieldError('passing_score') }}</p></div>
                    <div><label for="assessment-attempts" class="mb-2 block text-sm font-bold">{{ t('assessmentAuthoring.attempts') }}</label><input id="assessment-attempts" v-model="form.max_attempts" type="number" min="1" max="1000" step="1" :disabled="!canEdit || busy" class="min-h-11 w-full rounded-xl border border-slate-300 px-3"><p class="mt-1 text-xs text-slate-500">{{ t('assessmentAuthoring.unlimited') }}</p><p v-if="errors.max_attempts" role="alert" class="mt-1 text-sm text-red-700">{{ fieldError('max_attempts') }}</p></div>
                    <div v-if="isQuiz"><label for="assessment-time" class="mb-2 block text-sm font-bold">{{ t('assessmentAuthoring.timeLimit') }}</label><input id="assessment-time" v-model="form.time_limit_minutes" type="number" min="1" max="10080" step="1" :disabled="!canEdit || busy" class="min-h-11 w-full rounded-xl border border-slate-300 px-3"><p v-if="errors.time_limit_minutes" role="alert" class="mt-1 text-sm text-red-700">{{ fieldError('time_limit_minutes') }}</p></div>
                    <div><label for="assessment-from" class="mb-2 block text-sm font-bold">{{ t('assessmentAuthoring.availableFrom') }}</label><input id="assessment-from" v-model="form.available_from" type="datetime-local" :disabled="!canEdit || busy" class="min-h-11 w-full rounded-xl border border-slate-300 px-3"></div><div><label for="assessment-end" class="mb-2 block text-sm font-bold">{{ t(isQuiz ? 'assessmentAuthoring.availableUntil' : 'assessmentAuthoring.dueAt') }}</label><input id="assessment-end" v-model="form[isQuiz ? 'available_until' : 'due_at']" type="datetime-local" :disabled="!canEdit || busy" class="min-h-11 w-full rounded-xl border border-slate-300 px-3"><p v-if="errors[isQuiz ? 'available_until' : 'due_at']" role="alert" class="mt-1 text-sm text-red-700">{{ t('assessmentAuthoring.dateInvalid') }}</p></div>
                </div><div v-if="isQuiz" class="grid gap-3 sm:grid-cols-2"><label v-for="setting in ['shuffle_questions', 'shuffle_answers', 'show_results', 'show_correct_answers']" :key="setting" class="flex min-h-11 items-center gap-3 rounded-xl border border-slate-200 p-3 text-sm font-bold"><input v-model="form[setting]" type="checkbox" :disabled="!canEdit || busy" class="size-5 accent-brand">{{ t(`assessmentAuthoring.${{ shuffle_questions: 'shuffleQuestions', shuffle_answers: 'shuffleAnswers', show_results: 'showResults', show_correct_answers: 'showCorrectAnswers' }[setting]}`) }}</label></div><label v-else class="flex min-h-11 items-center gap-3 rounded-xl border border-slate-200 p-3 text-sm font-bold"><input v-model="form.allow_late_submissions" type="checkbox" :disabled="!canEdit || busy" class="size-5 accent-brand">{{ t('assessmentAuthoring.late') }}</label></section>
                <div v-if="canEdit" class="flex flex-wrap gap-3"><BaseButton type="submit" :loading="busy">{{ t(definition ? 'assessmentAuthoring.saveChanges' : 'assessmentAuthoring.saveDraft') }}</BaseButton></div>
            </form>
            <div v-if="isQuiz" id="assessment-questions"><QuizQuestionBuilder v-if="definition" :quiz="definition" :editable="canEdit" @changed="refreshQuestions" @dirty-change="questionDirty = $event" /><BaseAlert v-else>{{ t('assessmentAuthoring.publishHint') }}</BaseAlert></div>
            <section v-else id="assessment-attachments" class="space-y-4 rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6"><h2 class="text-xl font-black">{{ t('assessmentAuthoring.attachments') }}</h2><p class="text-sm text-slate-500">{{ t('assessmentAuthoring.attachmentHint') }}</p><p v-if="!definition?.attachments?.length" class="text-sm text-slate-500">{{ t('assessmentAuthoring.noAttachments') }}</p><ul v-else class="space-y-2"><li v-for="attachment in definition.attachments" :key="attachment.id" class="flex min-w-0 flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3"><span class="min-w-0 flex-1 break-all text-sm font-bold">{{ attachment.original_filename }} <span class="text-xs font-normal text-slate-500">{{ t('assessmentAuthoring.fileSize', { size: Math.ceil(attachment.file_size / 1024) }) }}</span></span><button type="button" class="min-h-11 text-sm font-bold text-brand underline" @click="downloadFile(attachment)">{{ t('assessmentAuthoring.download') }}</button><button v-if="canEdit" type="button" :disabled="busy" class="min-h-11 text-sm font-bold text-red-700 underline" @click="removeFile(attachment)">{{ t('assessmentAuthoring.remove') }}</button></li></ul><div v-if="definition && canEdit" class="flex min-w-0 flex-wrap items-end gap-3"><div class="min-w-0 flex-1"><label for="assignment-attachment" class="mb-2 block text-sm font-bold">{{ t('assessmentAuthoring.chooseFile') }}</label><input id="assignment-attachment" ref="fileInput" type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.zip,.txt" class="block w-full min-w-0 text-sm" @change="selectedFile = $event.target.files?.[0] ?? null"></div><BaseButton type="button" :disabled="!selectedFile" :loading="busy" @click="uploadFile">{{ t('assessmentAuthoring.upload') }}</BaseButton></div><BaseAlert v-if="!definition">{{ t('assessmentAuthoring.publishAssignmentHint') }}</BaseAlert></section>
            <section id="assessment-review" class="space-y-4 rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6"><h2 class="text-xl font-black">{{ t('assessmentAuthoring.review') }}</h2><p class="text-sm text-slate-600">{{ t(isQuiz ? 'assessmentAuthoring.publishHint' : 'assessmentAuthoring.publishAssignmentHint') }}</p><div class="flex flex-wrap gap-3"><BaseButton v-if="canPublish && definition.status !== 'published'" type="button" :disabled="busy || dirty" @click="changeStatus('publish')">{{ t('assessmentAuthoring.publish') }}</BaseButton><BaseButton v-if="canPublish && definition.status === 'published'" type="button" variant="secondary" :disabled="busy || dirty" @click="changeStatus('unpublish')">{{ t('assessmentAuthoring.unpublish') }}</BaseButton><BaseButton v-if="canArchive" type="button" variant="secondary" :disabled="busy || dirty" @click="changeStatus('archive')">{{ t('assessmentAuthoring.archive') }}</BaseButton></div></section>
        </template>
    </div>
</template>

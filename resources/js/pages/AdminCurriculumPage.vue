<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import { fetchAdminCourse } from '../api/admin';
import { createLesson, createSection, deleteLesson, deleteSection, fetchSections, reorderLessons, reorderSections, updateLesson, updateSection } from '../api/admin-curriculum';
import AdminLessonEditor from '../components/admin/AdminLessonEditor.vue';
import AdminLessonResources from '../components/admin/AdminLessonResources.vue';
import PageHeading from '../components/ui/PageHeading.vue';
import LoadingState from '../components/ui/LoadingState.vue';
import BaseAlert from '../components/ui/BaseAlert.vue';
import BaseButton from '../components/ui/BaseButton.vue';

const route = useRoute();
const { t, locale } = useI18n();
const courseId = route.params.id ?? route.params.courseId;
const instructorWorkspace = route.name === 'instructor.courses.curriculum';
const course = ref(null);
const sections = ref([]);
const loading = ref(true);
const busy = ref(false);
const error = ref(null);
const editError = ref(null);
const openSectionId = ref(null);
const sectionEditorId = ref(null);
const sectionForm = reactive({ title: '', description: '', is_active: true });
const lessonEditor = ref(null);
const resourceLessonId = ref(null);
const canEditCourse = computed(() => course.value?.capabilities?.can_update_course === true && !instructorWorkspace);
const canCreateCurriculum = computed(() => course.value?.capabilities?.can_create_curriculum === true);
const canUpdateCurriculum = computed(() => course.value?.capabilities?.can_update_curriculum === true);
const canDeleteCurriculum = computed(() => course.value?.capabilities?.can_delete_curriculum === true);

async function refreshSections() {
    sections.value = await fetchSections(courseId);
    if (openSectionId.value === null && sections.value.length) openSectionId.value = sections.value[0].id;
}
async function load() {
    loading.value = true; error.value = null;
    try { [course.value, sections.value] = await Promise.all([fetchAdminCourse(courseId), fetchSections(courseId)]); if (sections.value.length && openSectionId.value === null) openSectionId.value = sections.value[0].id; }
    catch (failure) { error.value = failure; }
    finally { loading.value = false; }
}
onMounted(load);
function editSection(section = null) {
    sectionEditorId.value = section?.id ?? 'new';
    if (section) openSectionId.value = section.id;
    Object.assign(sectionForm, { title: section?.title ?? '', description: section?.description ?? '', is_active: section?.is_active ?? true });
    editError.value = null;
}
async function saveSection() {
    if (busy.value) return;
    busy.value = true; editError.value = null;
    try {
        const payload = { title: sectionForm.title.trim(), description: sectionForm.description || null, is_active: sectionForm.is_active };
        if (sectionEditorId.value === 'new') await createSection(courseId, payload);
        else await updateSection(courseId, sectionEditorId.value, payload);
        sectionEditorId.value = null; await refreshSections();
    } catch (failure) { editError.value = failure; }
    finally { busy.value = false; }
}
async function removeSection(section) {
    if (busy.value || !window.confirm(t('curriculum.confirmDeleteSection', { count: section.lessons?.length ?? 0 }))) return;
    busy.value = true; error.value = null;
    try { await deleteSection(courseId, section.id); if (openSectionId.value === section.id) openSectionId.value = null; await refreshSections(); }
    catch (failure) { error.value = failure; await refreshSections(); }
    finally { busy.value = false; }
}
function editLesson(section, lesson = null) { lessonEditor.value = { sectionId: section.id, lesson }; editError.value = null; openSectionId.value = section.id; }
async function saveLesson(payload) {
    if (busy.value || !lessonEditor.value) return;
    busy.value = true; editError.value = null;
    try {
        const sectionId = lessonEditor.value.sectionId;
        const existingLesson = lessonEditor.value.lesson;
        const savedLesson = existingLesson
            ? await updateLesson(sectionId, existingLesson.id, payload)
            : await createLesson(sectionId, payload);
        const keepVideoEditorOpen = !existingLesson && payload.type === 'video' && !payload.is_preview && savedLesson?.id;
        lessonEditor.value = keepVideoEditorOpen ? { sectionId, lesson: savedLesson } : null;
        await refreshSections();
        if (keepVideoEditorOpen) {
            const refreshedLesson = sections.value.find((section) => section.id === sectionId)?.lessons?.find((lesson) => lesson.id === savedLesson.id);
            if (refreshedLesson) lessonEditor.value = { sectionId, lesson: refreshedLesson };
        }
    } catch (failure) { editError.value = failure; }
    finally { busy.value = false; }
}
async function removeLesson(section, lesson) {
    if (busy.value || !window.confirm(t('curriculum.confirmDeleteLesson'))) return;
    busy.value = true; error.value = null;
    try { await deleteLesson(section.id, lesson.id); if (resourceLessonId.value === lesson.id) resourceLessonId.value = null; await refreshSections(); }
    catch (failure) { error.value = failure; await refreshSections(); }
    finally { busy.value = false; }
}
async function move(items, index, direction, reorder) {
    if (busy.value) return;
    const target = index + direction;
    if (target < 0 || target >= items.length) return;
    const ids = items.map((item) => item.id);
    [ids[index], ids[target]] = [ids[target], ids[index]];
    busy.value = true; error.value = null;
    try { await reorder(ids); await refreshSections(); }
    catch (failure) { await refreshSections(); error.value = failure; }
    finally { busy.value = false; }
}
</script>

<template>
    <div class="mx-auto max-w-6xl space-y-6" :dir="locale === 'ar' ? 'rtl' : 'ltr'">
        <RouterLink :to="instructorWorkspace ? { name: 'instructor.courses.show', params: { courseId } } : { name: 'admin.courses.index' }" class="text-sm font-bold text-brand">{{ t('common.back') }}</RouterLink>
        <PageHeading :title="t('curriculum.heading')" :description="course?.title ?? t('curriculum.description')"><template #actions><BaseButton v-if="canCreateCurriculum" @click="editSection()">{{ t('curriculum.addSection') }}</BaseButton></template></PageHeading>
        <LoadingState v-if="loading" />
        <BaseAlert v-else-if="error && !course" tone="danger">{{ t(error.status === 403 ? 'admin.notAllowed' : 'admin.loadError') }} <button type="button" class="font-bold underline" @click="load">{{ t('common.retry') }}</button></BaseAlert>
        <template v-else-if="course">
            <BaseAlert v-if="error" tone="danger">{{ t(error.status === 422 ? 'admin.validation' : error.status === 403 ? 'admin.notAllowed' : 'admin.saveError') }} <button type="button" class="font-bold underline" @click="refreshSections">{{ t('common.retry') }}</button></BaseAlert>
            <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 text-sm text-slate-600"><span class="font-bold text-slate-900">{{ course.title }}</span><span>{{ t(`admin.courseStatus.${course.status}`) }}</span><span>{{ t('curriculum.sectionCount', { count: sections.length }) }}</span><RouterLink v-if="canEditCourse" :to="{ name: 'admin.courses.edit', params: { id: course.id } }" class="ms-auto font-bold text-brand underline">{{ t('admin.editCourse') }}</RouterLink></div>
            <form v-if="sectionEditorId === 'new'" class="space-y-4 rounded-2xl border border-brand/20 bg-white p-5" @submit.prevent="saveSection"><h2 class="font-black">{{ t('curriculum.addSection') }}</h2><label class="block text-sm font-bold" for="new-section-title">{{ t('admin.title') }}</label><input id="new-section-title" v-model="sectionForm.title" required maxlength="255" class="w-full rounded-xl border border-slate-300 px-4 py-3"><p v-if="editError?.errors?.title" role="alert" class="text-sm text-red-700">{{ editError.errors.title[0] }}</p><label class="block text-sm font-bold" for="new-section-description">{{ t('admin.description') }}</label><textarea id="new-section-description" v-model="sectionForm.description" rows="2" class="w-full rounded-xl border border-slate-300 px-4 py-3" /><label class="flex items-center gap-2 text-sm"><input v-model="sectionForm.is_active" type="checkbox" class="size-5 accent-brand">{{ t('admin.active') }}</label><div class="flex gap-2"><BaseButton type="submit" :loading="busy">{{ t('admin.create') }}</BaseButton><BaseButton variant="secondary" @click="sectionEditorId = null">{{ t('admin.cancel') }}</BaseButton></div></form>
            <BaseAlert v-if="!sections.length && sectionEditorId !== 'new'">{{ t('curriculum.empty') }}</BaseAlert>
            <ol class="space-y-5"><li v-for="(section, sectionIndex) in sections" :key="section.id" class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm"><div class="flex flex-wrap items-center gap-3 bg-slate-50 p-4 sm:p-5"><span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand text-sm font-black text-white">{{ sectionIndex + 1 }}</span><button type="button" class="min-w-0 flex-1 text-start font-black text-slate-950" :aria-expanded="openSectionId === section.id" @click="openSectionId = openSectionId === section.id ? null : section.id">{{ section.title }} <span class="ms-2 text-xs font-normal text-slate-500">{{ t('curriculum.lessonCount', { count: section.lessons?.length ?? 0 }) }}</span></button><span v-if="!section.is_active" class="rounded-full bg-amber-100 px-2 py-1 text-xs font-bold text-amber-900">{{ t('admin.inactive') }}</span><div class="flex flex-wrap gap-1"><button v-if="canUpdateCurriculum" type="button" class="min-h-11 rounded-xl border px-3 disabled:opacity-40" :disabled="busy || sectionIndex === 0" :aria-label="t('curriculum.moveEarlier')" @click="move(sections, sectionIndex, -1, (ids) => reorderSections(courseId, ids))">↑</button><button v-if="canUpdateCurriculum" type="button" class="min-h-11 rounded-xl border px-3 disabled:opacity-40" :disabled="busy || sectionIndex === sections.length - 1" :aria-label="t('curriculum.moveLater')" @click="move(sections, sectionIndex, 1, (ids) => reorderSections(courseId, ids))">↓</button><button v-if="canUpdateCurriculum" type="button" class="min-h-11 px-2 text-sm font-bold text-brand" @click="editSection(section)">{{ t('admin.edit') }}</button><button v-if="canDeleteCurriculum" type="button" class="min-h-11 px-2 text-sm font-bold text-red-700" :disabled="busy" @click="removeSection(section)">{{ t('admin.delete') }}</button></div></div>
                <div v-if="openSectionId === section.id" class="space-y-5 p-4 sm:p-6"><p v-if="section.description" class="whitespace-pre-wrap text-sm text-slate-600">{{ section.description }}</p><form v-if="sectionEditorId === section.id" class="space-y-3 rounded-2xl border border-brand/20 bg-brand/5 p-4" @submit.prevent="saveSection"><label class="block text-sm font-bold" for="edit-section-title">{{ t('admin.title') }}</label><input id="edit-section-title" v-model="sectionForm.title" required maxlength="255" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3"><p v-if="editError?.errors?.title" role="alert" class="text-sm text-red-700">{{ editError.errors.title[0] }}</p><label class="block text-sm font-bold" for="edit-section-description">{{ t('admin.description') }}</label><textarea id="edit-section-description" v-model="sectionForm.description" rows="2" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3" /><label class="flex items-center gap-2 text-sm"><input v-model="sectionForm.is_active" type="checkbox" class="size-5 accent-brand">{{ t('admin.active') }}</label><div class="flex gap-2"><BaseButton type="submit" :loading="busy">{{ t('admin.save') }}</BaseButton><BaseButton variant="secondary" @click="sectionEditorId = null">{{ t('admin.cancel') }}</BaseButton></div></form>
                    <div class="flex items-center justify-between gap-3"><h3 class="font-black">{{ t('curriculum.lessons') }}</h3><BaseButton v-if="canCreateCurriculum && !lessonEditor" variant="secondary" @click="editLesson(section)">{{ t('curriculum.addLesson') }}</BaseButton></div>
                    <p v-if="!section.lessons?.length" class="rounded-xl bg-slate-50 p-4 text-sm text-slate-600">{{ t('curriculum.noLessons') }}</p>
                    <ol class="space-y-3"><li v-for="(lesson, lessonIndex) in section.lessons ?? []" :key="lesson.id" class="rounded-2xl border border-slate-200 p-4"><div class="flex flex-wrap items-center gap-3"><span class="text-sm font-black text-slate-400">{{ sectionIndex + 1 }}.{{ lessonIndex + 1 }}</span><div class="min-w-0 flex-1"><p class="font-bold text-slate-950">{{ lesson.title }}</p><p class="text-xs text-slate-500">{{ t(`curriculum.types.${lesson.type}`) }} · {{ lesson.resources?.length ?? 0 }} {{ t('curriculum.resources') }}</p></div><div class="flex flex-wrap gap-1"><button v-if="canUpdateCurriculum" type="button" class="min-h-11 rounded-xl border px-3 disabled:opacity-40" :disabled="busy || lessonIndex === 0" :aria-label="t('curriculum.moveEarlier')" @click="move(section.lessons, lessonIndex, -1, (ids) => reorderLessons(section.id, ids))">↑</button><button v-if="canUpdateCurriculum" type="button" class="min-h-11 rounded-xl border px-3 disabled:opacity-40" :disabled="busy || lessonIndex === section.lessons.length - 1" :aria-label="t('curriculum.moveLater')" @click="move(section.lessons, lessonIndex, 1, (ids) => reorderLessons(section.id, ids))">↓</button><button v-if="canUpdateCurriculum" type="button" class="min-h-11 px-2 text-sm font-bold text-brand" @click="editLesson(section, lesson)">{{ t('admin.edit') }}</button><button type="button" class="min-h-11 px-2 text-sm font-bold text-brand" @click="resourceLessonId = resourceLessonId === lesson.id ? null : lesson.id">{{ t('curriculum.resources') }}</button><button v-if="canDeleteCurriculum" type="button" class="min-h-11 px-2 text-sm font-bold text-red-700" :disabled="busy" @click="removeLesson(section, lesson)">{{ t('admin.delete') }}</button></div></div><div class="mt-2 flex flex-wrap gap-2 text-xs"><span :class="lesson.is_published ? 'bg-emerald-100 text-emerald-900' : 'bg-slate-100 text-slate-700'" class="rounded-full px-2 py-1">{{ t(lesson.is_published ? 'curriculum.publishedLesson' : 'curriculum.draftLesson') }}</span><span :class="lesson.is_preview ? 'bg-amber-100 text-amber-900' : 'bg-slate-100 text-slate-700'" class="rounded-full px-2 py-1">{{ t(lesson.is_preview ? 'curriculum.publicPreview' : 'curriculum.enrolledOnly') }}</span></div><AdminLessonResources v-if="resourceLessonId === lesson.id" :lesson="lesson" :can-create="canCreateCurriculum" :can-update="canUpdateCurriculum" :can-delete="canDeleteCurriculum" class="mt-4" /></li></ol>
                    <AdminLessonEditor v-if="lessonEditor?.sectionId === section.id" :lesson="lessonEditor.lesson" :busy="busy" :error="editError" @save="saveLesson" @cancel="lessonEditor = null" />
                </div>
            </li></ol>
        </template>
    </div>
</template>

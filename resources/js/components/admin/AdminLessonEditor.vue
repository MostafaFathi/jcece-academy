<script setup>
import { reactive, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseAlert from '../ui/BaseAlert.vue';
import BaseButton from '../ui/BaseButton.vue';
import LessonVideoUploader from './LessonVideoUploader.vue';

const props = defineProps({ lesson: { type: Object, default: null }, busy: Boolean, error: { type: Object, default: null } });
const emit = defineEmits(['save', 'cancel']);
const { t } = useI18n();
const types = ['text', 'video', 'file', 'link'];
const form = reactive({ title: '', slug: '', type: 'text', description: '', content: '', video_url: '', duration_seconds: '', is_preview: false, is_published: false });
watch(() => props.lesson, (lesson) => {
    Object.assign(form, {
        title: lesson?.title ?? '', slug: lesson?.slug ?? '', type: lesson?.type ?? 'text', description: lesson?.description ?? '',
        content: lesson?.content ?? '', video_url: lesson?.video_url ?? '',
        duration_seconds: lesson?.duration_seconds ?? '', is_preview: lesson?.is_preview ?? false, is_published: lesson?.is_published ?? false,
    });
}, { immediate: true });
function submit() {
    if (props.busy) return;
    const payload = {
        title: form.title.trim(), slug: form.slug.trim(), type: form.type, description: form.description || null,
        content: form.type === 'text' ? form.content : null,
        duration_seconds: form.duration_seconds === '' ? null : Number(form.duration_seconds),
        is_preview: form.is_preview, is_published: form.is_published,
    };
    if (form.type === 'link' || (form.type === 'video' && form.is_preview)) payload.video_url = form.video_url || null;
    if (form.type === 'video' && form.is_preview) payload.video_provider = null;
    emit('save', payload);
}
</script>

<template>
    <form class="space-y-5 rounded-2xl border border-brand/20 bg-brand/5 p-5" @submit.prevent="submit">
        <h3 class="text-lg font-black text-slate-950">{{ t(lesson ? 'curriculum.editLesson' : 'curriculum.addLesson') }}</h3>
        <BaseAlert v-if="error" tone="danger">{{ t(error.status === 422 ? 'admin.validation' : 'admin.saveError') }}</BaseAlert>
        <div class="grid gap-4 sm:grid-cols-2"><div><label class="mb-1 block text-sm font-bold" for="lesson-title">{{ t('admin.title') }}</label><input id="lesson-title" v-model="form.title" required maxlength="255" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3"><p v-if="error?.errors?.title" role="alert" class="text-sm text-red-700">{{ error.errors.title[0] }}</p></div><div><label class="mb-1 block text-sm font-bold" for="lesson-slug">{{ t('admin.slug') }}</label><input id="lesson-slug" v-model="form.slug" required maxlength="255" pattern="[A-Za-z0-9_-]+" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3"><p v-if="error?.errors?.slug" role="alert" class="text-sm text-red-700">{{ error.errors.slug[0] }}</p></div><div><label class="mb-1 block text-sm font-bold" for="lesson-type">{{ t('curriculum.type') }}</label><select id="lesson-type" v-model="form.type" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3"><option v-for="type in types" :key="type" :value="type">{{ t(`curriculum.types.${type}`) }}</option></select></div><div><label class="mb-1 block text-sm font-bold" for="lesson-duration">{{ t('curriculum.durationSeconds') }}</label><input id="lesson-duration" v-model="form.duration_seconds" type="number" min="0" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3"><p v-if="error?.errors?.duration_seconds" role="alert" class="text-sm text-red-700">{{ error.errors.duration_seconds[0] }}</p></div></div>
        <div><label class="mb-1 block text-sm font-bold" for="lesson-description">{{ t('admin.description') }}</label><textarea id="lesson-description" v-model="form.description" rows="2" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3" /></div>
        <div v-if="form.type === 'text'"><label class="mb-1 block text-sm font-bold" for="lesson-content">{{ t('curriculum.textContent') }}</label><textarea id="lesson-content" v-model="form.content" required rows="7" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3" /><p class="text-xs text-slate-600">{{ t('curriculum.plainText') }}</p><p v-if="error?.errors?.content" role="alert" class="text-sm text-red-700">{{ error.errors.content[0] }}</p></div>
        <div v-if="form.type === 'video' && !form.is_preview" class="space-y-3"><LessonVideoUploader v-if="lesson" :key="lesson.id" :lesson="lesson" /><BaseAlert v-else>{{ t('curriculum.videoSaveFirst') }}</BaseAlert></div>
        <div v-if="form.type === 'link' || (form.type === 'video' && form.is_preview)"><label class="mb-1 block text-sm font-bold" for="lesson-url">{{ t(form.type === 'video' ? 'curriculum.previewVideoUrl' : 'curriculum.linkUrl') }}</label><input id="lesson-url" v-model="form.video_url" type="url" maxlength="2048" required dir="ltr" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3"><p v-if="error?.errors?.video_url" role="alert" class="text-sm text-red-700">{{ error.errors.video_url[0] }}</p></div>
        <BaseAlert v-if="form.type === 'file'">{{ t('curriculum.fileProvisioning') }}</BaseAlert>
        <div class="flex flex-wrap gap-5"><label class="flex items-center gap-2 text-sm font-bold"><input v-model="form.is_published" type="checkbox" class="size-5 accent-brand">{{ t('curriculum.publishedLesson') }}</label><label class="flex items-center gap-2 text-sm font-bold"><input v-model="form.is_preview" type="checkbox" class="size-5 accent-brand">{{ t('curriculum.publicPreview') }}</label></div><p v-if="error?.errors?.is_published" role="alert" class="text-sm text-red-700">{{ t('curriculum.videoPublishBeforeReady') }}</p><p class="text-xs text-slate-600">{{ t('curriculum.previewExplanation') }}</p>
        <div class="flex flex-wrap gap-3"><BaseButton type="submit" :loading="busy">{{ t(lesson ? 'admin.save' : 'admin.create') }}</BaseButton><BaseButton variant="secondary" @click="emit('cancel')">{{ t('admin.cancel') }}</BaseButton></div>
    </form>
</template>

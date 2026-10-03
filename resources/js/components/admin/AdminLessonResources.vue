<script setup>
import { reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { createLessonResource, deleteLessonResource, downloadLessonResource, fetchLessonResources, reorderLessonResources, updateLessonResource, uploadLessonResource } from '../../api/admin-curriculum';
import BaseAlert from '../ui/BaseAlert.vue';
import BaseButton from '../ui/BaseButton.vue';
import LoadingState from '../ui/LoadingState.vue';

const props = defineProps({ lesson: { type: Object, required: true }, canCreate: Boolean, canUpdate: Boolean, canDelete: Boolean });
const { t } = useI18n();
const resources = ref([]);
const loading = ref(true);
const busy = ref(false);
const error = ref(null);
const editingId = ref(null);
const form = reactive({ title: '', external_url: '', is_downloadable: false });
const file = ref(null);
const createKind = ref('link');
let sequence = 0;
async function load() {
    const current = ++sequence;
    loading.value = true;
    error.value = null;
    try { const result = await fetchLessonResources(props.lesson.id); if (current === sequence) resources.value = result; }
    catch (failure) { if (current === sequence) error.value = failure; }
    finally { if (current === sequence) loading.value = false; }
}
watch(() => props.lesson.id, () => { editingId.value = null; error.value = null; load(); }, { immediate: true });
function edit(resource = null, kind = 'link') {
    editingId.value = resource?.id ?? 'new';
    createKind.value = kind;
    file.value = null;
    Object.assign(form, { title: resource?.title ?? '', external_url: resource?.external_url ?? '', is_downloadable: resource?.is_downloadable ?? false });
    error.value = null;
}
async function save() {
    if (busy.value) return;
    busy.value = true; error.value = null;
    try {
        if (editingId.value === 'new' && createKind.value === 'file') {
            const payload = new FormData();
            payload.append('title', form.title.trim());
            payload.append('is_downloadable', form.is_downloadable ? '1' : '0');
            if (file.value) payload.append('file', file.value);
            await uploadLessonResource(props.lesson.id, payload);
        } else if (editingId.value === 'new') await createLessonResource(props.lesson.id, { title: form.title.trim(), type: 'link', external_url: form.external_url.trim(), is_downloadable: form.is_downloadable });
        else {
            const current = resources.value.find((item) => item.id === editingId.value);
            await updateLessonResource(props.lesson.id, editingId.value, {
                title: form.title.trim(), is_downloadable: form.is_downloadable,
                ...((current?.external_url || current?.type === 'link') ? { external_url: form.external_url.trim() } : {}),
            });
        }
        editingId.value = null;
        await load();
    } catch (failure) { error.value = failure; }
    finally { busy.value = false; }
}
function selectFile(event) { file.value = event.target.files?.[0] ?? null; }
async function download(resource) {
    if (busy.value) return;
    busy.value = true; error.value = null;
    try { await downloadLessonResource(props.lesson.id, resource.id); }
    catch (failure) { error.value = failure; }
    finally { busy.value = false; }
}
async function remove(resource) {
    if (busy.value || !window.confirm(t('curriculum.confirmDeleteResource'))) return;
    busy.value = true; error.value = null;
    try { await deleteLessonResource(props.lesson.id, resource.id); await load(); }
    catch (failure) { error.value = failure; await load(); }
    finally { busy.value = false; }
}
async function move(index, direction) {
    if (busy.value) return;
    const target = index + direction;
    if (target < 0 || target >= resources.value.length) return;
    const ids = resources.value.map((item) => item.id);
    [ids[index], ids[target]] = [ids[target], ids[index]];
    busy.value = true; error.value = null;
    try { await reorderLessonResources(props.lesson.id, ids); await load(); }
    catch (failure) { error.value = failure; await load(); }
    finally { busy.value = false; }
}
</script>

<template>
    <div class="space-y-4 rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3"><h4 class="font-black">{{ t('curriculum.resources') }}</h4><div v-if="canCreate && editingId === null" class="flex gap-2"><BaseButton variant="secondary" @click="edit()">{{ t('curriculum.addLink') }}</BaseButton><BaseButton variant="secondary" @click="edit(null, 'file')">{{ t('curriculum.addFile') }}</BaseButton></div></div>
        <LoadingState v-if="loading" /><BaseAlert v-else-if="error && editingId === null" tone="danger">{{ t('admin.loadError') }} <button type="button" class="font-bold underline" @click="load">{{ t('common.retry') }}</button></BaseAlert>
        <p v-if="!loading && !resources.length" class="text-sm text-slate-600">{{ t('curriculum.noResources') }}</p>
        <ol v-if="!loading" class="space-y-2"><li v-for="(resource, index) in resources" :key="resource.id" class="rounded-xl border border-slate-200 bg-white p-3"><div class="flex flex-wrap items-center justify-between gap-3"><div><p class="font-bold">{{ resource.title }}</p><p class="text-xs text-slate-600">{{ resource.type }} · {{ resource.file_reference_available ? t('curriculum.downloadReady') : resource.external_url ? t('curriculum.externalLink') : t('curriculum.noFileAvailable') }}</p></div><div class="flex flex-wrap gap-1"><button v-if="resource.file_reference_available" type="button" :disabled="busy" class="min-h-11 px-2 text-sm font-bold text-brand" @click="download(resource)">{{ t('curriculum.downloadFile') }}</button><button v-if="canUpdate" type="button" :disabled="busy || index === 0" class="min-h-11 rounded-lg border px-3 disabled:opacity-40" :aria-label="t('curriculum.moveEarlier')" @click="move(index, -1)">↑</button><button v-if="canUpdate" type="button" :disabled="busy || index === resources.length - 1" class="min-h-11 rounded-lg border px-3 disabled:opacity-40" :aria-label="t('curriculum.moveLater')" @click="move(index, 1)">↓</button><button v-if="canUpdate" type="button" class="min-h-11 px-2 text-sm font-bold text-brand" @click="edit(resource)">{{ t('admin.edit') }}</button><button v-if="canDelete" type="button" class="min-h-11 px-2 text-sm font-bold text-red-700" :disabled="busy" @click="remove(resource)">{{ t('admin.delete') }}</button></div></div></li></ol>
        <form v-if="editingId !== null" class="space-y-3 rounded-xl border border-brand/20 bg-white p-4" @submit.prevent="save"><div><label for="resource-title" class="mb-1 block text-sm font-bold">{{ t('admin.title') }}</label><input id="resource-title" v-model="form.title" required maxlength="255" class="w-full rounded-xl border border-slate-300 px-4 py-3"><p v-if="error?.errors?.title" role="alert" class="text-sm text-red-700">{{ error.errors.title[0] }}</p></div><div v-if="editingId === 'new' && createKind === 'file'"><label for="resource-file" class="mb-1 block text-sm font-bold">{{ t('curriculum.selectFile') }}</label><input id="resource-file" type="file" required class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3" @change="selectFile"><p v-if="file" class="mt-1 text-sm text-slate-600">{{ file.name }}</p><p v-if="error?.errors?.file" role="alert" class="text-sm text-red-700">{{ error.errors.file[0] }}</p></div><div v-if="(editingId === 'new' && createKind === 'link') || resources.find((item) => item.id === editingId)?.external_url || resources.find((item) => item.id === editingId)?.type === 'link'"><label for="resource-url" class="mb-1 block text-sm font-bold">{{ t('curriculum.linkUrl') }}</label><input id="resource-url" v-model="form.external_url" type="url" required maxlength="2048" dir="ltr" class="w-full rounded-xl border border-slate-300 px-4 py-3"><p v-if="error?.errors?.external_url" role="alert" class="text-sm text-red-700">{{ error.errors.external_url[0] }}</p></div><label class="flex items-center gap-2 text-sm"><input v-model="form.is_downloadable" type="checkbox" class="size-5 accent-brand">{{ t('curriculum.downloadable') }}</label><BaseAlert v-if="error" tone="danger">{{ t(error.status === 422 ? 'admin.validation' : 'admin.saveError') }}</BaseAlert><div class="flex gap-2"><BaseButton type="submit" :loading="busy">{{ t('admin.save') }}</BaseButton><BaseButton variant="secondary" @click="editingId = null">{{ t('admin.cancel') }}</BaseButton></div></form>
    </div>
</template>

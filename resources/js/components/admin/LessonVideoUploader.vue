<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { createVideoUpload, getVideoUpload, getVideoUploadOverview, previewVideo, removeVideoUpload, uploadBunnyVideo } from '../../api/bunny-video';
import { safeLearningUrl } from '../../utils/learning';
import BaseAlert from '../ui/BaseAlert.vue';
import BaseButton from '../ui/BaseButton.vue';

const props = defineProps({ lesson: { type: Object, required: true } });
const emit = defineEmits(['changed']);
const { t } = useI18n();
const file = ref(null);
const upload = ref(null);
const progress = ref(0);
const busy = ref(false);
const error = ref('');
const previewUrl = ref(null);
const providerConfigured = ref(false);
const maxUploadMegabytes = ref(0);
const cleanupPending = ref([]);
let abortController = null;
let pollTimer = null;

const mayUpload = computed(() => providerConfigured.value && !busy.value && !cleanupPending.value.length && !['creating', 'processing', 'uncertain', 'failed', 'retired', 'cleanup_failed'].includes(upload.value?.status));
const previewSource = computed(() => {
    const safe = safeLearningUrl(previewUrl.value);
    if (!safe) return null;
    const parsed = new URL(safe);
    return parsed.hostname === 'player.mediadelivery.net' && parsed.pathname.startsWith('/embed/') ? safe : null;
});

function storageKey(selected) {
    return `jcec.video-upload.${props.lesson.id}.${selected.name}.${selected.size}.${selected.lastModified}`;
}

function selectedFile(event) {
    file.value = event.target.files?.[0] ?? null;
    error.value = '';
}

function stopPolling() {
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = null;
}

async function refresh() {
    try {
        upload.value = await getVideoUpload(props.lesson.id, upload.value?.id ?? null);
        if (upload.value?.status === 'ready') {
            stopPolling();
            await loadOverview();
            emit('changed');
        } else if (['failed', 'deleted', 'cleanup_failed', 'uncertain'].includes(upload.value?.status)) stopPolling();
    } catch {
        error.value = t('curriculum.videoStatusError');
        stopPolling();
    }
}

function poll() {
    stopPolling();
    pollTimer = setInterval(refresh, 5000);
}

async function start() {
    if (!file.value || !mayUpload.value) return;
    const selected = file.value;
    const allowed = { mp4: 'video/mp4', mov: 'video/quicktime', webm: 'video/webm' };
    const extension = selected.name.split('.').pop()?.toLowerCase();
    if (allowed[extension] !== selected.type || !selected.size || selected.size > maxUploadMegabytes.value * 1048576) {
        error.value = t('curriculum.videoInvalidFile');
        return;
    }

    busy.value = true;
    error.value = '';
    abortController = new AbortController();
    const key = storageKey(selected);
    let saved;
    try { saved = JSON.parse(sessionStorage.getItem(key) ?? 'null'); } catch { saved = null; }
    if (!saved?.requestId) saved = { requestId: crypto.randomUUID(), url: null };

    try {
        const created = await createVideoUpload(props.lesson.id, {
            request_id: saved.requestId,
            filename: selected.name,
            mime_type: selected.type,
            size_bytes: selected.size,
        });
        upload.value = { id: created.id, status: created.status, filename: selected.name, encode_progress: 0 };
        sessionStorage.setItem(key, JSON.stringify(saved));
        await uploadBunnyVideo(selected, created.upload, {
            resumeUrl: saved.url,
            signal: abortController.signal,
            onLocation: (url) => { saved.url = url; sessionStorage.setItem(key, JSON.stringify(saved)); },
            onProgress: (value) => { progress.value = value; },
        });
        sessionStorage.removeItem(key);
        upload.value.status = 'processing';
        poll();
        await refresh();
    } catch (failure) {
        if (failure?.name !== 'AbortError') error.value = failure?.errors?.filename?.[0] ?? failure?.message ?? t('curriculum.videoUploadError');
    } finally {
        busy.value = false;
        abortController = null;
    }
}

async function remove() {
    if (!upload.value || busy.value || !window.confirm(t('curriculum.videoRemoveConfirm'))) return;
    busy.value = true;
    error.value = '';
    stopPolling();
    try {
        upload.value = await removeVideoUpload(props.lesson.id, upload.value.id);
        previewUrl.value = null;
        emit('changed');
        if (upload.value.status === 'cleanup_failed') error.value = t('curriculum.videoCleanupFailed');
        else await loadOverview();
    } catch { error.value = t('curriculum.videoRemoveError'); }
    finally { busy.value = false; }
}

async function retryCleanup(id) {
    if (busy.value) return;
    busy.value = true;
    try {
        const result = await removeVideoUpload(props.lesson.id, id);
        if (result.status === 'cleanup_failed') error.value = t('curriculum.videoCleanupFailed');
        await loadOverview();
    } catch { error.value = t('curriculum.videoRemoveError'); }
    finally { busy.value = false; }
}

async function loadOverview() {
    const overview = await getVideoUploadOverview(props.lesson.id);
    upload.value = overview.upload;
    providerConfigured.value = overview.settings.configured;
    maxUploadMegabytes.value = overview.settings.max_upload_megabytes;
    cleanupPending.value = overview.settings.cleanup_pending ?? [];
}

async function preview() {
    error.value = '';
    try { previewUrl.value = (await previewVideo(props.lesson.id)).url; }
    catch { error.value = t('curriculum.videoPreviewError'); }
}

onMounted(async () => {
    try {
        await loadOverview();
        if (upload.value && ['uploading', 'processing'].includes(upload.value.status)) poll();
    } catch { error.value = t('curriculum.videoStatusError'); }
});
watch(() => props.lesson.id, () => { stopPolling(); upload.value = null; previewUrl.value = null; });
onBeforeUnmount(() => { stopPolling(); abortController?.abort(); previewUrl.value = null; });
</script>

<template>
    <section class="space-y-4 rounded-2xl border border-slate-200 bg-white p-4 sm:p-5" :aria-label="t('curriculum.videoUploadHeading')">
        <div class="space-y-1"><h4 class="font-black text-slate-950">{{ t('curriculum.videoUploadHeading') }}</h4><p class="text-sm text-slate-600">{{ t('curriculum.videoUploadHint') }}</p></div>
        <BaseAlert v-if="error" tone="danger" role="alert">{{ error }}</BaseAlert>
        <BaseAlert v-if="!providerConfigured" tone="warning">{{ t('curriculum.videoNotConfigured') }}</BaseAlert>
        <div v-for="pending in cleanupPending" :key="pending.id" class="flex flex-wrap items-center gap-3 rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950"><span>{{ t('curriculum.videoCleanupFailed') }} · {{ pending.filename }}</span><BaseButton type="button" variant="secondary" :disabled="busy" @click="retryCleanup(pending.id)">{{ t('curriculum.videoRetryCleanup') }}</BaseButton></div>
        <p v-if="upload" class="text-sm font-bold text-slate-800" role="status">{{ t(`curriculum.videoStates.${upload.status}`) }}<span v-if="upload.status === 'processing'"> · {{ upload.encode_progress ?? 0 }}%</span><span v-if="upload.filename"> · {{ upload.filename }}</span></p>
        <div v-if="upload?.status === 'uploading' && !busy" class="text-sm text-slate-600">{{ t('curriculum.videoResumeHint') }}</div>
        <div v-if="busy" class="space-y-2"><progress :value="progress" max="100" class="h-3 w-full accent-brand" /><p class="text-sm text-slate-600">{{ progress }}%</p></div>
        <div class="flex flex-wrap items-center gap-3">
            <label for="lesson-video-file" class="text-sm font-bold text-slate-900">{{ t('curriculum.videoChooseFile') }}</label>
            <input id="lesson-video-file" type="file" accept=".mp4,.mov,.webm,video/mp4,video/quicktime,video/webm" class="min-w-0 max-w-full text-sm file:me-3 file:rounded-xl file:border-0 file:bg-brand/10 file:px-3 file:py-2 file:font-bold file:text-brand" :disabled="busy" @change="selectedFile">
            <BaseButton type="button" :disabled="!file || !mayUpload" :loading="busy" @click="start">{{ t(upload?.status === 'uploading' ? 'curriculum.videoResume' : 'curriculum.videoUpload') }}</BaseButton>
            <BaseButton v-if="busy" type="button" variant="secondary" @click="abortController?.abort()">{{ t('admin.cancel') }}</BaseButton>
            <BaseButton v-if="upload?.status === 'ready'" type="button" variant="secondary" @click="preview">{{ t('curriculum.videoPreview') }}</BaseButton>
            <BaseButton v-if="upload && upload.status !== 'deleted'" type="button" variant="secondary" :disabled="busy" @click="remove">{{ t('curriculum.videoRemove') }}</BaseButton>
        </div>
        <iframe v-if="previewSource" :src="previewSource" :title="t('curriculum.videoPreview')" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen class="aspect-video w-full rounded-2xl bg-slate-950" />
    </section>
</template>

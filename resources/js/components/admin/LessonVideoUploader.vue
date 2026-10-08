<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { createVideoUpload, getVideoUpload, getVideoUploadOverview, previewVideo, removeVideoUpload, uploadBunnyVideo } from '../../api/bunny-video';
import { safeLearningUrl } from '../../utils/learning';
import { createRequestId } from '../../utils/request-id';
import { isSupportedVideoFile } from '../../utils/video-file';
import BaseAlert from '../ui/BaseAlert.vue';
import BaseButton from '../ui/BaseButton.vue';

const props = defineProps({ lesson: { type: Object, required: true }, initialFile: { type: File, default: null } });
const emit = defineEmits(['changed', 'status-change']);
const { t } = useI18n();
const file = ref(props.initialFile);
const upload = ref(null);
const progress = ref(0);
const uploadStage = ref(null);
const busy = ref(false);
const error = ref('');
const previewUrl = ref(null);
const providerConfigured = ref(false);
const overviewLoaded = ref(false);
const maxUploadMegabytes = ref(0);
const cleanupPending = ref([]);
let abortController = null;
let pollTimer = null;

const mayUpload = computed(() => providerConfigured.value && !busy.value && !cleanupPending.value.length && (!upload.value || upload.value.status === 'uploading' || upload.value.status === 'deleted'));
const showFileChooser = computed(() => overviewLoaded.value && !busy.value && (!upload.value || upload.value.status === 'uploading' || upload.value.status === 'deleted'));
const encodeProgress = computed(() => Math.max(0, Math.min(100, Number(upload.value?.encode_progress) || 0)));
const statusTone = computed(() => upload.value?.status === 'ready' ? 'border-emerald-200 bg-emerald-50 text-emerald-950' : upload.value?.status === 'processing' ? 'border-amber-200 bg-amber-50 text-amber-950' : ['failed', 'cleanup_failed', 'uncertain'].includes(upload.value?.status) ? 'border-red-200 bg-red-50 text-red-950' : 'border-slate-200 bg-slate-50 text-slate-900');
const statusTitle = computed(() => upload.value?.status === 'processing' ? t('curriculum.videoProcessingTitle') : upload.value?.status === 'ready' ? t('curriculum.videoReadyTitle') : t(`curriculum.videoStates.${upload.value?.status}`));
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
    if (!isSupportedVideoFile(selected) || selected.size > maxUploadMegabytes.value * 1048576) {
        error.value = t('curriculum.videoInvalidFile');
        return;
    }

    busy.value = true;
    progress.value = 0;
    uploadStage.value = 'preparing';
    error.value = '';
    abortController = new AbortController();

    try {
        const key = storageKey(selected);
        let saved;
        try { saved = JSON.parse(sessionStorage.getItem(key) ?? 'null'); } catch { saved = null; }
        if (!saved?.requestId) saved = { requestId: createRequestId(), url: null };
        try { sessionStorage.setItem(key, JSON.stringify(saved)); } catch { /* Upload can proceed without browser storage. */ }
        const created = await createVideoUpload(props.lesson.id, {
            request_id: saved.requestId,
            filename: selected.name,
            mime_type: selected.type,
            size_bytes: selected.size,
        });
        upload.value = { id: created.id, status: created.status, filename: selected.name, encode_progress: 0 };
        uploadStage.value = 'connecting';
        await uploadBunnyVideo(selected, created.upload, {
            resumeUrl: saved.url,
            signal: abortController.signal,
            onLocation: (url) => { saved.url = url; try { sessionStorage.setItem(key, JSON.stringify(saved)); } catch { /* Resume remains available in this attempt. */ } },
            onProgress: (value) => { uploadStage.value = 'transferring'; progress.value = value; },
        });
        try { sessionStorage.removeItem(key); } catch { /* The upload has already completed. */ }
        upload.value.status = 'processing';
        uploadStage.value = 'processing';
        poll();
        await refresh();
    } catch (failure) {
        if (failure?.name !== 'AbortError') {
            const timedOut = failure?.name === 'TimeoutError' || failure?.originalError?.code === 'ECONNABORTED';
            error.value = timedOut ? t('curriculum.videoRequestTimeout') : (failure?.errors?.filename?.[0] ?? failure?.message ?? t('curriculum.videoUploadError'));
        }
        try { await loadOverview(); } catch { /* Keep the original upload error visible. */ }
    } finally {
        busy.value = false;
        uploadStage.value = null;
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
    overviewLoaded.value = true;
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
        else if (props.initialFile && !upload.value) await start();
    } catch { error.value = t('curriculum.videoStatusError'); }
});
watch(() => upload.value?.status, (status) => emit('status-change', status ?? null));
watch(() => props.lesson.id, () => { stopPolling(); upload.value = null; previewUrl.value = null; overviewLoaded.value = false; });
onBeforeUnmount(() => { stopPolling(); abortController?.abort(); previewUrl.value = null; });
</script>

<template>
    <section class="space-y-4 rounded-2xl border border-slate-200 bg-white p-4 sm:p-5" :aria-label="t('curriculum.videoUploadHeading')">
        <div class="space-y-1"><h4 class="font-black text-slate-950">{{ t('curriculum.videoUploadHeading') }}</h4><p v-if="!upload" class="text-sm text-slate-600">{{ t('curriculum.videoUploadHint') }}</p></div>
        <BaseAlert v-if="error" tone="danger" role="alert">{{ error }}</BaseAlert>
        <BaseAlert v-if="overviewLoaded && !providerConfigured" tone="warning">{{ t('curriculum.videoNotConfigured') }}</BaseAlert>
        <div v-for="pending in cleanupPending" :key="pending.id" class="flex flex-wrap items-center gap-3 rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950"><span>{{ t('curriculum.videoCleanupFailed') }} · {{ pending.filename }}</span><BaseButton type="button" variant="secondary" :disabled="busy" @click="retryCleanup(pending.id)">{{ t('curriculum.videoRetryCleanup') }}</BaseButton></div>
        <div v-if="upload" class="space-y-3 rounded-2xl border p-4" :class="statusTone" role="status">
            <div class="flex flex-wrap items-start gap-3">
                <span v-if="upload.status === 'ready'" class="flex size-8 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-lg font-black text-white" aria-hidden="true">✓</span>
                <span v-else-if="upload.status === 'processing'" class="flex size-8 shrink-0 items-center justify-center rounded-full bg-amber-200 text-amber-900" aria-hidden="true"><span class="size-4 animate-spin rounded-full border-2 border-current border-t-transparent motion-reduce:animate-none" /></span>
                <div class="min-w-0 flex-1 space-y-1">
                    <p class="font-black">{{ statusTitle }}</p>
                    <p v-if="upload.filename" class="break-all text-sm"><span>{{ t('curriculum.videoSelectedFile') }}: </span><bdi dir="ltr" class="font-bold">{{ upload.filename }}</bdi></p>
                    <p class="text-sm leading-6">{{ t(upload.status === 'ready' && lesson.is_published ? 'curriculum.videoStatusHelp.readyPublished' : `curriculum.videoStatusHelp.${upload.status}`) }}</p>
                </div>
            </div>
            <div v-if="upload.status === 'processing'" class="space-y-1">
                <div class="text-sm font-bold"><span v-if="encodeProgress > 0">{{ t('curriculum.videoProcessingProgress') }}: <bdi dir="ltr">{{ encodeProgress }}%</bdi></span><span v-else>{{ t('curriculum.videoProcessingStarting') }}</span></div>
                <progress :value="encodeProgress > 0 ? encodeProgress : undefined" max="100" class="h-2 w-full accent-brand" :aria-label="t('curriculum.videoProcessingProgress')" />
            </div>
        </div>
        <div v-if="busy" class="space-y-2"><p class="text-sm font-bold text-slate-700" role="status">{{ t(`curriculum.videoUploadStages.${uploadStage}`) }}</p><progress v-if="uploadStage === 'transferring'" :value="progress" max="100" class="h-3 w-full accent-brand" /><p v-if="uploadStage === 'transferring'" class="text-sm text-slate-600"><bdi dir="ltr">{{ progress }}%</bdi></p></div>
        <div v-if="showFileChooser" class="flex flex-wrap items-center gap-3">
            <input id="lesson-video-file" type="file" accept=".mp4,.mov,.webm,video/mp4,video/quicktime,video/webm" class="peer sr-only" @change="selectedFile">
            <label for="lesson-video-file" class="inline-flex min-h-11 cursor-pointer items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-brand hover:border-brand peer-focus-visible:outline-3 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-brand">{{ t('curriculum.videoChooseFile') }}</label>
            <bdi v-if="file" dir="ltr" class="max-w-full break-all text-sm text-slate-700">{{ file.name }}</bdi>
            <BaseButton type="button" :disabled="!file || !mayUpload" @click="start">{{ t(upload?.status === 'uploading' ? 'curriculum.videoResume' : 'curriculum.videoUpload') }}</BaseButton>
        </div>
        <div v-if="busy || upload?.status === 'ready' || (upload && upload.status !== 'deleted' && upload.status !== 'creating' && upload.status !== 'uncertain')" class="flex flex-wrap gap-3">
            <BaseButton v-if="busy" type="button" variant="secondary" @click="abortController?.abort()">{{ t('admin.cancel') }}</BaseButton>
            <BaseButton v-if="upload?.status === 'ready'" type="button" variant="secondary" :disabled="busy" @click="preview">{{ t('curriculum.videoPreview') }}</BaseButton>
            <BaseButton v-if="upload && !['deleted', 'creating', 'uncertain'].includes(upload.status)" type="button" variant="secondary" :disabled="busy" @click="remove">{{ t('curriculum.videoRemove') }}</BaseButton>
        </div>
        <iframe v-if="previewSource" :src="previewSource" :title="t('curriculum.videoPreview')" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen class="aspect-video w-full rounded-2xl bg-slate-950" />
    </section>
</template>

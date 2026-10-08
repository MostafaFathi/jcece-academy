<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { api } from '../../api/client';
import { safeLearningUrl } from '../../utils/learning';
import BaseButton from '../ui/BaseButton.vue';
const props = defineProps({ lesson: { type: Object, required: true }, courseSlug: { type: String, required: true }, busy: Boolean });
const emit = defineEmits(['save-position']);
const { t } = useI18n();
const video = ref(null);
const failed = ref(false);
const loading = ref(false);
const protectedSource = ref(null);
const protectedPlayer = ref('html5');
const source = computed(() => safeLearningUrl(props.lesson.protected_playback_available ? protectedSource.value : props.lesson.video_url));
const embedSource = computed(() => {
    if (protectedPlayer.value !== 'bunny_embed' || !source.value) return null;
    const url = new URL(source.value);
    return url.hostname === 'player.mediadelivery.net' && url.pathname.startsWith('/embed/') ? source.value : null;
});
let requestSequence = 0;
async function authorizePlayback() {
    const sequence = ++requestSequence;
    protectedSource.value = null;
    protectedPlayer.value = 'html5';
    failed.value = false;
    if (!props.lesson.protected_playback_available) { loading.value = false; return; }
    loading.value = true;
    try {
        const response = await api.post(`/api/v1/me/courses/${encodeURIComponent(props.courseSlug)}/lessons/${props.lesson.id}/protected-playback`);
        if (sequence === requestSequence) {
            protectedSource.value = response.data.data.url;
            protectedPlayer.value = response.data.data.player ?? 'html5';
        }
    } catch {
        if (sequence === requestSequence) failed.value = true;
    } finally {
        if (sequence === requestSequence) loading.value = false;
    }
}
watch(() => [props.lesson.id, props.lesson.protected_playback_available], authorizePlayback, { immediate: true });
onBeforeUnmount(() => { requestSequence++; protectedSource.value = null; });
function resume() {
    const position = props.lesson.progress?.last_position_seconds ?? 0;
    if (video.value && Number.isFinite(video.value.duration)) video.value.currentTime = Math.min(position, video.value.duration);
}
function pause() { video.value?.pause(); }
defineExpose({ pause });
</script>
<template>
    <div class="space-y-4" data-testid="video-lesson">
        <p v-if="loading" role="status" class="rounded-2xl bg-slate-100 p-6 text-sm leading-7 text-slate-600">{{ t('learning.videoAuthorizing') }}</p>
        <iframe v-if="embedSource" :src="embedSource" :title="lesson.title" allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin" class="aspect-video w-full rounded-2xl bg-slate-950" @error="failed = true" />
        <video v-else-if="source && protectedPlayer !== 'bunny_embed'" ref="video" :src="source" controls playsinline preload="metadata" class="aspect-video w-full rounded-2xl bg-slate-950" @loadedmetadata="resume" @error="failed = true" />
        <p v-else-if="!loading && !failed" class="rounded-2xl bg-slate-100 p-6 text-sm leading-7 text-slate-600">{{ t('learning.videoUnavailable') }}</p>
        <p v-if="failed" role="alert" class="text-sm text-red-700">{{ t('learning.videoFailed') }}</p>
        <BaseButton v-if="lesson.protected_playback_available && failed" variant="secondary" @click="authorizePlayback">{{ t('learning.videoRetry') }}</BaseButton>
        <BaseButton v-if="source && protectedPlayer !== 'bunny_embed'" variant="secondary" :disabled="busy || failed" @click="emit('save-position', video?.currentTime ?? 0)">{{ t('learning.savePosition') }}</BaseButton>
        <p v-if="lesson.content" class="whitespace-pre-wrap break-words text-sm leading-7 text-slate-600">{{ lesson.content }}</p>
    </div>
</template>

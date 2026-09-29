<script setup>
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { safeLearningUrl } from '../../utils/learning';
import BaseButton from '../ui/BaseButton.vue';
const props = defineProps({ lesson: { type: Object, required: true }, busy: Boolean });
const emit = defineEmits(['save-position']);
const { t } = useI18n();
const video = ref(null);
const failed = ref(false);
const source = computed(() => safeLearningUrl(props.lesson.video_url));
function resume() {
    const position = props.lesson.progress?.last_position_seconds ?? 0;
    if (video.value && Number.isFinite(video.value.duration)) video.value.currentTime = Math.min(position, video.value.duration);
}
function pause() { video.value?.pause(); }
defineExpose({ pause });
</script>
<template>
    <div class="space-y-4" data-testid="video-lesson">
        <video v-if="source" ref="video" :src="source" controls playsinline preload="metadata" class="aspect-video w-full rounded-2xl bg-slate-950" @loadedmetadata="resume" @error="failed = true" />
        <p v-else class="rounded-2xl bg-slate-100 p-6 text-sm leading-7 text-slate-600">{{ t('learning.videoUnavailable') }}</p>
        <p v-if="failed" role="alert" class="text-sm text-red-700">{{ t('learning.videoFailed') }}</p>
        <BaseButton v-if="source" variant="secondary" :disabled="busy || failed" @click="emit('save-position', video?.currentTime ?? 0)">{{ t('learning.savePosition') }}</BaseButton>
        <p v-if="lesson.content" class="whitespace-pre-wrap break-words text-sm leading-7 text-slate-600">{{ lesson.content }}</p>
    </div>
</template>

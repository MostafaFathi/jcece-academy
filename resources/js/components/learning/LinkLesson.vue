<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { safeLearningUrl } from '../../utils/learning';
const props = defineProps({ lesson: { type: Object, required: true } });
const { t } = useI18n();
const url = computed(() => safeLearningUrl(props.lesson.video_url));
</script>
<template><div class="space-y-4" data-testid="link-lesson"><p v-if="lesson.content" class="whitespace-pre-wrap break-words leading-8 text-slate-700">{{ lesson.content }}</p><a v-if="url" :href="url" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center rounded-xl bg-brand-soft px-5 py-3 text-sm font-bold text-brand">{{ t('learning.openLink') }} ↗</a><p v-else class="text-sm text-slate-600">{{ t('learning.unsafeLink') }}</p></div></template>

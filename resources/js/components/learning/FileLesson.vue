<script setup>
import { useI18n } from 'vue-i18n';
defineProps({ lesson: { type: Object, required: true } });
const { t } = useI18n();
</script>
<template><div class="space-y-4" data-testid="file-lesson"><p class="rounded-2xl border border-accent/40 bg-accent/10 p-5 leading-7 text-slate-700">{{ t('learning.fileDescription') }}</p><p v-if="lesson.content" class="whitespace-pre-wrap break-words leading-8 text-slate-700">{{ lesson.content }}</p></div></template>

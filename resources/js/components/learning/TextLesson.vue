<script setup>
import { useI18n } from 'vue-i18n';
defineProps({ lesson: { type: Object, required: true } });
const { t } = useI18n();
</script>
<template><div class="whitespace-pre-wrap break-words text-base leading-8 text-slate-700" data-testid="text-lesson">{{ lesson.content || t('learning.textEmpty') }}</div></template>

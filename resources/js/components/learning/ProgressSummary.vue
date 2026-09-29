<script setup>
import { useI18n } from 'vue-i18n';
defineProps({ summary: { type: Object, required: true } });
const { t } = useI18n();
</script>

<template>
    <div v-if="summary.progress_percentage != null" class="space-y-2" data-testid="course-progress">
        <div class="flex flex-wrap justify-between gap-2 text-sm"><span>{{ t('learning.counts', { completed: summary.completed_lessons, total: summary.total_lessons }) }}</span><bdi class="font-bold">{{ summary.progress_percentage }}%</bdi></div>
        <div role="progressbar" :aria-label="t('learning.progress')" :aria-valuenow="summary.progress_percentage" aria-valuemin="0" aria-valuemax="100" class="h-2 overflow-hidden rounded-full bg-slate-200"><div class="h-full rounded-full bg-accent transition-all" :style="{ width: `${summary.progress_percentage}%` }" /></div>
    </div>
</template>

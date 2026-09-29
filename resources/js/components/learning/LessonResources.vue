<script setup>
import { useI18n } from 'vue-i18n';
import { safeLearningUrl } from '../../utils/learning';
import BaseButton from '../ui/BaseButton.vue';
defineProps({ resources: { type: Array, default: () => [] }, busy: Boolean, downloadingId: { type: Number, default: null } });
defineEmits(['download']);
const { t } = useI18n();
</script>
<template>
    <section class="border-t border-slate-100 pt-6" :aria-label="t('learning.resources')">
        <h3 class="font-extrabold text-slate-900">{{ t('learning.resources') }}</h3>
        <p v-if="!resources.length" class="mt-3 text-sm text-slate-500">{{ t('learning.noResources') }}</p>
        <ul v-else class="mt-4 space-y-3"><li v-for="resource in resources" :key="resource.id" class="flex min-w-0 flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 p-4"><span class="min-w-0 break-words text-sm font-bold text-slate-700">{{ resource.title }}</span><BaseButton v-if="resource.download_available && resource.is_downloadable" variant="secondary" :loading="downloadingId === resource.id" :disabled="busy" @click="$emit('download', resource)">{{ t(downloadingId === resource.id ? 'learning.downloading' : 'learning.download') }}</BaseButton><a v-else-if="safeLearningUrl(resource.external_url)" :href="safeLearningUrl(resource.external_url)" target="_blank" rel="noopener noreferrer" class="text-sm font-bold text-brand underline underline-offset-4">{{ t('learning.openResource') }} ↗</a><span v-else class="text-xs text-slate-500">{{ t('learning.resourceUnavailable') }}</span></li></ul>
    </section>
</template>

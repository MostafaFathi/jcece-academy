<script setup>
import { useI18n } from 'vue-i18n';
const { t } = useI18n();
</script>

<template>
    <div role="status" class="flex min-h-48 flex-col items-center justify-center gap-3 text-slate-600"><span class="size-9 animate-spin rounded-full border-3 border-brand/20 border-t-brand" /><span>{{ t('common.loading') }}</span></div>
</template>

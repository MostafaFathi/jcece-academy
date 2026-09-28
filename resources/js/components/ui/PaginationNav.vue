<script setup>
import { useI18n } from 'vue-i18n';
defineProps({ currentPage: { type: Number, default: 1 }, lastPage: { type: Number, default: 1 } });
defineEmits(['change']);
const { locale, t } = useI18n();
</script>

<template>
    <nav :aria-label="t('common.page', { current: currentPage, total: lastPage })" class="flex items-center justify-between gap-3"><button type="button" class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-700 transition hover:border-brand hover:text-brand disabled:cursor-not-allowed disabled:opacity-40" :disabled="currentPage <= 1" @click="$emit('change', currentPage - 1)"><span aria-hidden="true">{{ locale === 'ar' ? '→' : '←' }}</span>{{ t('common.previous') }}</button><span class="text-sm font-semibold text-slate-600">{{ t('common.page', { current: currentPage, total: lastPage }) }}</span><button type="button" class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-bold text-slate-700 transition hover:border-brand hover:text-brand disabled:cursor-not-allowed disabled:opacity-40" :disabled="currentPage >= lastPage" @click="$emit('change', currentPage + 1)">{{ t('common.next') }}<span aria-hidden="true">{{ locale === 'ar' ? '←' : '→' }}</span></button></nav>
</template>

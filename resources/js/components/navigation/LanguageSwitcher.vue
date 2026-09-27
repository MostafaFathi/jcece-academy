<script setup>
import { useI18n } from 'vue-i18n';
import { setLocale } from '../../i18n';

const { locale, t } = useI18n();
function toggleLocale() { setLocale(locale.value === 'ar' ? 'en' : 'ar'); }
</script>

<template>
    <button type="button" class="min-h-10 rounded-lg px-3 text-sm font-bold transition hover:bg-slate-100 focus-visible:outline-3 focus-visible:outline-brand" @click="toggleLocale">{{ t('common.language') }}</button>
</template>

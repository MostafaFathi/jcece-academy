<script setup>
import { useI18n } from 'vue-i18n';
import { moneyText } from '../../utils/commerce';
defineProps({ amount: { type: [String, Number], default: null }, currency: { type: String, default: null } });
const { locale, t } = useI18n();
</script>
<template><span class="inline-flex flex-wrap items-baseline gap-1.5"><bdi>{{ moneyText(amount, locale) }}</bdi><span v-if="currency" class="text-[0.65em] font-bold">{{ currency }}</span><span v-else class="text-xs font-normal">{{ t('commerce.currencyMissing') }}</span></span></template>

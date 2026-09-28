<script setup>
import { useI18n } from 'vue-i18n';
import { useCartStore } from '../../stores/cart';
import MoneyAmount from './MoneyAmount.vue';
const cart = useCartStore();
const { t } = useI18n();
</script>
<template><aside class="min-w-0 rounded-3xl bg-brand-dark p-6 text-white shadow-xl sm:p-8"><p class="text-xs font-bold tracking-wider text-accent">{{ t('commerce.summary') }}</p><h2 class="mt-3 text-2xl font-black">{{ t('commerce.itemCount', { count: cart.count }) }}</h2><div class="my-6 flex flex-wrap justify-between gap-3 border-y border-white/15 py-6"><span>{{ t('commerce.estimatedTotal') }}</span><MoneyAmount :amount="cart.total" :currency="cart.currency" class="text-2xl font-black" /></div><p class="text-sm leading-7 text-white/70">{{ t('commerce.serverTotals') }}</p><p class="mt-3 text-sm leading-7 text-white/70">{{ t('commerce.noAccessYet') }}</p><div class="mt-6"><slot /></div></aside></template>

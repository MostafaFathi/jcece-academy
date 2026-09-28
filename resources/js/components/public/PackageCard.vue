<script setup>
import { useI18n } from 'vue-i18n';
import MoneyAmount from '../commerce/MoneyAmount.vue';
import AddToCartButton from '../commerce/AddToCartButton.vue';
import MediaFrame from './MediaFrame.vue';

defineProps({ packageItem: { type: Object, required: true } });
const { locale, t } = useI18n();
</script>

<template>
    <article class="group overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-[0_10px_35px_rgba(15,23,42,0.06)] transition duration-300 hover:-translate-y-1 hover:shadow-[0_22px_55px_rgba(107,29,50,0.13)]"><RouterLink :to="{ name: 'packages.show', params: { slug: packageItem.slug } }" class="block"><MediaFrame :src="packageItem.thumbnail" :alt="packageItem.title"><span class="absolute start-4 top-4 rounded-full bg-white/95 px-3 py-1.5 text-xs font-black text-brand shadow-sm">{{ t(`labels.packageTypes.${packageItem.type}`) }}</span></MediaFrame></RouterLink><div class="space-y-4 p-5 sm:p-6"><div><h3 class="text-xl font-black text-slate-950"><RouterLink :to="{ name: 'packages.show', params: { slug: packageItem.slug } }" class="hover:text-brand">{{ packageItem.title }}</RouterLink></h3><p v-if="packageItem.description" class="mt-2 line-clamp-2 text-sm leading-6 text-slate-600">{{ packageItem.description }}</p></div><div class="flex flex-wrap items-end justify-between gap-4 border-t border-slate-100 pt-4"><div><p class="text-xs font-semibold text-slate-500">{{ t('catalog.packagePrice') }}</p><strong class="text-xl font-black text-brand"><MoneyAmount :amount="packageItem.price" :currency="packageItem.currency" /></strong></div><span v-if="packageItem.course_count !== undefined" class="rounded-full bg-accent/25 px-3 py-1.5 text-xs font-black text-brand-dark">{{ t('catalog.courseCount', { count: packageItem.course_count }) }}</span></div></div><div class="px-5 pb-5 sm:px-6 sm:pb-6"><AddToCartButton type="package" :product="packageItem" /></div></article>
</template>

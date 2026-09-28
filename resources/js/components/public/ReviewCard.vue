<script setup>
import { useI18n } from 'vue-i18n';
import { formatDate } from '../../utils/catalog';
import RatingStars from './RatingStars.vue';

defineProps({ review: { type: Object, required: true } });
const { locale } = useI18n();
</script>

<template>
    <article class="rounded-2xl border border-slate-200 bg-white p-5"><div class="flex flex-wrap items-start justify-between gap-3"><div class="flex items-center gap-3"><span class="grid size-10 place-items-center rounded-full bg-brand text-sm font-black text-white">{{ review.reviewer_name?.charAt(0) }}</span><div><h3 class="font-black text-slate-900">{{ review.reviewer_name }}</h3><p class="text-xs text-slate-500">{{ formatDate(review.published_at, locale) }}</p></div></div><RatingStars :rating="review.rating" :count="0" compact /></div><h4 v-if="review.title" class="mt-4 font-black text-slate-800">{{ review.title }}</h4><p v-if="review.body" class="mt-2 whitespace-pre-line text-sm leading-7 text-slate-600">{{ review.body }}</p></article>
</template>

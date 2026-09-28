<script setup>
import { useI18n } from 'vue-i18n';
import MoneyAmount from '../commerce/MoneyAmount.vue';
import AddToCartButton from '../commerce/AddToCartButton.vue';
import MediaFrame from './MediaFrame.vue';
import RatingStars from './RatingStars.vue';

defineProps({ course: { type: Object, required: true } });
const { locale, t } = useI18n();
</script>

<template>
    <article class="group flex h-full flex-col overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-[0_10px_35px_rgba(15,23,42,0.06)] transition duration-300 hover:-translate-y-1 hover:border-brand/20 hover:shadow-[0_22px_55px_rgba(107,29,50,0.13)]">
        <RouterLink :to="{ name: 'courses.show', params: { slug: course.slug } }" class="relative block focus-visible:outline-3 focus-visible:outline-offset-[-3px] focus-visible:outline-accent"><MediaFrame :src="course.thumbnail" :alt="course.title"><span v-if="course.is_featured" class="absolute start-4 top-4 rounded-full bg-accent px-3 py-1.5 text-xs font-black text-brand-dark shadow-sm">{{ t('catalog.featured') }}</span></MediaFrame></RouterLink>
        <div class="flex flex-1 flex-col gap-4 p-5 sm:p-6"><div class="flex flex-wrap items-center gap-2 text-xs font-bold"><span v-if="course.category" class="rounded-full bg-brand-soft px-3 py-1 text-brand">{{ course.category.name }}</span><span class="text-slate-500">{{ t(`labels.levels.${course.level}`) }}</span></div><div><h3 class="line-clamp-2 text-xl font-black leading-7 text-slate-950"><RouterLink :to="{ name: 'courses.show', params: { slug: course.slug } }" class="transition hover:text-brand focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-accent">{{ course.title }}</RouterLink></h3><p v-if="course.short_description" class="mt-2 line-clamp-2 text-sm leading-6 text-slate-600">{{ course.short_description }}</p></div><div class="flex flex-wrap items-center justify-between gap-3"><RatingStars :rating="course.rating_summary?.average_rating" :count="course.rating_summary?.review_count ?? 0" compact /><span v-if="course.instructor" class="truncate text-xs font-semibold text-slate-500">{{ course.instructor.name }}</span></div><div class="mt-auto flex items-end justify-between gap-4 border-t border-slate-100 pt-4"><div><p class="text-xs font-semibold text-slate-500">{{ t('catalog.price') }}</p><div class="flex items-baseline gap-2"><strong class="text-xl font-black text-brand"><MoneyAmount :amount="course.price" :currency="course.currency" /></strong><del v-if="course.compare_price && Number(course.compare_price) > Number(course.price)" class="text-xs text-slate-400"><MoneyAmount :amount="course.compare_price" :currency="course.currency" /></del></div></div><span v-if="course.duration_minutes" class="text-xs font-bold text-slate-500">{{ t('catalog.minutes', { count: course.duration_minutes }) }}</span></div></div>
    <div class="px-5 pb-5 sm:px-6 sm:pb-6"><AddToCartButton type="course" :product="course" /></div></article>
</template>

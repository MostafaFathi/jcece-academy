<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({ sections: { type: Array, default: () => [] } });
const { t, te } = useI18n();
const lessonCount = computed(() => props.sections.reduce((total, section) => total + (section.lessons?.length ?? 0), 0));
const labelFor = (type) => te(`labels.lessonTypes.${type}`) ? t(`labels.lessonTypes.${type}`) : type;
</script>

<template>
    <div><div class="mb-5 flex flex-wrap items-center justify-between gap-3"><h2 class="text-2xl font-black text-slate-950">{{ t('course.curriculum') }}</h2><span class="rounded-full bg-brand-soft px-3 py-1.5 text-xs font-black text-brand">{{ t('course.lessons', { count: lessonCount }) }}</span></div><div class="space-y-3"><details v-for="(section, index) in sections" :key="section.id" class="group overflow-hidden rounded-2xl border border-slate-200 bg-white" :open="index === 0"><summary class="flex cursor-pointer list-none items-center justify-between gap-4 p-5 font-black text-slate-900 focus-visible:outline-3 focus-visible:outline-inset focus-visible:outline-brand"><span>{{ section.title }}</span><span class="grid size-8 place-items-center rounded-full bg-slate-100 text-lg transition group-open:rotate-45" aria-hidden="true">+</span></summary><div class="border-t border-slate-100"><p v-if="section.description" class="px-5 pt-4 text-sm leading-6 text-slate-600">{{ section.description }}</p><ol class="divide-y divide-slate-100"><li v-for="lesson in section.lessons ?? []" :key="lesson.id" class="flex items-start gap-3 px-5 py-4"><span class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-xl bg-brand-soft text-xs font-black text-brand">{{ lesson.sort_order + 1 }}</span><div class="min-w-0 flex-1"><div class="flex flex-wrap items-center gap-2"><h3 class="text-sm font-bold text-slate-800">{{ lesson.title }}</h3><span v-if="lesson.is_preview" class="rounded-full bg-accent/30 px-2 py-0.5 text-[10px] font-black text-brand-dark">{{ t('course.preview') }}</span></div><div class="mt-1 flex flex-wrap gap-3 text-xs text-slate-500"><span>{{ labelFor(lesson.type) }}</span><span v-if="lesson.duration_seconds">{{ t('catalog.minutes', { count: Math.ceil(lesson.duration_seconds / 60) }) }}</span></div><p v-if="lesson.description" class="mt-2 text-sm leading-6 text-slate-500">{{ lesson.description }}</p></div></li></ol></div></details></div></div>
</template>

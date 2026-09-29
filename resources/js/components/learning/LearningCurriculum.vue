<script setup>
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
defineProps({ sections: { type: Array, required: true }, activeId: { type: Number, default: null }, busy: Boolean });
const emit = defineEmits(['select']);
const { t } = useI18n();
const open = ref(false);
const toggle = ref(null);
function close() { open.value = false; toggle.value?.focus(); }
function select(id) { emit('select', id); open.value = false; }
</script>
<template>
    <aside class="min-w-0 self-start rounded-3xl border border-slate-200 bg-white shadow-sm lg:sticky lg:top-24" @keydown.esc="close">
        <button ref="toggle" type="button" :aria-expanded="open" aria-controls="learning-curriculum" class="flex min-h-12 w-full items-center justify-between gap-3 px-5 py-4 text-start font-extrabold text-slate-900 lg:hidden" @click="open = !open">{{ t(open ? 'learning.hideCurriculum' : 'learning.showCurriculum') }}<span aria-hidden="true">{{ open ? '−' : '+' }}</span></button>
        <h2 class="hidden border-b border-slate-100 p-5 font-extrabold text-slate-900 lg:block">{{ t('learning.curriculum') }}</h2>
        <div id="learning-curriculum" class="max-h-[65vh] overflow-y-auto overscroll-contain p-3 lg:block" :class="open ? 'block' : 'hidden'">
            <section v-for="section in sections" :key="section.id" class="mb-4"><h3 class="break-words px-3 py-3 text-sm font-bold text-slate-500">{{ section.title }}</h3><ol class="space-y-1"><li v-for="lesson in section.lessons" :key="lesson.id"><button type="button" :disabled="busy" :aria-current="lesson.id === activeId ? 'step' : undefined" class="flex min-h-14 w-full items-start gap-3 rounded-2xl p-3 text-start transition disabled:opacity-50" :class="lesson.id === activeId ? 'bg-brand-soft text-brand' : 'text-slate-700 hover:bg-slate-50'" @click="select(lesson.id)"><span class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full text-xs" :class="lesson.progress?.status === 'completed' ? 'bg-emerald-100 text-emerald-800' : 'border border-current/20'" aria-hidden="true">{{ lesson.progress?.status === 'completed' ? '✓' : '•' }}</span><span class="min-w-0"><span class="block break-words text-sm font-bold">{{ lesson.title }}</span><span class="mt-1 block text-xs opacity-75">{{ t(`learning.types.${lesson.type}`) }} · {{ t(`learning.lessonStatus.${lesson.progress?.status || 'not_started'}`) }}</span></span></button></li></ol></section>
        </div>
    </aside>
</template>

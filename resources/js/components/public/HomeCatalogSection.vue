<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseAlert from '../ui/BaseAlert.vue';
import EmptyState from '../ui/EmptyState.vue';
import CatalogSkeleton from './CatalogSkeleton.vue';

const props = defineProps({ title: { type: String, required: true }, destination: { type: Object, default: null }, loading: Boolean, error: Boolean, empty: Boolean, dense: Boolean, autoPlay: Boolean, itemCount: { type: Number, default: 0 }, emptyTitle: { type: String, default: '' } });
defineEmits(['retry']);
const { t, locale } = useI18n();
const rail = ref(null);
const paused = ref(false);
let autoPlayTimer = null;

function autoAdvance() {
    const element = rail.value;
    if (!element || paused.value || props.loading || props.error || props.empty || props.itemCount < 2 || document.visibilityState === 'hidden' || window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) return;
    const maxOffset = element.scrollWidth - element.clientWidth;
    if (maxOffset <= 2) return;
    const currentOffset = (locale.value === 'ar' ? -1 : 1) * element.scrollLeft;
    if (currentOffset >= maxOffset - 2) {
        element.scrollTo({ left: 0, behavior: 'auto' });
    } else {
        element.scrollBy({ left: (locale.value === 'ar' ? -1 : 1) * element.clientWidth, behavior: 'smooth' });
    }
}

function syncAutoPlay() {
    if (autoPlayTimer !== null) window.clearInterval(autoPlayTimer);
    autoPlayTimer = props.autoPlay ? window.setInterval(autoAdvance, 4000) : null;
}

onMounted(syncAutoPlay);
watch(() => props.autoPlay, syncAutoPlay);
onBeforeUnmount(() => { if (autoPlayTimer !== null) window.clearInterval(autoPlayTimer); });

function scroll(direction) {
    const element = rail.value;
    if (!element) return;
    const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
    element.scrollBy({ left: direction * (locale.value === 'ar' ? -1 : 1) * element.clientWidth, behavior: reducedMotion ? 'auto' : 'smooth' });
}
</script>

<template>
    <section class="border-t border-slate-100 bg-white py-10 sm:py-14" :aria-label="title">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <header class="mb-6 flex flex-wrap items-center justify-between gap-4">
                <h2 class="flex items-center gap-3 text-2xl font-black text-slate-950 sm:text-3xl"><span aria-hidden="true" class="size-4 shrink-0 rounded-full bg-primary" />{{ title }}</h2>
                <RouterLink v-if="destination" :to="destination" class="inline-flex min-h-11 items-center gap-2 rounded-lg px-3 text-sm font-bold text-brand hover:bg-brand-soft focus-visible:outline-3 focus-visible:outline-brand">{{ t('home.viewMore') }}<span aria-hidden="true">{{ locale === 'ar' ? '←' : '→' }}</span></RouterLink>
            </header>
            <BaseAlert v-if="error" tone="danger">{{ t('catalog.loadError') }} <button type="button" class="font-bold underline" @click="$emit('retry')">{{ t('common.retry') }}</button></BaseAlert>
            <div v-else-if="loading" class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4" role="status" :aria-label="t('common.loading')"><CatalogSkeleton v-for="index in 4" :key="index" /></div>
            <EmptyState v-else-if="empty" :title="emptyTitle" />
            <template v-else>
                <div ref="rail" class="grid snap-x snap-mandatory auto-cols-[100%] grid-flow-col gap-5 overflow-x-auto overscroll-x-contain pb-5 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden sm:auto-cols-[calc((100%-1.25rem)/2)] [&>*]:min-w-0 [&>*]:snap-start" :class="dense ? 'lg:auto-cols-[calc((100%-6.25rem)/6)]' : 'lg:auto-cols-[calc((100%-3.75rem)/4)]'" tabindex="0" role="region" :aria-label="title" aria-live="off" @mouseenter="paused = true" @mouseleave="paused = false" @focusin="paused = true" @focusout="paused = false" @pointerdown="paused = true" @pointerup="paused = false"><slot /></div>
                <div v-if="itemCount > 1" class="mt-2 flex justify-end gap-2">
                    <button type="button" class="grid size-11 place-items-center rounded-full border border-slate-200 bg-white text-brand-dark hover:bg-brand-soft focus-visible:outline-3 focus-visible:outline-brand" :aria-label="`${t('common.previous')}: ${title}`" @click="scroll(-1)"><span aria-hidden="true">{{ locale === 'ar' ? '→' : '←' }}</span></button>
                    <button type="button" class="grid size-11 place-items-center rounded-full border border-slate-200 bg-primary text-brand-dark hover:bg-primary-hover focus-visible:outline-3 focus-visible:outline-brand" :aria-label="`${t('common.next')}: ${title}`" @click="scroll(1)"><span aria-hidden="true">{{ locale === 'ar' ? '←' : '→' }}</span></button>
                </div>
            </template>
        </div>
    </section>
</template>

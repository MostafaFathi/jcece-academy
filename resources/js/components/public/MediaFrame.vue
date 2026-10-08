<script setup>
import { computed, ref, watch } from 'vue';
import { publicMediaUrl } from '../../utils/catalog';

const props = defineProps({ src: { type: String, default: null }, alt: { type: String, default: '' }, ratio: { type: String, default: 'video' } });
const failed = ref(false);
const source = computed(() => publicMediaUrl(props.src));
watch(() => props.src, () => { failed.value = false; });
</script>

<template>
    <div class="relative min-w-0 w-full overflow-hidden bg-gradient-to-br from-brand-dark via-brand to-[#8c2947]" :class="ratio === 'square' ? 'aspect-square' : 'aspect-video'">
        <img v-if="source && !failed" :src="source" :alt="alt" class="size-full object-cover transition duration-500 group-hover:scale-[1.03]" loading="lazy" @error="failed = true">
        <div v-else class="absolute inset-0 grid place-items-center" role="img" :aria-label="alt"><div class="absolute -start-10 -top-10 size-32 rounded-full border-[18px] border-white/5" /><div class="absolute -bottom-14 -end-12 size-44 rounded-full bg-accent/20 blur-sm" /><img :src="'/assets/images/logo-1.png'" alt="" class="relative size-28 rounded-2xl bg-white/95 object-contain p-2 shadow-xl"></div>
        <slot />
    </div>
</template>

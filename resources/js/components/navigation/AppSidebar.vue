<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuthStore } from '../../stores/auth';
import { navigationByArea, visibleNavigation } from '../../composables/navigation';
import BrandLogo from './BrandLogo.vue';

const props = defineProps({ area: { type: String, required: true } });
const auth = useAuthStore();
const { t } = useI18n();
const items = computed(() => visibleNavigation(navigationByArea[props.area] ?? [], auth));
</script>

<template>
    <aside class="relative flex h-full flex-col overflow-hidden bg-brand-dark p-4 text-white"><div class="absolute -end-16 -top-16 size-52 rounded-full bg-accent/[0.07]" /><div class="relative"><BrandLogo /></div><div class="relative my-5 h-px bg-white/10" /><nav class="relative flex flex-1 flex-col gap-1" :aria-label="t('nav.main')"><RouterLink v-for="item in items" :key="item.route" :to="{ name: item.route }" class="rounded-xl px-4 py-3 text-sm font-bold text-white/70 transition hover:bg-white/10 hover:text-white focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-accent" active-class="bg-accent !text-brand-dark shadow-lg shadow-black/10">{{ t(item.label) }}</RouterLink></nav><p class="relative mt-5 rounded-2xl border border-white/10 bg-white/[0.04] p-4 text-xs leading-6 text-white/50">{{ t('brand.tagline') }}</p></aside>
</template>

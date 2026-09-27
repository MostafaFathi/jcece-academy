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
    <aside class="flex h-full flex-col bg-brand-dark p-4 text-white"><BrandLogo /><div class="my-5 h-px bg-white/10" /><nav class="flex flex-1 flex-col gap-1" aria-label="Main navigation"><RouterLink v-for="item in items" :key="item.route" :to="{ name: item.route }" class="rounded-xl px-4 py-3 text-sm font-bold text-white/75 transition hover:bg-white/10 hover:text-white" active-class="bg-accent !text-brand-dark shadow-sm">{{ t(item.label) }}</RouterLink></nav><p class="mt-5 px-2 text-xs leading-5 text-white/55">{{ t('brand.tagline') }}</p></aside>
</template>

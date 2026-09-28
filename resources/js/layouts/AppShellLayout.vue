<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import AppSidebar from '../components/navigation/AppSidebar.vue';
import AppHeader from '../components/navigation/AppHeader.vue';
import AppBreadcrumbs from '../components/navigation/AppBreadcrumbs.vue';

defineProps({ area: { type: String, required: true } });
const drawerOpen = ref(false);
const route = useRoute();
const { t } = useI18n();

watch(() => route.fullPath, () => { drawerOpen.value = false; });
watch(drawerOpen, (open) => { document.body.style.overflow = open ? 'hidden' : ''; });
onBeforeUnmount(() => { document.body.style.overflow = ''; });
</script>

<template>
    <div class="min-h-screen bg-[#f6f7f9] text-slate-900" @keydown.esc="drawerOpen = false"><div class="fixed inset-y-0 start-0 z-30 hidden w-72 lg:block"><AppSidebar :area="area" /></div><div class="min-h-screen lg:ps-72"><AppHeader @open-menu="drawerOpen = true" /><main class="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8"><AppBreadcrumbs /><RouterView /></main></div><div v-if="drawerOpen" class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true" :aria-label="t('nav.main')"><button type="button" class="absolute inset-0 bg-slate-950/65 backdrop-blur-sm" :aria-label="t('common.menuClose')" @click="drawerOpen = false" /><div class="absolute inset-y-0 start-0 w-80 max-w-[86vw] shadow-2xl"><AppSidebar :area="area" /><button type="button" class="absolute end-3 top-3 grid size-10 place-items-center rounded-full bg-white/10 text-xl text-white hover:bg-white/20" :aria-label="t('common.menuClose')" @click="drawerOpen = false">×</button></div></div></div>
</template>

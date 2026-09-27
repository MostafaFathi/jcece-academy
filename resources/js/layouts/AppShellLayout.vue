<script setup>
import { ref } from 'vue';
import AppSidebar from '../components/navigation/AppSidebar.vue';
import AppHeader from '../components/navigation/AppHeader.vue';
import AppBreadcrumbs from '../components/navigation/AppBreadcrumbs.vue';

defineProps({ area: { type: String, required: true } });
const drawerOpen = ref(false);
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-900"><div class="fixed inset-y-0 start-0 hidden w-72 lg:block"><AppSidebar :area="area" /></div><div class="min-h-screen lg:ps-72"><AppHeader @open-menu="drawerOpen = true" /><main class="mx-auto max-w-7xl p-4 sm:p-6 lg:p-8"><AppBreadcrumbs /><RouterView /></main></div><div v-if="drawerOpen" class="fixed inset-0 z-40 lg:hidden"><button type="button" class="absolute inset-0 bg-slate-950/55" aria-label="Close" @click="drawerOpen = false" /><div class="absolute inset-y-0 start-0 w-80 max-w-[86vw] shadow-2xl"><AppSidebar :area="area" /><button type="button" class="absolute end-3 top-3 grid size-10 place-items-center rounded-full bg-white/10 text-xl text-white" @click="drawerOpen = false">×</button></div></div></div>
</template>

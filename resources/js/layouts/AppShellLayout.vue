<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import AppSidebar from '../components/navigation/AppSidebar.vue';
import AppHeader from '../components/navigation/AppHeader.vue';
import AppBreadcrumbs from '../components/navigation/AppBreadcrumbs.vue';
import MessageNotificationBadge from '../components/messaging/MessageNotificationBadge.vue';

defineProps({ area: { type: String, required: true } });
const drawerOpen = ref(false);
const route = useRoute();
const { t } = useI18n();

watch(() => route.fullPath, () => { drawerOpen.value = false; });
watch(drawerOpen, (open) => { document.body.style.overflow = open ? 'hidden' : ''; });
onBeforeUnmount(() => { document.body.style.overflow = ''; });
</script>

<template>
    <div class="app-shell relative min-h-screen overflow-x-clip bg-[#f7f6f6] text-slate-900" @keydown.esc="drawerOpen = false">
        <a href="#workspace-content" class="fixed start-4 top-3 z-[70] -translate-y-24 rounded-xl bg-accent px-4 py-2 text-sm font-bold text-brand-dark shadow-lg transition focus:translate-y-0">{{ t('common.skipContent') }}</a>
        <div class="pointer-events-none absolute inset-x-0 top-0 h-80 bg-[radial-gradient(ellipse_at_65%_0%,rgba(107,29,50,.06),transparent_68%)]" />
        <div class="fixed inset-y-0 start-0 z-30 hidden w-72 lg:block"><AppSidebar :area="area" /></div>
        <div class="relative min-h-screen lg:ps-72">
            <AppHeader :area="area" @open-menu="drawerOpen = true" />
            <main id="workspace-content" tabindex="-1" class="mx-auto w-full max-w-[1600px] px-4 pt-5 sm:px-6 sm:pt-7 lg:px-9 lg:pt-8" :class="['student', 'instructor'].includes(area) ? 'pb-28' : 'pb-14'">
                <AppBreadcrumbs />
                <RouterView />
            </main>
        </div>
        <MessageNotificationBadge :area="area" />
        <div v-if="drawerOpen" class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true" :aria-label="t('nav.main')">
            <button type="button" class="absolute inset-0 bg-slate-950/65 backdrop-blur-sm" :aria-label="t('common.menuClose')" @click="drawerOpen = false" />
            <div class="absolute inset-y-0 start-0 w-80 max-w-[88vw] shadow-2xl"><AppSidebar :area="area" /><button type="button" class="absolute end-3 top-3 grid size-10 place-items-center rounded-xl border border-white/15 bg-white/10 text-xl text-white hover:bg-white/20" :aria-label="t('common.menuClose')" @click="drawerOpen = false">×</button></div>
        </div>
    </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n';
import PublicFooter from '../components/public/PublicFooter.vue';
import PublicHeader from '../components/public/PublicHeader.vue';

const { t } = useI18n();
</script>

<template>
    <div class="min-h-screen bg-white text-slate-900">
        <a href="#main-content" class="fixed start-4 top-3 z-[60] -translate-y-24 rounded-xl bg-accent px-4 py-2 font-black text-brand-dark transition focus:translate-y-0">{{ t('common.skipContent') }}</a>
        <PublicHeader />
        <main id="main-content" tabindex="-1"><RouterView /></main>
        <PublicFooter />
    </div>
</template>

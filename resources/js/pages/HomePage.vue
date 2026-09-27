<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { useI18n } from 'vue-i18n';
import BaseAlert from '../components/ui/BaseAlert.vue';

const route = useRoute();
const { t } = useI18n();
const startupFailed = computed(() => route.query.startup === 'failed');
</script>

<template>
    <section class="relative isolate overflow-hidden"><div class="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_top_left,rgba(255,210,30,0.22),transparent_35%),linear-gradient(to_bottom,#fff,#f8fafc)]" /><div class="mx-auto grid min-h-[calc(100vh-5rem)] max-w-7xl items-center gap-12 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:py-24"><div><BaseAlert v-if="startupFailed" tone="danger" class="mb-6"><strong>{{ t('errors.startupTitle') }}</strong><span class="ms-2">{{ t('errors.startupText') }}</span></BaseAlert><p class="mb-4 inline-flex rounded-full bg-accent/25 px-4 py-2 text-sm font-black text-brand">JCEC ACADEMY</p><h1 class="max-w-xl text-4xl font-black leading-tight text-slate-950 sm:text-6xl">{{ t('brand.tagline') }}</h1><p class="mt-6 max-w-xl text-lg leading-8 text-slate-600">{{ t('brand.description') }}</p><RouterLink :to="{ name: 'login' }" class="mt-8 inline-flex rounded-xl bg-brand px-6 py-3 font-bold text-white shadow-lg shadow-brand/20 hover:bg-brand-dark">{{ t('common.login') }}</RouterLink></div><div class="relative mx-auto w-full max-w-lg"><div class="absolute inset-8 -z-10 rotate-3 rounded-[2.5rem] bg-accent" /><div class="rounded-[2.5rem] border border-slate-200 bg-white p-8 shadow-2xl"><img :src="'/assets/images/logo-1.png'" alt="JCEC Academy" class="mx-auto max-h-96 w-full object-contain"></div></div></div></section>
</template>
